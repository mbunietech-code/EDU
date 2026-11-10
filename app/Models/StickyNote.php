<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** A note on the admin dashboard board, seen by every admin and helpdesk member. */
class StickyNote extends Model
{
    /** color => [background, border] */
    public const COLORS = [
        'yellow' => ['#fef9c3', '#fde047'],
        'green' => ['#dcfce7', '#86efac'],
        'blue' => ['#dbeafe', '#93c5fd'],
        'pink' => ['#fce7f3', '#f9a8d4'],
        'purple' => ['#ede9fe', '#c4b5fd'],
    ];

    protected $fillable = ['body', 'color', 'pinned', 'created_by', 'updated_by'];

    protected $casts = ['pinned' => 'boolean'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Validation for a note from the web board or the app. */
    public static function rules(): array
    {
        return [
            // No length limit for people; only the database column's own size (TEXT, ~64 KB).
            'body' => ['required', 'string', function (string $attr, mixed $value, \Closure $fail) {
                if (strlen((string) $value) > 65000) {
                    $fail('This note is too long to save. Split it into two notes.');
                }
            }],
            'color' => ['required', \Illuminate\Validation\Rule::in(array_keys(self::COLORS))],
        ];
    }

    /** The author and super admins may change or remove a note; everyone else reads it. */
    public function canBeChangedBy(User $user): bool
    {
        return $user->isSuperAdmin() || $this->created_by === $user->id;
    }

    /** Notes others added or changed since this admin last opened the notes page (top-bar dot). */
    public static function unseenCountFor(User $user): int
    {
        try {
            if (! Schema::hasTable('sticky_notes')) {
                return 0;
            }
            $seen = Cache::get('sticky-notes-seen:'.$user->id);

            return self::query()
                ->where(fn ($q) => $q->whereNull('updated_by')->orWhere('updated_by', '!=', $user->id))
                ->when($seen, fn ($q) => $q->where('updated_at', '>', $seen))
                ->count();
        } catch (\Throwable) {
            return 0; // the top bar must never break because of notes
        }
    }

    public static function markSeen(User $user): void
    {
        Cache::forever('sticky-notes-seen:'.$user->id, now()->toDateTimeString());
    }

    /** @return array{0:string,1:string} */
    public function palette(): array
    {
        return self::COLORS[$this->color] ?? self::COLORS['yellow'];
    }
}
