<?php

namespace App\Http\Controllers\Public;

use App\Models\Author;
use App\Models\Category;
use Illuminate\Contracts\View\View;

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
}
