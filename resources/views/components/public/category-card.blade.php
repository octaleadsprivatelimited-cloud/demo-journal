@props(['category', 'index' => 1])
@php
    $image = $category->image_path
        ? (Illuminate\Support\Str::startsWith($category->image_path, ['http://', 'https://']) ? $category->image_path : Illuminate\Support\Facades\Storage::disk(config('publication.uploads.disk', 'public'))->url($category->image_path))
        : null;
@endphp
<a {{ $attributes->class(['category-card']) }} href="{{ route('categories.show', $category->slug) }}">
    <span class="category-number">{{ str_pad((string) $index, 2, '0', STR_PAD_LEFT) }}</span>
    @if($image)<img src="{{ $image }}" alt="" width="96" height="96" loading="lazy">@endif
    <span class="category-card-copy">
        <strong>{{ $category->name }}</strong>
        @if($category->description)<small>{{ Illuminate\Support\Str::limit($category->description, 100) }}</small>@endif
        @isset($category->published_articles_count)
            <em>{{ trans_choice(':count article|:count articles', $category->published_articles_count, ['count' => $category->published_articles_count]) }}</em>
        @endisset
    </span>
    <x-public.icon name="arrow-up-right" />
</a>
