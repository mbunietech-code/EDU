<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A company account login kept in the password vault. Username, password and
 * notes are encrypted with APP_KEY; they are only decrypted when someone with
 * "vault.view" unlocks the vault and asks to see one entry (logged).
 *
 * When APP_KEY is rotated, put the old key in APP_PREVIOUS_KEYS so these can
 * still be read.
 */
class VaultEntry extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'hosting' => 'Hosting & servers',
        'domain' => 'Domains & DNS',
        'email' => 'Email',
        'social' => 'Social media',
        'payment' => 'Payments & banking',
        'software' => 'Software & AI tools',
        'other' => 'Other',
    ];

    protected $fillable = ['name', 'category', 'url', 'username', 'password', 'notes', 'created_by', 'updated_by'];

    protected $casts = [
        'username' => 'encrypted',
        'password' => 'encrypted',
        'notes' => 'encrypted',
    ];

    /** Never put decrypted secrets in arrays / JSON by accident. */
    protected $hidden = ['username', 'password', 'notes'];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }
}
