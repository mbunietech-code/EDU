<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /** The author and super admins may change or remove a note; everyone else reads it. */
    public function canBeChangedBy(User $user): bool
    {
        return $user->isSuperAdmin() || $this->created_by === $user->id;
    }

    /** @return array{0:string,1:string} */
    public function palette(): array
    {
        return self::COLORS[$this->color] ?? self::COLORS['yellow'];
    }
}
