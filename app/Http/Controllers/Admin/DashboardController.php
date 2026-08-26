<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleStatus;
use App\Enums\ContactStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleView;
use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContactSubmission;
use App\Models\NewsletterSubscriber;
use App\Models\Review;
use App\Models\Submission;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('viewAdminDashboard');

        $monthly = Article::query()->whereNotNull('published_at')->where('published_at', '>=', now()->subMonths(11)->startOfMonth())
            ->orderBy('published_at')->get(['published_at'])->groupBy(fn (Article $article) => $article->published_at->format('Y-m'))
            ->map(fn ($rows, string $month) => ['label' => $month, 'value' => $rows->count()])->values();
        $statusData = Article::query()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $pendingSubmissions = Submission::query()->pending()->with(['article:id,title,slug,status,deleted_at', 'submitter:id,name'])->latest('submitted_at')->limit(6)->get();
        $reviewsNeedingAttention = Review::query()->whereIn('status', ['assigned', 'in_progress'])->whereNotNull('due_at')->where('due_at', '<=', now()->addDays(7))
            ->with(['article:id,title,slug,deleted_at', 'reviewer:id,name'])->orderBy('due_at')->limit(6)->get();
        $pendingApplications = User::query()->where('status', 'pending')->whereNotNull('requested_role')->oldest()->limit(6)->get(['id', 'name', 'email', 'requested_role', 'created_at']);

        return view('admin.dashboard', [
            'stats' => [
                'articles' => Article::withTrashed()->count(), 'published' => Article::status(ArticleStatus::Published)->count(),
                'pending' => Submission::pending()->count(), 'drafts' => Article::status(ArticleStatus::Draft)->count(),
                'authors' => Author::count(), 'users' => User::count(), 'categories' => Category::count(), 'tags' => Tag::count(),
                'contacts' => ContactSubmission::status(ContactStatus::New)->count(), 'subscribers' => NewsletterSubscriber::active()->count(),
                'views' => ArticleView::count(),
                'reviewsDue' => Review::query()->whereIn('status', ['assigned', 'in_progress'])->whereNotNull('due_at')->where('due_at', '<=', now()->addDays(7))->count(),
                'applications' => User::query()->where('status', 'pending')->whereNotNull('requested_role')->count(),
            ],
            'monthlyPublications' => $monthly,
            'statusData' => $statusData,
            'pendingSubmissions' => $pendingSubmissions,
            'reviewsNeedingAttention' => $reviewsNeedingAttention,
            'pendingApplications' => $pendingApplications,
            'popularArticles' => Article::query()->published()->with('category:id,name')->orderByDesc('view_count')->limit(6)->get(),
            'activity' => AuditLog::query()->with('actor:id,name')->latest('created_at')->limit(10)->get(),
        ]);
    }
}
