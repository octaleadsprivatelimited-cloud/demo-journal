<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MergeTagRequest;
use App\Http\Requests\Admin\TagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class TagController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Tag::class);

        return view('admin.tags.index', [
            'tags' => Tag::query()->withCount('articles')->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.addcslashes($request->input('q'), '%_').'%'))->orderBy('name')->paginate(30)->withQueryString(),
            'allTags' => Tag::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Tag::class);

        return view('admin.tags.create');
    }

    public function store(TagRequest $request): RedirectResponse
    {
        Gate::authorize('create', Tag::class);
        $tag = Tag::query()->create($request->validated());

        return redirect()->route('admin.tags.edit', $tag)->with('success', 'Tag created.');
    }

    public function edit(Tag $tag): View
    {
        Gate::authorize('update', $tag);

        return view('admin.tags.edit', compact('tag'));
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        Gate::authorize('update', $tag);
        $tag->update($request->validated());

        return back()->with('success', 'Tag updated.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        Gate::authorize('delete', $tag);
        $tag->delete();

        return redirect()->route('admin.tags.index')->with('success', 'Tag removed.');
    }

    public function merge(MergeTagRequest $request, Tag $tag): RedirectResponse
    {
        Gate::authorize('delete', $tag);
        $target = Tag::query()->findOrFail($request->integer('target_tag_id'));
        if ($target->is($tag)) {
            throw ValidationException::withMessages(['target_tag_id' => 'Choose a different target tag.']);
        }
        Gate::authorize('update', $target);
        DB::transaction(function () use ($tag, $target): void {
            $target->articles()->syncWithoutDetaching($tag->articles()->pluck('articles.id'));
            $tag->articles()->detach();
            $tag->delete();
        });

        return redirect()->route('admin.tags.index')->with('success', "Merged into {$target->name}; article associations were preserved.");
    }
}
