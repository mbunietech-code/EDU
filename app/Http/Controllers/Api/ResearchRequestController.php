<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreResearchRequest;
use App\Models\ResearchRequest;
use App\Models\ResearchRequestEvent;
use App\Models\ResearchRequestFile;
use App\Models\ResearchRequestMessage;
use App\Models\ResearchRequestStage;
use App\Services\ResearchRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Research work requests for the MHub app — the same flow as the website's
 * "Research requests": the customer's own requests only (404 otherwise),
 * and nothing staff-only (internal notes, internal files, internal events)
 * is ever part of a response.
 */
class ResearchRequestController extends Controller
{
    public function __construct(protected ResearchRequestService $service)
    {
    }

    /** Choices for the request form. */
    public function options(): JsonResponse
    {
        $pairs = fn (array $map) => collect($map)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values();

        return response()->json([
            'types' => $pairs(ResearchRequest::TYPES),
            'levels' => $pairs(ResearchRequest::LEVELS),
            'citation_styles' => $pairs(ResearchRequest::CITATION_STYLES),
            'priorities' => $pairs(ResearchRequest::PRIORITIES),
            'currencies' => ResearchRequest::CURRENCIES,
            'stages' => ResearchRequestStage::flow()->map(fn ($s) => ['name' => $s->name, 'description' => $s->description, 'color' => $s->hex(), 'progress' => $s->progress])->values(),
            'files' => ['extensions' => ResearchRequestFile::EXTENSIONS, 'max_mb' => ResearchRequestFile::MAX_KB / 1024, 'max_files' => ResearchRequestFile::MAX_FILES],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('q', ''));
        $base = ResearchRequest::query()->where('user_id', $user->id);

        $items = (clone $base)->with('stage')
            ->when(in_array($status, array_keys(ResearchRequest::STATUSES), true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('reference', 'like', '%'.$search.'%')))
            ->latest('id')
            ->paginate(20);

        $unseen = (clone $base)->unseenByCustomer()->pluck('id')->all();

        return response()->json([
            'data' => collect($items->items())->map(fn (ResearchRequest $rr) => $this->summary($rr) + ['has_update' => in_array($rr->id, $unseen, true)])->values(),
            'counts' => [
                'all' => (clone $base)->count(),
                'active' => (clone $base)->active()->count(),
                'completed' => (clone $base)->where('status', ResearchRequest::COMPLETED)->count(),
                'cancelled' => (clone $base)->where('status', ResearchRequest::CANCELLED)->count(),
            ],
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()],
        ]);
    }

    public function store(StoreResearchRequest $request): JsonResponse
    {
        abort_if($request->user()->isGuest(), 403);

        $rr = $this->service->create($request->user(), $request->validated(), $request->file('files', []));

        return response()->json(['message' => 'Request '.$rr->reference.' sent.', 'data' => $this->detail($rr->fresh())], 201);
    }

    public function show(Request $request, ResearchRequest $researchRequest): JsonResponse
    {
        $rr = $this->owned($request, $researchRequest);
        $this->service->markSeenByCustomer($rr);

        return response()->json(['data' => $this->detail($rr)]);
    }

