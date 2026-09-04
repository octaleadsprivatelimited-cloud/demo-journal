<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowFile extends Model
{
    protected $guarded = [];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
