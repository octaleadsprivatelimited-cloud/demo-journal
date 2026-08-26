<?php

declare(strict_types=1);

namespace App\Http\Controllers\Editor;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Submission;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('editor.dashboard', [
            'stats' => [
                'pending' => Submission::pending()->count(),
                'underReview' => Article::status(ArticleStatus::UnderReview)->count(),
                'approved' => Article::status(ArticleStatus::Approved)->count(),
                'scheduled' => Article::status(ArticleStatus::Scheduled)->count(),
            ],
            'submissions' => Submission::query()
                ->pending()
                ->with(['article:id,title,slug,status,submitted_at,deleted_at', 'submitter:id,name'])
                ->latest('submitted_at')
                ->limit(8)
                ->get(),
        ]);
    }
}
