<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reviewer;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', 'in:assigned,in_progress,completed,declined,cancelled,overdue'], 'q' => ['nullable', 'string', 'max:200']]);
        $base = Review::query()->where('reviewer_id', $request->user()->getKey());
        $counts = (clone $base)->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('reviewer.dashboard', [
            'filters' => $filters,
            'stats' => [
                'assigned' => $counts[ReviewStatus::Assigned->value] ?? 0,
                'in_progress' => $counts[ReviewStatus::InProgress->value] ?? 0,
                'completed' => $counts[ReviewStatus::Completed->value] ?? 0,
                'total' => $counts->sum(),
                'overdue' => (clone $base)->whereIn('status', ['assigned', 'in_progress'])->where('due_at', '<', now())->count(),
            ],
            'reviews' => (clone $base)->with(['article:id,title,slug,status,submitted_at,deleted_at', 'article.workflow', 'submission:id,article_id,round'])
                ->when($filters['status'] ?? null, function ($query, $status) {
                    return $status === 'overdue'
                        ? $query->whereIn('status', ['assigned', 'in_progress'])->where('due_at', '<', now())
                        : $query->where('status', $status);
                })
                ->when($filters['q'] ?? null, fn ($query, $term) => $query->whereHas('article', fn ($articles) => $articles->where(fn ($matches) => $matches->search($term)->orWhereHas('workflow', fn ($workflow) => $workflow->where('manuscript_id', 'like', '%'.$term.'%')))))
                ->orderByRaw('completed_at IS NOT NULL')
                ->orderBy('due_at')
                ->paginate(15)->withQueryString(),
        ]);
    }
}
