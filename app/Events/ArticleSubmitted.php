<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Article;
use App\Models\Submission;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArticleSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Article $article, public Submission $submission) {}
}
