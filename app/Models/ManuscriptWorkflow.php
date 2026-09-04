<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManuscriptWorkflow extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'deadline' => 'datetime', 'revision' => 'integer'];
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function label(): string
    {
        return str($this->stage)->headline()->toString();
    }
}
