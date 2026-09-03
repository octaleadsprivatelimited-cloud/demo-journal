<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleView extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $fillable = [
        'article_id', 'user_id', 'session_id', 'ip_hash', 'referrer', 'user_agent', 'viewed_at',
    ];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
