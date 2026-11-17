<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreResearchRequest;
use App\Models\ResearchRequest;
use App\Models\ResearchRequestFile;
use App\Models\ResearchRequestStage;
use App\Services\ResearchRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * "Research requests" in the customer dashboard: ask the team to do research
 * work, follow it stage by stage, talk with the team, download drafts and
 * the final work, ask for revisions, accept and rate it. Every action checks
 * the request belongs to the signed-in customer (404 otherwise).
 */
class ResearchRequestController extends Controller
{
    public function __construct(protected ResearchRequestService $service)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));

        $base = ResearchRequest::query()->where('user_id', $user->id);

        $requests = (clone $base)
            ->with('stage')
            ->when(in_array($status, array_keys(ResearchRequest::STATUSES), true), fn ($q) => $q->where('status', $status))
            ->when($status === 'action', fn ($q) => $q->active()->where(fn ($q) => $q->where('quote_status', 'pending')
                ->orWhereHas('stage', fn ($s) => $s->where('key', ResearchRequestStage::DELIVERED))))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('reference', 'like', '%'.$search.'%')))
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $counts = [
            'all' => (clone $base)->count(),
            'active' => (clone $base)->active()->count(),
            'completed' => (clone $base)->where('status', ResearchRequest::COMPLETED)->count(),
            'cancelled' => (clone $base)->where('status', ResearchRequest::CANCELLED)->count(),
            'action' => (clone $base)->active()->where(fn ($q) => $q->where('quote_status', 'pending')
                ->orWhereHas('stage', fn ($s) => $s->where('key', ResearchRequestStage::DELIVERED)))->count(),
        ];

        $unseen = (clone $base)->unseenByCustomer()->pluck('id')->all();

        return view('user.research-requests.index', compact('requests', 'counts', 'status', 'search', 'unseen'));
    }

    public function create(Request $request): View
    {
        abort_if($request->user()->isGuest(), 403);

        $type = (string) $request->query('type', '');

        return view('user.research-requests.create', [
            'preset' => array_key_exists($type, ResearchRequest::TYPES) ? $type : null,
            'stages' => ResearchRequestStage::flow(),
        ]);
    }

    public function store(StoreResearchRequest $request): RedirectResponse
    {
        abort_if($request->user()->isGuest(), 403);

        $rr = $this->service->create($request->user(), $request->validated(), $request->file('files', []));

        return redirect()->route('user.research-requests.show', $rr)
            ->with('success', 'Request '.$rr->reference.' sent. We will review it and get back to you here.');
    }

    public function show(Request $request, ResearchRequest $researchRequest): View
    {
        $rr = $this->owned($request, $researchRequest);
        $this->service->markSeenByCustomer($rr);

        $rr->load([
            'stage', 'assignee:id,name,job_title',
            'messages' => fn ($q) => $q->forCustomer()->with(['user:id,name,is_admin,job_title', 'files' => fn ($f) => $f->forCustomer()]),
            'events' => fn ($q) => $q->forCustomer()->with(['user:id,name,is_admin', 'toStage']),
        ]);

        $files = $rr->files()->forCustomer()->get();

        return view('user.research-requests.show', [
            'rr' => $rr,
            'tracker' => $this->service->tracker($rr),
            'deliverables' => $files->whereIn('kind', ['draft', 'final'])->values(),
            'briefFiles' => $files->where('kind', 'brief')->values(),
        ]);
    }

    public function message(Request $request, ResearchRequest $researchRequest): RedirectResponse
    {
        $rr = $this->owned($request, $researchRequest);
        $request->validate($this->messageRules());

        $this->service->customerMessage($rr, $request->user(), $request->input('body'), $request->file('files', []));

        return redirect()->to(route('user.research-requests.show', $rr).'#messages')->with('success', 'Message sent.');
    }

    public function acceptQuote(Request $request, ResearchRequest $researchRequest): RedirectResponse
    {
        $rr = $this->owned($request, $researchRequest);
        $this->service->answerQuote($rr, $request->user(), true);

        return back()->with('success', 'Thank you — the price is accepted and the team has been told.');
    }

    public function declineQuote(Request $request, ResearchRequest $researchRequest): RedirectResponse
    {
        $rr = $this->owned($request, $researchRequest);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $this->service->answerQuote($rr, $request->user(), false, $data['reason'] ?? null);

        return back()->with('success', 'You declined the price. The team will get back to you.');
    }

    public function revision(Request $request, ResearchRequest $researchRequest): RedirectResponse
    {
        $rr = $this->owned($request, $researchRequest);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
            'files' => ['nullable', 'array', 'max:'.ResearchRequestFile::MAX_FILES],
            'files.*' => ResearchRequestFile::rule(),
        ]);
        $this->service->requestRevision($rr, $request->user(), $data['reason'], $request->file('files', []));

        return redirect()->to(route('user.research-requests.show', $rr).'#messages')->with('success', 'Revision requested. The team has been told what to change.');
    }

    public function complete(Request $request, ResearchRequest $researchRequest): RedirectResponse
    {
        $rr = $this->owned($request, $researchRequest);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:3000'],
        ]);
        $this->service->complete($rr, $request->user(), (int) $data['rating'], $data['review'] ?? null);

        return back()->with('success', 'Thank you for your rating! The request is now closed.');
    }

    public function cancel(Request $request, ResearchRequest $researchRequest): RedirectResponse
    {
        $rr = $this->owned($request, $researchRequest);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->service->customerCancel($rr, $request->user(), $data['reason'] ?? null);

        return back()->with('success', 'Request cancelled.');
    }

    public function file(Request $request, ResearchRequest $researchRequest, ResearchRequestFile $file)
    {
        $rr = $this->owned($request, $researchRequest);
        abort_unless((int) $file->research_request_id === (int) $rr->id && ! $file->is_internal, 404);
        abort_unless(Storage::disk(ResearchRequestFile::DISK)->exists($file->path), 404, 'This file is no longer on the server.');

        return Storage::disk(ResearchRequestFile::DISK)->download($file->path, $file->original_name);
    }

    /** The request, only if it is the signed-in customer's own. */
    private function owned(Request $request, ResearchRequest $rr): ResearchRequest
    {
        abort_unless($rr->isOwnedBy($request->user()), 404);

        return $rr;
    }

    private function messageRules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:'.ResearchRequestFile::MAX_FILES],
            'files.*' => ResearchRequestFile::rule(),
        ];
    }
}
