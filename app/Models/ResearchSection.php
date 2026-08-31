<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ResearchSection extends Model
{
    protected $fillable = ['research_chapter_id', 'heading', 'body', 'position'];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(ResearchChapter::class, 'research_chapter_id');
    }

    /** Rendered HTML from the Markdown body (safe subset). */
    public function bodyHtml(): string
    {
        if (blank($this->body)) {
            return '';
        }

        return Str::markdown($this->body, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    public function excerpt(int $words = 40): string
    {
        return Str::words(trim(strip_tags($this->bodyHtml())), $words);
    }
}
