<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLog extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $fillable = [
        'user_id', 'query', 'filters', 'results_count', 'duration_ms', 'session_id',
        'ip_hash', 'searched_at',
    ];

    protected $hidden = ['ip_hash'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'results_count' => 'integer', 'duration_ms' => 'integer', 'searched_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
