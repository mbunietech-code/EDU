<?php

namespace App\Services;

use App\Models\ResearchRequest;
use App\Models\ResearchRequestEvent;
use App\Models\ResearchRequestFile;
use App\Models\ResearchRequestMessage;
use App\Models\ResearchRequestStage;
use App\Models\User;
use App\Notifications\Admin\NewResearchRequest;
use App\Notifications\Admin\ResearchRequestActivity;
use App\Notifications\User\ResearchRequestUpdated;
use App\Support\AdminNotifications;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Everything that happens to a research work request, in one place, so the
 * website, the admin panel and the MHub API behave the same: the customer
 * asks, admins review, quote, assign and move it through the stages,
 * deliver drafts and the final work; the customer replies, accepts the
 * quote, asks for revisions and finally accepts and rates the work.
 *
 * Internal notes / internal files stay staff only: customers never get them
 * on a page, in the API or in a notification.
 */
class ResearchRequestService
{
    // --- Customer ----------------------------------------------------------

    /** @param  array<int,UploadedFile>  $files */
    public function create(User $customer, array $data, array $files = []): ResearchRequest
    {
        $request = DB::transaction(function () use ($customer, $data, $files) {
            $stage = ResearchRequestStage::starting();

            $request = ResearchRequest::create([
                'user_id' => $customer->id,
                'title' => trim($data['title']),
                'research_type' => $data['research_type'],
                'field_of_study' => $this->clean($data['field_of_study'] ?? null),
                'description' => trim($data['description']),
                'academic_level' => $data['academic_level'],
                'purpose' => $this->clean($data['purpose'] ?? null),
                'deadline' => $data['deadline'] ?? null,
                'budget' => ($data['budget'] ?? null) !== null && $data['budget'] !== '' ? $data['budget'] : null,
                'currency' => $data['currency'] ?? 'TZS',
                'pages' => ($data['pages'] ?? null) ?: null,
                'citation_style' => $data['citation_style'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'stage_id' => $stage?->id,
                'progress' => $stage?->progress ?? 0,
            ]);
            $request->forceFill(['customer_activity_at' => now(), 'customer_seen_at' => now()])->save();

            $this->storeFiles($request, $customer, $files, 'brief');

            $this->log($request, $customer, 'created', [
                'to_stage_id' => $stage?->id,
                'progress' => $request->progress,
                'note' => 'You sent this request. Our team will review it shortly.',
            ]);

            return $request;
        });

        $request->load('user');
        AdminNotifications::recipients('research_requests.view')
            ->each(fn (User $admin) => $admin->notify(new NewResearchRequest($request)));

        return $request;
    }

    /** @param  array<int,UploadedFile>  $files */
    public function customerMessage(ResearchRequest $request, User $customer, ?string $body, array $files = []): ResearchRequestMessage
    {
        $this->assertOwner($request, $customer);
        $this->assertHasContent($body, $files);

        $message = $this->addMessage($request, $customer, $body, $files, fromAdmin: false);
        $request->forceFill(['customer_activity_at' => now(), 'customer_seen_at' => now()])->save();

        $this->notifyStaff($request, $customer, 'message', $customer->name.' wrote on '.$request->reference.': '.Str::limit((string) ($body ?: 'sent a file'), 120));

        return $message;
    }

    public function answerQuote(ResearchRequest $request, User $customer, bool $accept, ?string $reason = null): void
    {
        $this->assertOwner($request, $customer);
        if (! $request->hasPendingQuote()) {
            throw ValidationException::withMessages(['quote' => 'There is no price waiting for your answer.']);
        }

        DB::transaction(function () use ($request, $customer, $accept, $reason) {
            $request->forceFill([
                'quote_status' => $accept ? 'accepted' : 'declined',
                'quote_answered_at' => now(),
                'customer_activity_at' => now(),
                'customer_seen_at' => now(),
            ])->save();

            $this->log($request, $customer, $accept ? 'quote_accepted' : 'quote_declined', [
                'note' => $accept
                    ? 'You accepted the price of '.$request->quoteLabel().'.'
                    : 'You declined the price'.($reason ? ': '.trim($reason) : '.'),
            ]);

            // Accepting moves a quoted request on to the next stage (normally "In progress").
            if ($accept && $request->stage?->key === ResearchRequestStage::QUOTED) {
                $next = ResearchRequestStage::byKey(ResearchRequestStage::IN_PROGRESS)
                    ?? ResearchRequestStage::query()->where('is_active', true)->where('sort_order', '>', $request->stage->sort_order)->ordered()->first();
                if ($next) {
                    $this->applyStage($request, $customer, $next, null, 'Work starts now that the price is agreed.', null);
                }
            }
        });

        $this->notifyStaff($request, $customer, $accept ? 'quote_accepted' : 'quote_declined',
            $customer->name.($accept ? ' accepted the quote (' : ' declined the quote (').$request->quoteLabel().') on '.$request->reference.($reason ? ': '.Str::limit($reason, 100) : '.'));
    }

    /** @param  array<int,UploadedFile>  $files */
    public function requestRevision(ResearchRequest $request, User $customer, string $reason, array $files = []): void
    {
        $this->assertOwner($request, $customer);
        if (! $request->canRequestRevision()) {
            throw ValidationException::withMessages(['reason' => 'You can ask for changes once a draft or the final work has been delivered.']);
        }

        DB::transaction(function () use ($request, $customer, $reason, $files) {
            $this->addMessage($request, $customer, 'Revision request: '.trim($reason), $files, fromAdmin: false);
            $request->forceFill([
                'revision_count' => $request->revision_count + 1,
                'customer_activity_at' => now(),
                'customer_seen_at' => now(),
            ])->save();

            $this->log($request, $customer, 'revision', ['note' => 'You asked for changes: '.Str::limit(trim($reason), 300)]);

            if ($stage = ResearchRequestStage::byKey(ResearchRequestStage::REVISIONS)) {
                $this->applyStage($request, $customer, $stage, null, null, null);
            }
        });

        $this->notifyStaff($request, $customer, 'revision', $customer->name.' asked for a revision on '.$request->reference.': '.Str::limit($reason, 120));
    }

    public function complete(ResearchRequest $request, User $customer, int $rating, ?string $review = null): void
    {
        $this->assertOwner($request, $customer);
        if (! $request->canBeCompletedByCustomer()) {
            throw ValidationException::withMessages(['rating' => 'You can accept the work once the final work is delivered.']);
        }

        DB::transaction(function () use ($request, $customer, $rating, $review) {
            $request->forceFill([
                'rating' => max(1, min(5, $rating)),
                'review' => $this->clean($review),
                'customer_activity_at' => now(),
                'customer_seen_at' => now(),
            ])->save();

            $this->log($request, $customer, 'completed', ['note' => 'You accepted the work and rated it '.$request->rating.'/5.']);

            $closed = ResearchRequestStage::byKey(ResearchRequestStage::CLOSED);
            if ($closed) {
                $this->applyStage($request, $customer, $closed, 100, null, null);
            } else {
                $request->forceFill(['status' => ResearchRequest::COMPLETED, 'progress' => 100, 'completed_at' => now()])->save();
            }
        });

        $this->notifyStaff($request, $customer, 'completed', $customer->name.' accepted '.$request->reference.' and rated it '.$request->rating.'/5.');
    }

    public function customerCancel(ResearchRequest $request, User $customer, ?string $reason = null): void
    {
        $this->assertOwner($request, $customer);
        if (! $request->canBeCancelledByCustomer()) {
            throw ValidationException::withMessages(['reason' => 'This request can no longer be cancelled here — the work has started. Write to the team instead.']);
        }

        $this->markCancelled($request, $customer, $reason, 'You cancelled this request');
        $request->forceFill(['customer_activity_at' => now(), 'customer_seen_at' => now()])->save();

        $this->notifyStaff($request, $customer, 'cancelled', $customer->name.' cancelled '.$request->reference.($reason ? ': '.Str::limit($reason, 120) : '.'));
    }

    public function markSeenByCustomer(ResearchRequest $request): void
    {
        $request->forceFill(['customer_seen_at' => now()])->saveQuietly();
    }

    // --- Staff ---------------------------------------------------------------

    public function markSeenByStaff(ResearchRequest $request): void
    {
        $request->forceFill(['admin_seen_at' => now()])->saveQuietly();
    }

    /** Move to a stage, with a note for the customer and/or an internal note. */
    public function moveStage(
        ResearchRequest $request,
        User $admin,
        ResearchRequestStage $stage,
        ?int $progress = null,
        ?string $note = null,
        ?string $internalNote = null,
        bool $notify = true,
    ): void {
        $sameStage = (int) $request->stage_id === (int) $stage->id;
        $note = $this->clean($note);
        $internalNote = $this->clean($internalNote);

        DB::transaction(fn () => $this->applyStage($request, $admin, $stage, $progress, $note, $internalNote));

        $this->touchStaff($request, $admin, customerVisible: true);

        if ($notify) {
            $message = $sameStage
                ? 'Progress on '.$request->reference.' is now '.$request->progress.'%.'
                : 'Your request '.$request->reference.' moved to "'.$stage->name.'" ('.$request->progress.'%).';
            if ($note) {
                $message .= ' '.Str::limit($note, 200);
            }
            $important = in_array($stage->key, [ResearchRequestStage::DELIVERED, ResearchRequestStage::CLOSED, ResearchRequestStage::DRAFT_READY], true);
            $this->notifyCustomer($request, 'stage', $message, $important && ! $sameStage);
        }
    }

    public function assign(ResearchRequest $request, User $admin, ?User $assignee): void
    {
        if ($assignee && ! ($assignee->is_admin && $assignee->hasPermission('research_requests.view'))) {
            throw ValidationException::withMessages(['assigned_to' => 'Pick a team member who can open research requests.']);
        }

        $request->forceFill(['assigned_to' => $assignee?->id])->save();
        $this->log($request, $admin, 'assigned', [
            'is_internal' => true,
            'internal_note' => $assignee ? 'Given to '.$assignee->name.($assignee->job_title ? ' ('.$assignee->job_title.')' : '') : 'Unassigned',
            'meta' => ['assigned_to' => $assignee?->id],
        ]);
        $this->touchStaff($request, $admin, customerVisible: false);

        if ($assignee && (int) $assignee->id !== (int) $admin->id) {
            $assignee->notify(new ResearchRequestActivity($request, 'assigned', $admin->name.' gave you research request '.$request->reference.': '.$request->title));
        }
    }

    public function sendQuote(ResearchRequest $request, User $admin, float $amount, string $currency, ?string $note = null): void
    {
        if (! $request->isActive()) {
            throw ValidationException::withMessages(['quote_amount' => 'Reopen the request before sending a price.']);
        }

        DB::transaction(function () use ($request, $admin, $amount, $currency, $note) {
            $request->forceFill([
                'quote_amount' => $amount,
                'quote_currency' => $currency,
                'quote_note' => $this->clean($note),
                'quote_status' => 'pending',
                'quoted_at' => now(),
                'quote_answered_at' => null,
            ])->save();

            $this->log($request, $admin, 'quote_sent', [
                'note' => 'Price: '.$request->quoteLabel().($request->quote_note ? ' — '.$request->quote_note : ''),
                'meta' => ['amount' => $amount, 'currency' => $currency],
            ]);

            $quoted = ResearchRequestStage::byKey(ResearchRequestStage::QUOTED);
            if ($quoted && $quoted->is_active && ($request->stage === null || $request->stage->sort_order < $quoted->sort_order)) {
                $this->applyStage($request, $admin, $quoted, null, null, null);
            }
        });

        $this->touchStaff($request, $admin, customerVisible: true);
        $this->notifyCustomer($request, 'quote', 'Price for '.$request->reference.': '.$request->quoteLabel().'. Open the request to accept or decline it.', true);
    }

    /**
     * Staff reply (customer sees it) or internal note (staff only).
     *
     * @param  array<int,UploadedFile>  $files
     */
    public function staffMessage(ResearchRequest $request, User $admin, ?string $body, array $files = [], bool $internal = false): ResearchRequestMessage
    {
        $this->assertHasContent($body, $files);

        $message = $this->addMessage($request, $admin, $body, $files, fromAdmin: true, internal: $internal);
        $this->touchStaff($request, $admin, customerVisible: ! $internal);

        if (! $internal) {
            $this->notifyCustomer($request, 'message', 'New message from the team on '.$request->reference.': '.Str::limit((string) ($body ?: 'a file was shared'), 140));
        }

        return $message;
    }

    /**
     * Upload drafts or the final work. A draft moves the request to "Draft
     * ready", the final work to "Delivered", unless it is already further on.
     *
     * @param  array<int,UploadedFile>  $files
     */
    public function deliver(ResearchRequest $request, User $admin, string $kind, array $files, ?string $note = null, bool $moveStage = true): void
    {
        if (! in_array($kind, ['draft', 'final'], true) || $files === []) {
            throw ValidationException::withMessages(['files' => 'Choose at least one file to deliver.']);
        }

        DB::transaction(function () use ($request, $admin, $kind, $files, $note, $moveStage) {
            $stored = $this->storeFiles($request, $admin, $files, $kind, note: $this->clean($note));

            $this->log($request, $admin, 'files', [
                'note' => ($kind === 'final' ? 'Final work delivered' : 'Draft shared').' ('.count($stored).' file'.(count($stored) === 1 ? '' : 's').')'.($note ? ': '.trim($note) : '.'),
                'meta' => ['kind' => $kind, 'files' => array_map(fn (ResearchRequestFile $f) => $f->id, $stored)],
            ]);

            if ($kind === 'final' && ! $request->delivered_at) {
                $request->forceFill(['delivered_at' => now()])->save();
            }

            if ($moveStage) {
                $target = ResearchRequestStage::byKey($kind === 'final' ? ResearchRequestStage::DELIVERED : ResearchRequestStage::DRAFT_READY);
                if ($target && $target->is_active && ($request->stage === null || $request->stage->sort_order < $target->sort_order || $request->stage->key === ResearchRequestStage::REVISIONS)) {
                    $this->applyStage($request, $admin, $target, null, null, null);
                }
            }
        });

        $this->touchStaff($request, $admin, customerVisible: true);
        $this->notifyCustomer($request, 'files',
            ($kind === 'final' ? 'Your final work for '.$request->reference.' is ready to download.' : 'A draft for '.$request->reference.' is ready for you to read.'),
            true);
    }

    public function updateDetails(ResearchRequest $request, User $admin, array $data): void
    {
        $before = ['priority' => $request->priority, 'deadline' => $request->deadline?->toDateString()];
        $request->fill([
            'priority' => $data['priority'] ?? $request->priority,
            'deadline' => array_key_exists('deadline', $data) ? ($data['deadline'] ?: null) : $request->deadline,
        ]);
        if ($request->isDirty('deadline')) {
            $request->deadline_alerted_on = null;
        }
        if (! $request->isDirty()) {
            return;
        }
        $request->save();

        $changes = [];
        if ($before['priority'] !== $request->priority) {
            $changes[] = 'priority '.$request->priorityLabel();
        }
        if ($before['deadline'] !== $request->deadline?->toDateString()) {
            $changes[] = 'deadline '.($request->deadline?->format('d M Y') ?? 'none');
        }

        $deadlineChanged = $before['deadline'] !== $request->deadline?->toDateString();
        $this->log($request, $admin, 'details', [
            // The customer sees a new deadline; a priority change is staff only.
            'is_internal' => ! $deadlineChanged,
            'note' => $deadlineChanged ? 'Deadline set to '.($request->deadline?->format('d M Y') ?? 'none').'.' : null,
            'internal_note' => 'Changed '.implode(', ', $changes),
        ]);
        $this->touchStaff($request, $admin, customerVisible: $deadlineChanged);

        if ($deadlineChanged) {
            $this->notifyCustomer($request, 'stage', 'The deadline for '.$request->reference.' is now '.($request->deadline?->format('d M Y') ?? 'open').'.');
        }
    }

    public function staffCancel(ResearchRequest $request, User $admin, string $reason): void
    {
        if (! $request->isActive()) {
            return;
        }
        $this->markCancelled($request, $admin, $reason, 'The team cancelled this request');
        $this->touchStaff($request, $admin, customerVisible: true);
        $this->notifyCustomer($request, 'cancelled', 'Your request '.$request->reference.' was cancelled: '.Str::limit($reason, 160), true);
    }

    public function reopen(ResearchRequest $request, User $admin, ?string $note = null): void
    {
        if ($request->isActive()) {
            return;
        }

        DB::transaction(function () use ($request, $admin, $note) {
            $stage = $request->stage && ! $request->stage->isClosed() && $request->stage->is_active
                ? $request->stage
                : (ResearchRequestStage::byKey(ResearchRequestStage::IN_PROGRESS) ?? ResearchRequestStage::starting());

            $request->forceFill([
                'status' => ResearchRequest::ACTIVE,
                'cancelled_at' => null,
                'cancel_reason' => null,
                'completed_at' => null,
                'stage_id' => $stage?->id,
                'progress' => min($request->progress, $stage?->progress ?? $request->progress),
            ])->save();

            $this->log($request, $admin, 'reopened', ['to_stage_id' => $stage?->id, 'note' => $this->clean($note) ?? 'The request was reopened.']);
        });

        $this->touchStaff($request, $admin, customerVisible: true);
        $this->notifyCustomer($request, 'reopened', 'Your request '.$request->reference.' was reopened.'.($note ? ' '.Str::limit($note, 160) : ''));
    }

    // --- Shared ----------------------------------------------------------------

    /**
     * @param  array<int,UploadedFile>  $files
     * @return list<ResearchRequestFile>
     */
    public function storeFiles(ResearchRequest $request, User $by, array $files, string $kind, ?int $messageId = null, bool $internal = false, ?string $note = null): array
    {
        $stored = [];
        foreach (array_values(array_filter($files)) as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $name = Str::limit(preg_replace('/[^\pL\pN\s._()-]+/u', '', $file->getClientOriginalName()) ?: 'file.'.$ext, 200, '');
            $path = $file->storeAs('research-requests/'.$request->id, Str::random(32).'.'.$ext, ResearchRequestFile::DISK);

            $stored[] = ResearchRequestFile::create([
                'research_request_id' => $request->id,
                'message_id' => $messageId,
                'user_id' => $by->id,
                'kind' => $kind,
                'is_internal' => $internal,
                'path' => $path,
                'original_name' => $name,
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'note' => $note,
            ]);
        }

        return $stored;
    }

    /** Stages for the customer's tracker: done / current / upcoming. */
    public function tracker(ResearchRequest $request): array
    {
        $stages = ResearchRequestStage::flow();
        $current = $request->stage;
        $currentOrder = $current?->sort_order ?? -1;

        return $stages->map(fn (ResearchRequestStage $s) => [
            'id' => $s->id,
            'key' => $s->key,
            'name' => $s->name,
            'description' => $s->description,
            'color' => $s->hex(),
            'progress' => $s->progress,
            'state' => match (true) {
                $request->status === ResearchRequest::COMPLETED => 'done',
                $current && $s->id === $current->id => 'current',
                $s->sort_order < $currentOrder => 'done',
                default => 'upcoming',
            },
        ])->values()->all();
    }

    private function applyStage(ResearchRequest $request, User $by, ResearchRequestStage $stage, ?int $progress, ?string $note, ?string $internalNote): void
    {
        $from = $request->stage_id;
        $progress = $progress === null ? max($stage->progress, $from === $stage->id ? $request->progress : 0) : max(0, min(100, $progress));
        if ($stage->isClosed()) {
            $progress = 100;
        }

        $request->forceFill([
            'stage_id' => $stage->id,
            'progress' => $progress,
            'status' => $stage->isClosed() ? ResearchRequest::COMPLETED : ($request->status === ResearchRequest::CANCELLED ? ResearchRequest::CANCELLED : ResearchRequest::ACTIVE),
            'completed_at' => $stage->isClosed() ? ($request->completed_at ?? now()) : null,
            'delivered_at' => $stage->key === ResearchRequestStage::DELIVERED ? ($request->delivered_at ?? now()) : $request->delivered_at,
        ])->save();
        $request->setRelation('stage', $stage);

        $this->log($request, $by, (int) $from === (int) $stage->id ? 'progress' : 'stage', [
            'from_stage_id' => $from,
            'to_stage_id' => $stage->id,
            'progress' => $progress,
            'note' => $note,
            'internal_note' => $internalNote,
        ]);
    }

    private function markCancelled(ResearchRequest $request, User $by, ?string $reason, string $prefix): void
    {
        $reason = $this->clean($reason);
        $request->forceFill([
            'status' => ResearchRequest::CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $reason ? Str::limit($reason, 490) : null,
        ])->save();

        $this->log($request, $by, 'cancelled', ['note' => $prefix.($reason ? ': '.$reason : '.')]);
    }

    /** @param  array<int,UploadedFile>  $files */
    private function addMessage(ResearchRequest $request, User $by, ?string $body, array $files, bool $fromAdmin, bool $internal = false): ResearchRequestMessage
    {
        return DB::transaction(function () use ($request, $by, $body, $files, $fromAdmin, $internal) {
            $message = ResearchRequestMessage::create([
                'research_request_id' => $request->id,
                'user_id' => $by->id,
                'is_from_admin' => $fromAdmin,
                'is_internal' => $internal,
                'body' => $this->clean($body),
            ]);
            $this->storeFiles($request, $by, $files, 'message', $message->id, $internal);

            return $message;
        });
    }

    private function log(ResearchRequest $request, ?User $by, string $type, array $data = []): ResearchRequestEvent
    {
        return ResearchRequestEvent::create($data + [
            'research_request_id' => $request->id,
            'user_id' => $by?->id,
            'type' => $type,
        ]);
    }

    private function touchStaff(ResearchRequest $request, User $admin, bool $customerVisible): void
    {
        $request->forceFill(array_filter([
            'admin_seen_at' => now(),
            'admin_activity_at' => $customerVisible ? now() : null,
        ]))->save();
    }

    private function notifyCustomer(ResearchRequest $request, string $event, string $message, bool $mail = false): void
    {
        $customer = $request->user ?? User::query()->find($request->user_id);
        if ($customer && $customer->status !== 'suspended') {
            $customer->notify(new ResearchRequestUpdated($request, $event, $message, $mail));
        }
    }

    /** The assignee when there is one, otherwise everyone who can open research requests; never the actor. */
    private function notifyStaff(ResearchRequest $request, User $actor, string $event, string $message): void
    {
        $request->loadMissing('assignee');
        $assignee = $request->assignee;

        $recipients = $assignee && $assignee->is_admin && $assignee->status === 'active' && $assignee->hasPermission('research_requests.view')
            ? collect([$assignee])
            : AdminNotifications::recipients('research_requests.view');

        $recipients
            ->reject(fn (User $u) => (int) $u->id === (int) $actor->id)
            ->each(fn (User $u) => $u->notify(new ResearchRequestActivity($request, $event, $message)));
    }

    private function assertOwner(ResearchRequest $request, User $user): void
    {
        abort_unless($request->isOwnedBy($user), 404);
    }

    /** @param  array<int,mixed>  $files */
    private function assertHasContent(?string $body, array $files): void
    {
        if (trim((string) $body) === '' && array_filter($files) === []) {
            throw ValidationException::withMessages(['body' => 'Write a message or attach a file.']);
        }
    }

    private function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : $text;
    }
}
