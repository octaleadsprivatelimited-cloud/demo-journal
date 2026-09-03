<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArticleStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Article $article,
        public ArticleStatus $from,
        public ArticleStatus $to,
        public ?User $actor = null,
        public ?string $note = null,
    ) {}
}
