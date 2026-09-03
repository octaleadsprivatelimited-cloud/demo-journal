<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    use HasFactory, HasUniqueSlug, SoftDeletes;

    protected $fillable = ['name', 'slug', 'description'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_tags');
    }

    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }
}
