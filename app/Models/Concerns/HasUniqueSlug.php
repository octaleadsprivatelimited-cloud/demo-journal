<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUniqueSlug
{
    public static function bootHasUniqueSlug(): void
    {
        static::saving(function (Model $model): void {
            if ($model->getAttribute('slug') && ! $model->isDirty($model->getSlugSourceColumn())) {
                return;
            }

            $source = (string) $model->getAttribute($model->getSlugSourceColumn());
            $base = Str::limit(Str::slug($source), 220, '');
            $base = $base !== '' ? $base : Str::lower(Str::random(10));
            $slug = $base;
            $suffix = 2;

            while (static::query()
                ->withoutGlobalScopes()
                ->where('slug', $slug)
                ->when($model->exists, fn ($query) => $query->where($model->getKeyName(), '!=', $model->getKey()))
                ->exists()) {
                $slug = $base.'-'.$suffix++;
            }

            $model->setAttribute('slug', $slug);
        });
    }

    protected function getSlugSourceColumn(): string
    {
        return 'name';
    }
}
