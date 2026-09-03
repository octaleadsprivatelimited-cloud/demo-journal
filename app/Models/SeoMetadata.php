<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoMetadata extends Model
{
    use HasFactory;

    protected $table = 'seo_metadata';

    protected $fillable = [
        'seoable_type', 'seoable_id', 'seo_title', 'meta_description', 'canonical_url',
        'focus_keywords', 'og_title', 'og_description', 'og_image', 'twitter_card',
        'schema_type', 'structured_data',
    ];

    protected function casts(): array
    {
        return ['focus_keywords' => 'array', 'structured_data' => 'array'];
    }

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
