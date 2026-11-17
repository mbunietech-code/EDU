<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A customer's order for research work to be done for them (a thesis
 * chapter, a proposal, data analysis…). Admins move it through the process
 * stages (ResearchRequestStage) until it is delivered and closed; the
 * customer follows the tracker, talks with the team, gets drafts and the
 * final work, asks for revisions and finally accepts and rates it.
 *
 * Not the same as Research (work a member writes and publishes).
 */
class ResearchRequest extends Model
{
    public const ACTIVE = 'active';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [self::ACTIVE => 'Active', self::COMPLETED => 'Completed', self::CANCELLED => 'Cancelled'];

    public const TYPES = [
        'thesis' => 'Thesis / dissertation',
        'proposal' => 'Research proposal',
        'chapter' => 'Chapter writing',
        'literature_review' => 'Literature review',
        'data_analysis' => 'Data analysis (SPSS, Stata, R, Python…)',
        'article' => 'Journal article / publication',
        'report' => 'Report / project paper',
        'assignment' => 'Assignment / coursework',
        'editing' => 'Editing, proofreading & formatting',
        'questionnaire' => 'Questionnaire / data collection tools',
        'other' => 'Other',
    ];

    public const LEVELS = [
        'certificate' => 'Certificate',
        'diploma' => 'Diploma',
        'bachelor' => "Bachelor's degree",
        'postgraduate_diploma' => 'Postgraduate diploma',
        'masters' => "Master's degree",
        'phd' => 'PhD / doctorate',
        'professional' => 'Professional / work',
        'personal' => 'Personal / other',
    ];

    public const CITATION_STYLES = [
        'apa' => 'APA (7th)',
        'harvard' => 'Harvard',
        'mla' => 'MLA',
        'chicago' => 'Chicago',
        'ieee' => 'IEEE',
        'vancouver' => 'Vancouver',
        'oscola' => 'OSCOLA',
        'none' => 'Not needed',
        'other' => 'Other / tell us',
    ];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    public const CURRENCIES = ['TZS', 'USD'];

    /** Days before the deadline when a request counts as "due soon". */
    public const DUE_SOON_DAYS = 3;

    protected $fillable = [
        'user_id', 'title', 'research_type', 'field_of_study', 'description', 'academic_level', 'purpose',
        'deadline', 'budget', 'currency', 'pages', 'citation_style', 'priority',
        'stage_id', 'status', 'progress', 'assigned_to',
    ];

    protected $attributes = [
        'status' => self::ACTIVE,
        'priority' => 'normal',
        'currency' => 'TZS',
        'progress' => 0,
    ];

    protected $casts = [
        'deadline' => 'date',
        'budget' => 'decimal:2',
        'quote_amount' => 'decimal:2',
        'pages' => 'integer',
        'progress' => 'integer',
        'rating' => 'integer',
        'revision_count' => 'integer',
        'quoted_at' => 'datetime',
        'quote_answered_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'customer_activity_at' => 'datetime',
        'admin_activity_at' => 'datetime',
        'customer_seen_at' => 'datetime',
        'admin_seen_at' => 'datetime',
        'deadline_alerted_on' => 'date',
    ];

    protected static function booted(): void
    {
        static::created(function (self $request) {
            if (! $request->reference) {
                $request->forceFill(['reference' => 'RR-'.str_pad((string) $request->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });
    }

    // --- Relations ---------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ResearchRequestStage::class, 'stage_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ResearchRequestMessage::class)->oldest('id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ResearchRequestFile::class)->latest('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ResearchRequestEvent::class)->latest('id');
    }

    // --- Scopes ------------------------------------------------------------

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::ACTIVE);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->active()->whereNotNull('deadline')->whereDate('deadline', '<', Carbon::today());
    }

    public function scopeDueSoon(Builder $q): Builder
    {
        $today = Carbon::today();

        return $q->active()->whereNotNull('deadline')
            ->whereDate('deadline', '>=', $today)
            ->whereDate('deadline', '<=', $today->copy()->addDays(self::DUE_SOON_DAYS));
    }

    /** Active requests the team has not looked at since the customer last did something. */
    public function scopeNeedsAdminAttention(Builder $q): Builder
    {
        return $q->active()->where(fn (Builder $q) => $q->whereNull('admin_seen_at')
            ->orWhereColumn('customer_activity_at', '>', 'admin_seen_at'));
    }

    /** Requests with team news the customer has not opened yet. */
    public function scopeUnseenByCustomer(Builder $q): Builder
    {
        return $q->whereNotNull('admin_activity_at')
            ->where(fn (Builder $q) => $q->whereNull('customer_seen_at')
                ->orWhereColumn('admin_activity_at', '>', 'customer_seen_at'));
    }

    /** Board / list ordering: urgent first, then nearest deadline, newest last. */
    public function scopeWorkOrder(Builder $q): Builder
    {
        return $q->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByRaw('deadline IS NULL')
            ->orderBy('deadline')
            ->latest('id');
    }

    // --- State -------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->id;
    }

