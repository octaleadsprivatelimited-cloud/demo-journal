<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorDecision extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'revision_deadline' => 'datetime'];
    }

    public function article(): BelongsTo { return $this->belongsTo(Article::class); }
    public function submission(): BelongsTo { return $this->belongsTo(Submission::class); }
    public function decidedBy(): BelongsTo { return $this->belongsTo(User::class, 'decided_by_id'); }
}
