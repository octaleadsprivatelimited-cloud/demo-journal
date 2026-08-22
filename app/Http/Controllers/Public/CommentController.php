<?php

namespace App\Http\Controllers\Public;

use App\Enums\CommentStatus;
use App\Http\Requests\Public\CommentRequest;
use App\Models\Article;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;

class CommentController extends PublicController
{
    public function store(CommentRequest $request, string $slug): RedirectResponse
    {
        $article = Article::query()->published()->where('slug', $slug)->firstOrFail();

        abort_unless($this->featureEnabled('comments') && $article->comments_enabled, 404);

        $validated = $request->validated();

        Comment::query()->create([
            'article_id' => $article->getKey(),
            'user_id' => $request->user()?->getKey(),
            'guest_name' => $request->user() ? null : $validated['guest_name'],
            'guest_email' => $request->user() ? null : $validated['guest_email'],
            'body' => $validated['body'],
            'status' => CommentStatus::Pending,
            'ip_hash' => $request->ip()
                ? hash_hmac('sha256', $request->ip(), (string) config('app.key'))
                : null,
        ]);

        return redirect()
            ->to(route('articles.show', $article->slug).'#discussion')
            ->with('success', 'Thank you. Your comment is awaiting editorial review.');
    }
}