    public function message(Request $request, ResearchRequest $researchRequest): JsonResponse
    {
        $rr = $this->owned($request, $researchRequest);
        $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:'.ResearchRequestFile::MAX_FILES],
            'files.*' => ResearchRequestFile::rule(),
        ]);

        $msg = $this->service->customerMessage($rr, $request->user(), $request->input('body'), $request->file('files', []));

        return response()->json(['data' => $this->messageRow($rr, $msg->load(['user', 'files']))], 201);
    }

    public function acceptQuote(Request $request, ResearchRequest $researchRequest): JsonResponse
    {
        $rr = $this->owned($request, $researchRequest);
        $this->service->answerQuote($rr, $request->user(), true);

        return response()->json(['message' => 'Price accepted.', 'data' => $this->detail($rr->fresh())]);
    }

    public function declineQuote(Request $request, ResearchRequest $researchRequest): JsonResponse
    {
        $rr = $this->owned($request, $researchRequest);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $this->service->answerQuote($rr, $request->user(), false, $data['reason'] ?? null);

        return response()->json(['message' => 'Price declined.', 'data' => $this->detail($rr->fresh())]);
    }

    public function revision(Request $request, ResearchRequest $researchRequest): JsonResponse
    {
        $rr = $this->owned($request, $researchRequest);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
            'files' => ['nullable', 'array', 'max:'.ResearchRequestFile::MAX_FILES],
            'files.*' => ResearchRequestFile::rule(),
        ]);
        $this->service->requestRevision($rr, $request->user(), $data['reason'], $request->file('files', []));

        return response()->json(['message' => 'Revision requested.', 'data' => $this->detail($rr->fresh())]);
    }

    public function complete(Request $request, ResearchRequest $researchRequest): JsonResponse
    {
        $rr = $this->owned($request, $researchRequest);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:3000'],
        ]);
        $this->service->complete($rr, $request->user(), (int) $data['rating'], $data['review'] ?? null);

        return response()->json(['message' => 'Thank you! The request is closed.', 'data' => $this->detail($rr->fresh())]);
    }

    public function cancel(Request $request, ResearchRequest $researchRequest): JsonResponse
    {
        $rr = $this->owned($request, $researchRequest);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->service->customerCancel($rr, $request->user(), $data['reason'] ?? null);

        return response()->json(['message' => 'Request cancelled.', 'data' => $this->detail($rr->fresh())]);
    }

    /** The file itself (bearer token), e.g. for an in-app preview. */
    public function file(Request $request, ResearchRequest $researchRequest, ResearchRequestFile $file)
    {
        $file = $this->ownedFile($request, $researchRequest, $file);

        return Storage::disk(ResearchRequestFile::DISK)->download($file->path, $file->original_name);
    }

    /** A 5-minute signed link the app opens in the browser to save the file. */
    public function fileUrl(Request $request, ResearchRequest $researchRequest, ResearchRequestFile $file): JsonResponse
    {
        $file = $this->ownedFile($request, $researchRequest, $file);

        return response()->json([
            'url' => URL::temporarySignedRoute('signed.research-requests.file', now()->addMinutes(5), ['file' => $file->id]),
            'name' => $file->original_name,
        ]);
    }

    /** Signed link target (no session in the browser the app opens). */
    public function signedFile(ResearchRequestFile $file)
    {
        abort_if($file->is_internal, 404);
        abort_unless(Storage::disk(ResearchRequestFile::DISK)->exists($file->path), 404);

        return Storage::disk(ResearchRequestFile::DISK)->download($file->path, $file->original_name);
    }

    // --- Shapes (customer-safe) --------------------------------------------------

    private function owned(Request $request, ResearchRequest $rr): ResearchRequest
    {
        abort_unless($rr->isOwnedBy($request->user()), 404);

        return $rr;
    }

    private function ownedFile(Request $request, ResearchRequest $rr, ResearchRequestFile $file): ResearchRequestFile
    {
        $this->owned($request, $rr);
        abort_unless((int) $file->research_request_id === (int) $rr->id && ! $file->is_internal, 404);
        abort_unless(Storage::disk(ResearchRequestFile::DISK)->exists($file->path), 404, 'This file is no longer on the server.');

        return $file;
    }

    private function summary(ResearchRequest $rr): array
    {
        $color = $rr->isActive() ? ($rr->stage?->hex() ?? '#4F46E5') : ($rr->status === ResearchRequest::COMPLETED ? '#059669' : '#9CA3AF');

        return [
            'id' => $rr->id,
            'reference' => $rr->reference,
            'title' => $rr->title,
            'type' => $rr->research_type,
            'type_label' => $rr->typeLabel(),
            'level_label' => $rr->levelLabel(),
            'status' => $rr->status,
            'status_label' => $rr->statusLabel(),
            'state_label' => $rr->stateLabel(),
            'stage' => $rr->stage ? ['id' => $rr->stage->id, 'key' => $rr->stage->key, 'name' => $rr->stage->name, 'description' => $rr->stage->description] : null,
            'color' => $color,
            'progress' => $rr->progress,
            'priority' => $rr->priority,
            'priority_label' => $rr->priorityLabel(),
            'deadline' => $rr->deadline?->toDateString(),
            'deadline_label' => $rr->deadlineLabel(),
            'overdue' => $rr->isOverdue(),
            'due_soon' => $rr->isDueSoon(),
            'pages' => $rr->pages,
            'quote_pending' => $rr->hasPendingQuote(),
            'quote_label' => $rr->quoteLabel(),
            'created_at' => $rr->created_at?->toIso8601String(),
            'created_ago' => $rr->created_at?->diffForHumans(),
        ];
    }

    private function detail(ResearchRequest $rr): array
    {
        $rr->load([
            'stage', 'assignee:id,name,job_title',
            'messages' => fn ($q) => $q->forCustomer()->with(['user:id,name,is_admin', 'files' => fn ($f) => $f->forCustomer()]),
            'events' => fn ($q) => $q->forCustomer()->with('toStage'),
        ]);
        $files = $rr->files()->forCustomer()->get();

        return $this->summary($rr) + [
            'description' => $rr->description,
            'field_of_study' => $rr->field_of_study,
            'purpose' => $rr->purpose,
            'citation_style' => $rr->citation_style,
            'citation_label' => $rr->citationLabel(),
            'budget' => $rr->budget !== null ? (float) $rr->budget : null,
            'budget_label' => $rr->budgetLabel(),
            'currency' => $rr->currency,
            'quote' => $rr->quote_amount !== null ? [
                'amount' => (float) $rr->quote_amount,
                'currency' => $rr->quote_currency ?: $rr->currency,
                'label' => $rr->quoteLabel(),
                'note' => $rr->quote_note,
                'status' => $rr->quote_status,
                'quoted_at' => $rr->quoted_at?->toIso8601String(),
            ] : null,
            'handled_by' => $rr->assignee ? ['name' => $rr->assignee->name, 'job_title' => $rr->assignee->job_title] : null,
            'revision_count' => $rr->revision_count,
            'rating' => $rr->rating,
            'review' => $rr->review,
            'cancel_reason' => $rr->cancel_reason,
            'can' => [
                'cancel' => $rr->canBeCancelledByCustomer(),
                'answer_quote' => $rr->hasPendingQuote(),
                'request_revision' => $rr->canRequestRevision(),
                'complete' => $rr->canBeCompletedByCustomer(),
                'message' => $rr->isActive(),
            ],
            'tracker' => $this->service->tracker($rr),
            'deliverables' => $files->whereIn('kind', ['draft', 'final'])->map(fn ($f) => $this->fileRow($rr, $f))->values(),
            'attachments' => $files->where('kind', 'brief')->map(fn ($f) => $this->fileRow($rr, $f))->values(),
            'messages' => $rr->messages->map(fn (ResearchRequestMessage $m) => $this->messageRow($rr, $m))->values(),
            'history' => $rr->events->map(fn (ResearchRequestEvent $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'label' => $e->label(),
                'note' => $e->note,
                'progress' => in_array($e->type, ['stage', 'progress'], true) ? $e->progress : null,
                'color' => $e->toStage?->hex(),
                'created_at' => $e->created_at?->toIso8601String(),
                'time' => $e->created_at?->format('d M Y, H:i'),
            ])->values(),
        ];
    }

    private function messageRow(ResearchRequest $rr, ResearchRequestMessage $m): array
    {
        return [
            'id' => $m->id,
            'from_team' => (bool) $m->is_from_admin,
            'author' => $m->is_from_admin ? ($m->user?->name ?? 'MbunieEduHub team') : 'You',
            'body' => $m->body,
            'files' => $m->files->reject(fn ($f) => $f->is_internal)->map(fn ($f) => $this->fileRow($rr, $f))->values(),
            'created_at' => $m->created_at?->toIso8601String(),
            'time' => \App\Support\MessageTime::label($m->created_at),
        ];
    }

    private function fileRow(ResearchRequest $rr, ResearchRequestFile $f): array
    {
        return [
            'id' => $f->id,
            'kind' => $f->kind,
            'kind_label' => $f->kindLabel(),
            'name' => $f->original_name,
            'extension' => $f->extension(),
            'size' => $f->size,
            'size_label' => $f->sizeLabel(),
            'note' => $f->note,
            'created_at' => $f->created_at?->toIso8601String(),
            'url_endpoint' => '/research-requests/'.$rr->id.'/files/'.$f->id.'/url',
        ];
    }
}
