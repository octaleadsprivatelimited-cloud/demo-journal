<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoiRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['registered_at' => 'datetime', 'last_synced_at' => 'datetime'];
    }

    public function article(): BelongsTo { return $this->belongsTo(Article::class); }
}
