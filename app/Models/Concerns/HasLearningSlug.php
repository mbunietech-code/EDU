<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Slug generation shared by the soft-deleting learning models (categories,
 * courses, videos, rooms). Mirrors Research::uniqueSlug(): trashed rows still
 * own their slug so a restore can never collide with a newer record.
 *
 * The using model declares which attribute the slug is derived from via
 * slugSource() ('name' or 'title').
 */
trait HasLearningSlug
{
    abstract protected function slugSource(): string;

    protected static function bootHasLearningSlug(): void
    {
        static::saving(function (self $model) {
            if (empty($model->slug)) {
                $model->slug = static::uniqueSlug((string) $model->{$model->slugSource()}, $model->getKey());
            }
        });
    }

    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = rtrim(Str::limit(Str::slug($source), 200, ''), '-') ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
