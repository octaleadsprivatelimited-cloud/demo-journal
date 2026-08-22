<?php

declare(strict_types=1);

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ReviewFeedbackController extends Controller
{
    public function __invoke(Request $request): View
    {
        $reviews = Review::query()->whereHas('article', fn ($query) => $query->where('created_by_id', $request->user()->getKey()))->whereNotNull('completed_at')
            ->with(['article:id,title,slug,status', 'submission:id,article_id,round', 'comments' => fn ($query) => $query->where('is_confidential', false)])->latest('completed_at')->paginate(15);

        return view('author.reviews.index', compact('reviews'));
    }
}
