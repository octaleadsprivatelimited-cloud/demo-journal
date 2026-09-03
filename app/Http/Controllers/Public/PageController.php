<?php

namespace App\Http\Controllers\Public;

use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class PageController extends PublicController
{
    public function about(): View
    {
        $publishedCount = $this->publishedArticles()->toBase()->count();
        $authorCount = Author::query()->where('is_active', true)->whereHas('articles', fn ($query) => $query->published())->count();
        $categoryCount = Category::query()->where('is_active', true)->count();

        return view('public.pages.about', $this->publicViewData(compact(
            'publishedCount',
            'authorCount',
            'categoryCount',
        )));
    }

    public function aboutStats(): JsonResponse
    {
        return response()
            ->json([
                'published' => Article::query()->published()->count(),
                'contributors' => Author::query()->where('is_active', true)->whereHas('articles', fn ($query) => $query->published())->count(),
                'fields' => Category::query()->where('is_active', true)->count(),
            ])
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    public function editorialBoard(): View
    {
        $members = Author::query()
            ->where('is_active', true)
            ->whereNotNull('designation')
            ->orderByDesc('is_verified')
            ->orderBy('name')
            ->get();

        $chief = $members->first(fn ($member) => str_contains(strtolower((string) $member->designation), 'chief'));
        $editors = $members->filter(fn ($member) => $member->isNot($chief) && str_contains(strtolower((string) $member->designation), 'editor'));
        $reviewers = $members->reject(fn ($member) => $member->is($chief) || $editors->contains(fn ($editor) => $editor->is($member)));

        return view('public.pages.editorial-board', $this->publicViewData(compact(
            'chief',
            'editors',
            'reviewers',
        )));
    }

    public function contact(): View
    {
        return view('public.pages.contact', $this->publicViewData());
    }

    public function copyright(): View
    {
        return view('public.pages.copyright', $this->publicViewData());
    }

    public function retractionPolicy(): View
    {
        return view('public.pages.retraction-policy', $this->publicViewData());
    }

    public function appeal(): View
    {
        return view('public.pages.appeal', $this->publicViewData());
    }
}
