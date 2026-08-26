<?php

namespace App\Http\Controllers\Public;

use App\Models\Author;
use App\Models\Category;
use App\Models\Setting;
use App\Models\EditorialMember;
use App\Models\IndexingService;
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
        $records = EditorialMember::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        if ($records->isNotEmpty()) {
            $chief = $records->first(fn ($member) => str_contains(strtolower($member->role), 'chief'));
            $editors = $records->where('group', 'editorial_board')->reject(fn ($member) => $chief?->is($member));
            $reviewers = $records->whereIn('group', ['reviewers','advisors','publisher_staff']);
            return view('public.pages.editorial-board', $this->publicViewData(compact('chief','editors','reviewers')));
        }
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

    public function policy(string $page): View
    {
        $pages = [
            'aims-scope' => ['Aims & Scope', 'policy.aims_scope'],
            'peer-review' => ['Peer Review Policy', 'policy.peer_review'],
            'publication-ethics' => ['Publication Ethics', 'policy.publication_ethics'],
            'author-guidelines' => ['Author Guidelines', 'policy.author_guidelines'],
            'copyright' => ['Copyright', 'policy.copyright'],
            'open-access' => ['Open Access', 'policy.open_access'],
            'fees' => ['Fees & APC', 'policy.fees'],
            'indexing' => ['Indexing & Abstracting', 'policy.indexing'],
            'archiving' => ['Archiving', 'policy.archiving'],
            'privacy' => ['Privacy Policy', 'policy.privacy'],
            'terms' => ['Terms', 'policy.terms'],
        ];

        abort_unless(isset($pages[$page]), 404);
        [$title, $key] = $pages[$page];
        $content = Setting::value($key);
        $indexingServices = $page === 'indexing' ? IndexingService::query()->where('is_active', true)->where('status', 'verified')->orderBy('sort_order')->get() : collect();

        return view('public.pages.policy', $this->publicViewData(compact('title', 'content', 'page', 'indexingServices')));
    }

    public function contact(): View
    {
        return view('public.pages.contact', $this->publicViewData());
    }

    public function downloads(): View
    {
        $downloads = $this->featureEnabled('pdf_downloads')
            ? $this->publishedArticles()
                ->where('pdf_download_enabled', true)
                ->orderByDesc('published_at')
                ->get()
            : collect();

        return view('public.pages.downloads', $this->publicViewData(compact('downloads')));
    }
}
