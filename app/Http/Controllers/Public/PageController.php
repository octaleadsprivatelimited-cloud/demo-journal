<?php

namespace App\Http\Controllers\Public;

use App\Models\Author;
use App\Models\Category;
use App\Models\Setting;
use App\Models\EditorialMember;
use App\Models\IndexingService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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

    public function resources(): View
    {
        return view('public.pages.resources', $this->publicViewData());
    }

    public function editorialBoard(): View
    {
        $registeredEditors = User::publicProfile()->whereHas('roles', fn ($q) => $q->where('slug', 'editor'))->orderBy('name')->get();
        $records = EditorialMember::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        if ($records->isNotEmpty()) {
            $chief = $records->first(fn ($member) => str_contains(strtolower($member->role), 'chief'));
            $editors = $records->where('group', 'editorial_board')->reject(fn ($member) => $chief?->is($member));
            $reviewers = $records->whereIn('group', ['reviewers','advisors','publisher_staff']);
            return view('public.pages.editorial-board', $this->publicViewData(compact('chief','editors','reviewers','registeredEditors')));
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
            'registeredEditors',
        )));
    }

    public function people(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $people = User::publicProfile()
            ->when($term, function ($query) use ($term): void {
                $like = '%'.addcslashes($term, '%_').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)
                    ->orWhere('organization', 'like', $like)->orWhere('designation', 'like', $like));
            })->orderBy('name')->paginate(18)->withQueryString();

        return view('public.people.index', $this->publicViewData(compact('people', 'term')));
    }

    public function profile(User $user): View
    {
        abort_unless($user->hasPublicProfile(), 404);
        $articles = $this->publishedArticles()
            ->whereHas('authors', fn ($q) => $q->where('user_id', $user->id))
            ->latest('published_at')->paginate(10);

        return view('public.people.show', $this->publicViewData(compact('user', 'articles')));
    }

    public function policy(string $page): View
    {
        $definition=\App\Services\JournalPages::all()[$page] ?? null;
        abort_unless($definition && !$definition['route'],404);
        $title=$definition['title'];
        $state=\App\Services\JournalPages::state($page);
        $content=$state['published'] ? $state['content'] : null;
        $indexingServices=in_array($page,['indexing','database-coverage']) ? IndexingService::where('is_active',true)->where('status','verified')->orderBy('sort_order')->get() : collect();
        $facts=match($page) {
            'issn'=>['Print ISSN'=>Setting::value('journal.issn'),'Electronic ISSN'=>Setting::value('journal.eissn')],
            'publisher'=>['Publisher'=>Setting::value('journal.publisher_name'),'Address'=>Setting::value('journal.publisher_address')],
            'publication-frequency'=>['Publication frequency'=>Setting::value('journal.frequency')],
            'journal-history'=>['History'=>Setting::value('journal.publication_history')],
            default=>[],
        };
        $facts=array_filter($facts,fn($value)=>filled($value) && !str_contains(strtoupper((string)$value),'CLIENT INPUT'));
        return view('public.pages.policy',$this->publicViewData(compact('title','content','page','definition','state','indexingServices','facts')));
    }

    public function directory(\Illuminate\Http\Request $request): View
    {
        $q=trim((string)$request->query('q'));
        $group=(string)$request->query('group');
        $pages=collect(\App\Services\JournalPages::all())->filter(fn($page)=>(!$q || str_contains(mb_strtolower($page['title']),mb_strtolower($q))) && (!$group || $page['group']===$group));
        return view('public.pages.directory',$this->publicViewData(compact('pages','q','group')));
    }

    public function corrections(\Illuminate\Http\Request $request): View
    {
        $type=(string)$request->query('type');
        $q=trim((string)$request->query('q'));
        $notices=$this->publishedArticles()->whereIn('publication_notice',['correction','retraction','expression_of_concern'])
            ->when(in_array($type,['correction','retraction','expression_of_concern']),fn($query)=>$query->where('publication_notice',$type))
            ->when($q,fn($query)=>$query->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($q).'%']))
            ->latest('updated_at')->paginate(20)->withQueryString();
        return view('public.pages.corrections',$this->publicViewData(compact('notices','q','type')));
    }

    public function currentIssue()
    {
        $issue=\App\Models\JournalIssue::query()->where('is_current',true)->whereHas('articles')->orderByDesc('publication_date')->first();
        return $issue ? redirect()->route('archive.issue',$issue) : redirect()->route('archive.index');
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
