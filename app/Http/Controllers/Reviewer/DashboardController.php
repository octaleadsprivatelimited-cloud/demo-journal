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
        $base = Review::query()->where('reviewer_id', $request->user()->getKey());
        $counts = (clone $base)->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('reviewer.dashboard', [
            'stats' => [
                'assigned' => $counts[ReviewStatus::Assigned->value] ?? 0,
                'in_progress' => $counts[ReviewStatus::InProgress->value] ?? 0,
                'completed' => $counts[ReviewStatus::Completed->value] ?? 0,
                'total' => $counts->sum(),
            ],
            'reviews' => (clone $base)->with(['article:id,title,slug,status,submitted_at', 'submission:id,article_id,round'])
                ->orderByRaw('completed_at IS NOT NULL')
                ->orderBy('due_at')
                ->paginate(15),
        ]);
    }
}
