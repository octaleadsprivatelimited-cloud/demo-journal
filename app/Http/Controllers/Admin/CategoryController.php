<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class CategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Category::class);

        return view('admin.categories.index', ['categories' => Category::query()->withCount('articles')->with('parent:id,name')->orderBy('sort_order')->orderBy('name')->paginate(25)]);
    }

    public function create(): View
    {
        Gate::authorize('create', Category::class);

        return view('admin.categories.create', ['parents' => Category::query()->roots()->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Category::class);
        $data = $request->safe()->except('image');
        $data['is_active'] = $request->boolean('is_active');
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('categories', 'public');
        }
        $category = Category::query()->create($data);

        return redirect()->route('admin.categories.edit', $category)->with('success', 'Category created.');
    }

    public function edit(Category $category): View
    {
        Gate::authorize('update', $category);

        return view('admin.categories.edit', ['category' => $category, 'parents' => Category::query()->whereKeyNot($category->getKey())->roots()->orderBy('name')->get(['id', 'name'])]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);
        $data = $request->safe()->except('image');
        $data['is_active'] = $request->boolean('is_active');
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('categories', 'public');
        }
        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);
        abort_if($category->children()->exists(), 409, 'Move or remove child categories first.');
        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category moved to trash; its articles remain available.');
    }
}
