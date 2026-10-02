<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\JournalPages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class JournalPageController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('manageSettings');
        $q=trim((string)$request->query('q'));
        $group=(string)$request->query('group');
        $pages=collect(JournalPages::all())->filter(fn($page) => (!$q || str_contains(mb_strtolower($page['title']),mb_strtolower($q))) && (!$group || $page['group']===$group));
        return view('admin.pages.index', compact('pages','q','group'));
    }

    public function edit(string $page)
    {
        Gate::authorize('manageSettings');
        $definition=JournalPages::all()[$page] ?? null;
        abort_unless($definition && !$definition['route'],404);
        $state=JournalPages::state($page);
        return view('admin.pages.edit', compact('definition','state','page'));
    }

    public function update(Request $request, string $page)
    {
        Gate::authorize('manageSettings');
        $definition=JournalPages::all()[$page] ?? null;
        abort_unless($definition && !$definition['route'],404);
        $data=$request->validate([
            'content'=>['required_unless:action,unpublish','nullable','string','max:50000'],
            'action'=>['required','in:draft,publish,unpublish'],
            'approved'=> $request->input('action') === 'publish' ? ['required','accepted'] : ['nullable','boolean'],
            'version'=>['required','integer','min:0'],
        ]);
        DB::transaction(function () use($page,$data) {
            // A row lock also serializes the first edit without replacing existing content.
            Setting::query()->insertOrIgnore(['key'=>'journal_page.'.$page,'value'=>json_encode(JournalPages::state($page)), 'group'=>'journal_pages','is_public'=>false,'created_at'=>now(),'updated_at'=>now()]);
            Setting::whereKey('journal_page.'.$page)->lockForUpdate()->firstOrFail();
            $state=JournalPages::state($page);
            if ((int)$data['version'] !== (int)$state['version']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['content'=>'Another administrator saved this page. Copy your text, reload and review the latest version.']);
            }
            if ($data['action'] !== 'unpublish') $state['draft']=$data['content'];
            if ($data['action'] === 'publish') {
                $state['content']=$data['content'];
                $state['published']=true;
                $state['updated_at']=now()->toIso8601String();
            }
            if ($data['action'] === 'unpublish') $state['published']=false;
            $state['version']++;
            Setting::put('journal_page.'.$page,$state,'journal_pages',false);
        });
        return back()->with('success', match($data['action']) {'publish'=>'Page published.', 'unpublish'=>'Page unpublished. Your text has been retained.', default=>'Draft saved. Public wording has not changed.'});
    }
}
