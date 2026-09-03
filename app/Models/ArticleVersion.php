<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id', 'created_by_id', 'version_number', 'title', 'subtitle', 'abstract',
        'content', 'keywords', 'references', 'change_summary',
    ];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'references' => 'array', 'version_number' => 'integer'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
