<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResearchRequest;
use App\Models\ResearchRequestFile;
use App\Models\ResearchRequestStage;
use App\Models\User;
use App\Services\DeletionService;
use App\Services\ResearchRequestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Research work requests board: every request customers sent, as a list or
 * a kanban by stage, with search, filters and summary counts. On a request:
 * move the stage (note for the customer + internal note + progress %),
 * assign it, quote a price, reply or leave internal notes, deliver drafts
 * and the final work, change priority / deadline, cancel, reopen.
 *
 * research_requests.view to look, research_requests.manage to act (routes).
 */
class ResearchRequestController extends Controller
{
    public function __construct(protected ResearchRequestService $service)
    {
    }

    public function index(Request $request): View
    {
        $view = $request->query('view') === 'board' ? 'board' : 'list';
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'active'),
            'stage' => $request->integer('stage') ?: null,
            'priority' => (string) $request->query('priority', ''),
            'assignee' => (string) $request->query('assignee', ''),
            'deadline' => (string) $request->query('deadline', ''),
        ];
        $me = $request->user();

        $query = ResearchRequest::query()
            ->with(['user:id,name,email', 'stage', 'assignee:id,name'])
            ->withCount(['messages as customer_messages_count' => fn ($q) => $q->where('is_from_admin', false)])
            ->when($filters['q'] !== '', function (Builder $q) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $q->where(fn (Builder $q) => $q->where('title', 'like', $term)->orWhere('reference', 'like', $term)
                    ->orWhere('field_of_study', 'like', $term)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->when(in_array($filters['status'], array_keys(ResearchRequest::STATUSES), true), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['status'] === 'attention', fn (Builder $q) => $q->needsAdminAttention())
            ->when($filters['stage'], fn (Builder $q) => $q->where('stage_id', $filters['stage']))
            ->when(array_key_exists($filters['priority'], ResearchRequest::PRIORITIES), fn (Builder $q) => $q->where('priority', $filters['priority']))
            ->when($filters['assignee'] === 'me', fn (Builder $q) => $q->where('assigned_to', $me->id))
            ->when($filters['assignee'] === 'none', fn (Builder $q) => $q->whereNull('assigned_to'))
            ->when(ctype_digit($filters['assignee']), fn (Builder $q) => $q->where('assigned_to', (int) $filters['assignee']))
            ->when($filters['deadline'] === 'overdue', fn (Builder $q) => $q->overdue())
            ->when($filters['deadline'] === 'soon', fn (Builder $q) => $q->dueSoon())
            ->when($filters['deadline'] === 'week', fn (Builder $q) => $q->active()->whereNotNull('deadline')->whereBetween('deadline', [Carbon::today(), Carbon::today()->addDays(7)]))
            ->workOrder();

        $stages = ResearchRequestStage::flow();

        if ($view === 'board') {
            // Board: active work by stage (closed shows the most recent ones only).
            $items = (clone $query)->limit(400)->get();
            $columns = $stages->map(fn (ResearchRequestStage $s) => [
                'stage' => $s,
                'items' => $items->where('stage_id', $s->id)->values()->take($s->isClosed() ? 20 : 200),
            ]);
            $requests = null;
        } else {
            $requests = $query->paginate(20)->withQueryString();
            $columns = null;
        }

        $unseenIds = ResearchRequest::query()->needsAdminAttention()->pluck('id')->all();

        $summary = [
            'active' => ResearchRequest::query()->active()->count(),
            'attention' => count($unseenIds),
            'unassigned' => ResearchRequest::query()->active()->whereNull('assigned_to')->count(),
            'overdue' => ResearchRequest::query()->overdue()->count(),
            'due_soon' => ResearchRequest::query()->dueSoon()->count(),
            'quotes' => ResearchRequest::query()->active()->where('quote_status', 'pending')->count(),
            'completed_month' => ResearchRequest::query()->where('status', ResearchRequest::COMPLETED)->where('completed_at', '>=', now()->startOfMonth())->count(),
            'rating' => round((float) ResearchRequest::query()->whereNotNull('rating')->avg('rating'), 1),
        ];

        return view('admin.research-requests.index', [
            'view' => $view,
            'filters' => $filters,
            'requests' => $requests,
            'columns' => $columns,
            'stages' => $stages,
            'people' => ResearchRequest::assignablePeople(),
            'summary' => $summary,
            'unseenIds' => $unseenIds,
            'canManage' => $me->can('research_requests.manage'),
        ]);
    }

    public function show(Request $request, ResearchRequest $researchRequest): View
    {
        $rr = $researchRequest;
        $this->service->markSeenByStaff($rr);

        $rr->load([
            'user:id,name,email,created_at',
            'stage', 'assignee:id,name,job_title',
            'messages.user:id,name,is_admin,job_title', 'messages.files',
            'events' => fn ($q) => $q->with(['user:id,name,is_admin', 'toStage', 'fromStage']),
            'files.user:id,name',
        ]);

        $customerHistory = ResearchRequest::query()->where('user_id', $rr->user_id)->whereKeyNot($rr->id)
            ->with('stage')->latest('id')->limit(5)->get();

        return view('admin.research-requests.show', [
            'rr' => $rr,
            'tracker' => $this->service->tracker($rr),
            'stages' => ResearchRequestStage::flow(),
            'people' => ResearchRequest::assignablePeople(),
            'customerHistory' => $customerHistory,
            'canManage' => $request->user()->can('research_requests.manage'),
        ]);
    }

    public function stage(Request $request, ResearchRequest $researchRequest): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'stage_id' => ['required', 'integer', Rule::exists('research_request_stages', 'id')],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:3000'],
            'internal_note' => ['nullable', 'string', 'max:3000'],
            'notify' => ['nullable', 'boolean'],
        ]);
        $stage = ResearchRequestStage::query()->findOrFail($data['stage_id']);

        $this->service->moveStage(
            $researchRequest, $request->user(), $stage,
            isset($data['progress']) ? (int) $data['progress'] : null,
            $data['note'] ?? null, $data['internal_note'] ?? null,
            $request->boolean('notify', true),
        );

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'stage' => $stage->name, 'progress' => $researchRequest->fresh()->progress]);
        }

        return back()->with('success', $researchRequest->reference.' is now at "'.$stage->name.'" ('.$researchRequest->progress.'%).');
    }

    public function assign(Request $request, ResearchRequest $researchRequest): RedirectResponse
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')]]);
        $assignee = ! empty($data['assigned_to']) ? User::query()->find($data['assigned_to']) : null;

        $this->service->assign($researchRequest, $request->user(), $assignee);

        return back()->with('success', $assignee ? 'Given to '.$assignee->name.'.' : 'Unassigned.');
    }

    public function quote(Request $request, ResearchRequest $researchRequest): RedirectResponse
    {
        $data = $request->validate([
            'quote_amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'quote_currency' => ['required', Rule::in(ResearchRequest::CURRENCIES)],
            'quote_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $this->service->sendQuote($researchRequest, $request->user(), (float) $data['quote_amount'], $data['quote_currency'], $data['quote_note'] ?? null);

        return back()->with('success', 'Price sent to the customer.');
    }

    public function message(Request $request, ResearchRequest $researchRequest): RedirectResponse
    {
        $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'internal' => ['nullable', 'boolean'],
            'files' => ['nullable', 'array', 'max:'.ResearchRequestFile::MAX_FILES],
            'files.*' => ResearchRequestFile::rule(),
        ]);
        $internal = $request->boolean('internal');

        $this->service->staffMessage($researchRequest, $request->user(), $request->input('body'), $request->file('files', []), $internal);

        return redirect()->to(route('admin.research-requests.show', $researchRequest).'#messages')
            ->with('success', $internal ? 'Internal note saved (the customer cannot see it).' : 'Message sent to the customer.');
    }

    public function deliver(Request $request, ResearchRequest $researchRequest): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['draft', 'final'])],
            'note' => ['nullable', 'string', 'max:500'],
            'move_stage' => ['nullable', 'boolean'],
            'files' => ['required', 'array', 'min:1', 'max:'.ResearchRequestFile::MAX_FILES],
            'files.*' => ResearchRequestFile::rule(),
        ]);

        $this->service->deliver($researchRequest, $request->user(), $data['kind'], $request->file('files', []), $data['note'] ?? null, $request->boolean('move_stage', true));

        return back()->with('success', $data['kind'] === 'final' ? 'Final work delivered — the customer was told.' : 'Draft shared with the customer.');
    }

    public function details(Request $request, ResearchRequest $researchRequest): RedirectResponse
    {
        $data = $request->validate([
            'priority' => ['required', Rule::in(array_keys(ResearchRequest::PRIORITIES))],
            'deadline' => ['nullable', 'date'],
        ]);

        $this->service->updateDetails($researchRequest, $request->user(), $data);

        return back()->with('success', 'Details saved.');
    }

    public function cancel(Request $request, ResearchRequest $researchRequest): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->service->staffCancel($researchRequest, $request->user(), $data['reason']);

        return back()->with('success', 'Request cancelled and the customer told.');
    }

    public function reopen(Request $request, ResearchRequest $researchRequest): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $this->service->reopen($researchRequest, $request->user(), $data['note'] ?? null);

        return back()->with('success', 'Request reopened.');
    }

    public function destroy(Request $request, ResearchRequest $researchRequest, DeletionService $deletion): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        // Files leave the disk one by one (their deleted hook), then the snapshot + row go.
        $researchRequest->files()->get()->each->delete();
        $deletion->delete($researchRequest, $data['reason']);

        return redirect()->route('admin.research-requests.index')->with('success', 'Request deleted.');
    }

    public function file(ResearchRequest $researchRequest, ResearchRequestFile $file)
    {
        abort_unless((int) $file->research_request_id === (int) $researchRequest->id, 404);
        abort_unless(Storage::disk(ResearchRequestFile::DISK)->exists($file->path), 404, 'This file is no longer on the server.');

        return Storage::disk(ResearchRequestFile::DISK)->download($file->path, $file->original_name);
    }
}
