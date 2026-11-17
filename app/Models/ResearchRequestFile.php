<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file on a research work request, kept on the private disk and only
 * served through authorised download routes.
 *  - brief:   attached by the customer when describing the work
 *  - message: attached to a message
 *  - draft:   a draft delivered by the team
 *  - final:   the final work delivered by the team
 */
class ResearchRequestFile extends Model
{
    public const DISK = 'private';

    public const KINDS = ['brief' => 'Brief', 'message' => 'Message attachment', 'draft' => 'Draft', 'final' => 'Final work'];

    /** Allowed uploads (extensions) and the per-file limit in KB. */
    public const EXTENSIONS = ['pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'zip', 'sav', 'dta', 'r', 'py', 'ipynb'];

    public const MAX_KB = 20480;

    public const MAX_FILES = 8;

    protected $fillable = ['research_request_id', 'message_id', 'user_id', 'kind', 'is_internal', 'path', 'original_name', 'mime', 'size', 'note'];

    protected $casts = [
        'is_internal' => 'boolean',
        'size' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleted(function (self $file) {
            Storage::disk(self::DISK)->delete($file->path);
        });
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ResearchRequest::class, 'research_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForCustomer(Builder $q): Builder
    {
        return $q->where('is_internal', false);
    }

    public function isDeliverable(): bool
    {
        return in_array($this->kind, ['draft', 'final'], true);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst($this->kind);
    }

    public function sizeLabel(): string
    {
        $bytes = (int) $this->size;

        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => number_format($bytes / 1024).' KB',
            default => $bytes.' B',
        };
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    /** Validation rule for one uploaded file. */
    public static function rule(): array
    {
        // Checked by the file's own extension (research formats like .sav / .dta have no reliable MIME); files are only ever served as downloads.
        return ['file', 'max:'.self::MAX_KB, 'extensions:'.implode(',', self::EXTENSIONS)];
    }
}
