<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowActivity extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'author_visible' => 'boolean'];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