    public function isOverdue(): bool
    {
        return $this->isActive() && $this->deadline !== null && $this->deadline->lt(Carbon::today());
    }

    public function isDueSoon(): bool
    {
        return $this->isActive() && $this->deadline !== null && ! $this->isOverdue()
            && $this->deadline->lte(Carbon::today()->addDays(self::DUE_SOON_DAYS));
    }

    /** Whole days to the deadline (negative = overdue), or null. */
    public function daysLeft(): ?int
    {
        return $this->deadline ? (int) Carbon::today()->diffInDays($this->deadline, false) : null;
    }

    public function deadlineLabel(): ?string
    {
        $days = $this->daysLeft();
        if ($days === null) {
            return null;
        }
        if (! $this->isActive()) {
            return $this->deadline->format('d M Y');
        }

        return match (true) {
            $days < -1 => abs($days).' days overdue',
            $days === -1 => '1 day overdue',
            $days === 0 => 'Due today',
            $days === 1 => 'Due tomorrow',
            default => $days.' days left',
        };
    }

    /** The customer may cancel while the request is still in an early stage. */
    public function canBeCancelledByCustomer(): bool
    {
        return $this->isActive() && ($this->stage === null || $this->stage->allows_cancel);
    }

    public function hasPendingQuote(): bool
    {
        return $this->isActive() && $this->quote_status === 'pending' && $this->quote_amount !== null;
    }

    /** Drafts / final work the customer can download. */
    public function hasDeliverables(): bool
    {
        return $this->files()->whereIn('kind', ['draft', 'final'])->where('is_internal', false)->exists();
    }

    /** Ask for changes: once something was delivered (draft or final) and the request is still open. */
    public function canRequestRevision(): bool
    {
        return $this->isActive() && $this->stage?->key !== ResearchRequestStage::REVISIONS && $this->hasDeliverables();
    }

    /** Accept & close with a rating: once the final work is delivered. */
    public function canBeCompletedByCustomer(): bool
    {
        return $this->isActive() && (
            $this->stage?->key === ResearchRequestStage::DELIVERED
            || $this->files()->where('kind', 'final')->where('is_internal', false)->exists()
        );
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->research_type] ?? ucfirst(str_replace('_', ' ', (string) $this->research_type));
    }

    public function levelLabel(): string
    {
        return self::LEVELS[$this->academic_level] ?? ucfirst(str_replace('_', ' ', (string) $this->academic_level));
    }

    public function citationLabel(): ?string
    {
        return $this->citation_style ? (self::CITATION_STYLES[$this->citation_style] ?? strtoupper($this->citation_style)) : null;
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst((string) $this->priority);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Short state for lists: the stage while active, else Completed / Cancelled. */
    public function stateLabel(): string
    {
        return $this->isActive() ? ($this->stage?->name ?? 'Received') : $this->statusLabel();
    }

    public static function money(float|string|null $amount, ?string $currency = 'TZS'): ?string
    {
        if ($amount === null || $amount === '') {
            return null;
        }
        $currency = $currency ?: 'TZS';
        $value = (float) $amount;

        return $currency.' '.number_format($value, $currency === 'TZS' ? 0 : 2);
    }

    public function budgetLabel(): ?string
    {
        return self::money($this->budget, $this->currency);
    }

    public function quoteLabel(): ?string
    {
        return self::money($this->quote_amount, $this->quote_currency ?: $this->currency);
    }

    public function priorityAppearance(): string
    {
        return match ($this->priority) {
            'urgent' => 'danger',
            'high' => 'warning',
            'low' => 'neutral',
            default => 'info',
        };
    }

    /** Admins a request can be given to: active staff who can open the requests area. */
    public static function assignablePeople()
    {
        return User::query()->where('is_admin', true)->where('is_guest', false)->where('status', 'active')
            ->orderByRaw('job_title IS NULL')->orderBy('job_title')->orderBy('name')
            ->get(['id', 'name', 'job_title', 'role', 'permissions', 'is_admin'])
            ->filter(fn (User $u) => $u->hasPermission('research_requests.view'))
            ->values();
    }

    /**
     * Sidebar badge for staff. Never breaks the page (e.g. live database
     * before the 0044 alter is applied).
     */
    public static function attentionCount(): int
    {
        try {
            return static::query()->needsAdminAttention()->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Sidebar badge for a customer: their requests with team news they have not opened. */
    public static function unseenCountFor(?User $user): int
    {
        if (! $user) {
            return 0;
        }
        try {
            return static::query()->where('user_id', $user->id)->unseenByCustomer()->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
