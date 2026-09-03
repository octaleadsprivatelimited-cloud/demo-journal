<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewComment extends Model
{
    use HasFactory;

    protected $fillable = ['review_id', 'user_id', 'body', 'location', 'is_confidential'];

    protected function casts(): array
    {
        return ['is_confidential' => 'boolean'];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
