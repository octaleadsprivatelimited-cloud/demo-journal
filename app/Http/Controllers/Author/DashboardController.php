<?php

declare(strict_types=1);

namespace App\Http\Controllers\Author;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = Article::query()->where('created_by_id', $request->user()->getKey());
        $counts = (clone $query)->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('author.dashboard', [
            'stats' => [
                'total' => $counts->sum(),
                'draft' => $counts[ArticleStatus::Draft->value] ?? 0,
                'submitted' => $counts[ArticleStatus::Submitted->value] ?? 0,
                'under_review' => $counts[ArticleStatus::UnderReview->value] ?? 0,
                'revision_required' => $counts[ArticleStatus::RevisionRequired->value] ?? 0,
                'published' => $counts[ArticleStatus::Published->value] ?? 0,
                'rejected' => $counts[ArticleStatus::Rejected->value] ?? 0,
            ],
            'recentArticles' => (clone $query)->with('category:id,name,slug')->latest('updated_at')->limit(6)->get(),
        ]);
    }
}
