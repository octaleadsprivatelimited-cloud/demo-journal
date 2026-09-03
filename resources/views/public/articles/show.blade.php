@php
    $seo = $article->seoMetadata;
    $publishedAt = $article->published_at ?? $article->created_at;
    $readingTime = $article->reading_time_minutes ?: max(1, (int) ceil(str_word_count(strip_tags((string) $article->content)) / 220));
    $imageUrl = $article->featured_image_path
        ? (Illuminate\Support\Str::startsWith($article->featured_image_path, ['http://', 'https://']) ? $article->featured_image_path : Illuminate\Support\Facades\Storage::disk(config('publication.uploads.disk', 'public'))->url($article->featured_image_path))
        : asset('assets/editorial-placeholder.svg');
    $structuredData = array_filter([
        '@context' => 'https://schema.org',
        '@type' => $seo?->schema_type ?: 'ScholarlyArticle',
        'headline' => $seo?->og_title ?: $article->title,
        'description' => $seo?->meta_description ?: $article->excerpt ?: $article->abstract,
        'image' => [$imageUrl],
        'datePublished' => optional($publishedAt)->toIso8601String(),
        'dateModified' => optional($article->updated_at)->toIso8601String(),
        'mainEntityOfPage' => route('articles.show', $article->slug),
        'author' => $article->authors->map(fn($author) => ['@type' => 'Person', 'name' => $author->name, 'url' => route('authors.show', $author->slug)])->values()->all(),
        'publisher' => ['@type' => 'Organization', 'name' => data_get($site, 'name'), 'url' => route('home')],
        'articleSection' => $article->category?->name,
        'keywords' => collect($article->keywords)->merge($article->tags->pluck('name'))->filter()->join(', '),
        'identifier' => $article->doi ? 'https://doi.org/'.$article->doi : $article->public_id,
    ]);
@endphp
@extends('layouts.public')

@section('title', $seo?->seo_title ?: $article->title)
@section('description', $seo?->meta_description ?: $article->excerpt ?: $article->abstract ?: Illuminate\Support\Str::limit(strip_tags($article->content), 155))
@section('canonical', $seo?->canonical_url ?: route('articles.show', $article->slug))
@section('image', $seo?->og_image ?: $imageUrl)
@section('og_title', $seo?->og_title ?: $article->title)
@section('og_description', $seo?->og_description ?: $article->excerpt ?: $article->abstract)
@section('og_type', 'article')

@push('head')
    <meta property="article:published_time" content="{{ optional($publishedAt)->toIso8601String() }}">
    <meta property="article:modified_time" content="{{ optional($article->updated_at)->toIso8601String() }}">
    @if($article->category)<meta property="article:section" content="{{ $article->category->name }}">@endif
    @foreach($article->tags as $tag)<meta property="article:tag" content="{{ $tag->name }}">@endforeach
@endpush

@section('content')
    <article class="article-page" data-reading-article>
        <header class="article-header">
            <div class="container container-reading-wide">
                <x-public.breadcrumbs :items="[
                    'Articles' => route('articles.index'),
                    $article->category?->name ?? 'Journal' => $article->category ? route('categories.show', $article->category->slug) : null,
                    $article->title => null,
                ]" />
                <div class="article-header-grid">
                    <div class="article-header-main">
                        <div class="article-kicker">
                            @if($article->category)<a href="{{ route('categories.show', $article->category->slug) }}">{{ $article->category->name }}</a>@endif
                            @if($article->publication_type)<span>{{ Str::headline($article->publication_type) }}</span>@endif
                        </div>
                        <h1>{{ $article->title }}</h1>
                        @if($article->subtitle)<p class="article-dek">{{ $article->subtitle }}</p>@endif
                        @if($article->authors->isNotEmpty())
                            <div class="article-byline">
                                <div class="byline-avatars">
                                    @foreach($article->authors->take(3) as $author)
                                        @php($avatar = $author->avatar_path ? (Illuminate\Support\Str::startsWith($author->avatar_path, ['http://', 'https://']) ? $author->avatar_path : Illuminate\Support\Facades\Storage::disk(config('publication.uploads.disk', 'public'))->url($author->avatar_path)) : null)
                                        <span>@if($avatar)<img src="{{ $avatar }}" alt="" width="48" height="48">@else{{ mb_substr($author->name, 0, 1) }}@endif</span>
                                    @endforeach
                                </div>
                                <div>
                                    <p>By {!! $article->authors->map(fn($author) => '<a href="'.e(route('authors.show', $author->slug)).'">'.e($author->name).'</a>')->implode(', ') !!}</p>
                                    <div class="article-dates">
                                        <time datetime="{{ optional($publishedAt)->toDateString() }}">Published {{ optional($publishedAt)->format('F j, Y') }}</time>
                                        @if($article->updated_at && $publishedAt && $article->updated_at->gt($publishedAt->copy()->addDay()))<time datetime="{{ $article->updated_at->toDateString() }}">Updated {{ $article->updated_at->format('F j, Y') }}</time>@endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="article-header-aside">
                        <dl>
                            <div><dt>Reading time</dt><dd>{{ $readingTime }} minutes</dd></div>
                            @if($article->doi)<div><dt>DOI</dt><dd><a href="https://doi.org/{{ $article->doi }}" rel="external noopener">{{ $article->doi }}</a></dd></div>@endif
                            <div><dt>Article views</dt><dd>{{ number_format($article->view_count) }}</dd></div>
                        </dl>
                    </div>
                </div>
            </div>
        </header>

        <div class="article-hero-image container container-reading-wide">
            <img src="{{ $imageUrl }}" alt="{{ $article->title }}" width="1440" height="810" fetchpriority="high">
        </div>

        <div class="container article-reading-grid">
            <aside class="article-tools" aria-label="Article tools">
                <div class="sticky-tools">
                    <p>Share</p>
                    <button type="button" data-share-native data-share-title="{{ $article->title }}" data-share-url="{{ route('articles.show', $article->slug) }}" aria-label="Share this article"><x-public.icon name="share" /></button>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('articles.show', $article->slug)) }}" target="_blank" rel="noopener" aria-label="Share on LinkedIn">in</a>
                    <a href="https://x.com/intent/post?url={{ urlencode(route('articles.show', $article->slug)) }}&text={{ urlencode($article->title) }}" target="_blank" rel="noopener" aria-label="Share on X">X</a>
                    <button type="button" data-copy-url="{{ route('articles.show', $article->slug) }}" aria-label="Copy article link">↗</button>
                    <span class="tools-rule"></span>
                    <a href="{{ route('articles.print', $article->slug) }}" target="_blank" aria-label="Print article"><x-public.icon name="print" /></a>
                    @if(data_get($site, 'features.pdf_downloads', true) && $article->pdf_download_enabled && $article->pdf_path)<a href="{{ route('articles.pdf', $article->slug) }}" aria-label="Download PDF"><x-public.icon name="download" /></a>@endif
                </div>
            </aside>

            <div class="article-body-column">
                @if($article->abstract)
                    <section class="article-abstract" aria-labelledby="abstract-heading">
                        <p class="eyebrow" id="abstract-heading">Abstract</p>
                        <p>{{ $article->abstract }}</p>
                    </section>
                @endif

                <div class="article-mobile-tools">
                    <button class="button button-outline" type="button" data-share-native data-share-title="{{ $article->title }}" data-share-url="{{ route('articles.show', $article->slug) }}"><x-public.icon name="share" /> Share</button>
                    <a class="button button-outline" href="{{ route('articles.print', $article->slug) }}" target="_blank"><x-public.icon name="print" /> Print</a>
                    @if(data_get($site, 'features.pdf_downloads', true) && $article->pdf_download_enabled && $article->pdf_path)<a class="button button-outline" href="{{ route('articles.pdf', $article->slug) }}"><x-public.icon name="download" /> PDF</a>@endif
                </div>

                <div class="article-content" data-article-content>
                    {!! $article->content !!}
                </div>

                @if(collect($article->references)->filter()->isNotEmpty())
                    <section class="references" id="references">
                        <h2>References</h2>
                        <ol>
                            @foreach($article->references as $reference)
                                <li>{{ is_array($reference) ? ($reference['citation'] ?? $reference['title'] ?? collect($reference)->filter()->join('. ')) : $reference }}</li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                @if($article->tags->isNotEmpty())
                    <div class="article-tags" aria-label="Article topics"><span>Topics</span>@foreach($article->tags as $tag)<a href="{{ route('articles.index', ['tag' => $tag->slug]) }}">{{ $tag->name }}</a>@endforeach</div>
                @endif

                <section class="citation-box" id="cite-this-article">
                    <div><p class="eyebrow">Citation</p><h2>Cite this article</h2></div>
                    @php($citation = $article->authors->pluck('name')->join(', ').'. “'.$article->title.'.” '.data_get($site, 'name').', '.optional($publishedAt)->format('Y').'.'.($article->doi ? ' https://doi.org/'.$article->doi : ' '.route('articles.show', $article->slug)))
                    <p data-citation-text>{{ $citation }}</p>
                    <button class="text-link" type="button" data-copy-citation>Copy citation <x-public.icon name="arrow-up-right" /></button>
                </section>
            </div>

            <aside class="article-toc" aria-label="On this page">
                <div class="toc-inner" data-toc-wrapper hidden>
                    <p>On this page</p>
                    <ol data-toc></ol>
                </div>
            </aside>
        </div>

        @if($article->authors->isNotEmpty())
            <section class="article-authors-section">
                <div class="container container-reading">
                    <p class="eyebrow">About the {{ Str::plural('contributor', $article->authors->count()) }}</p>
                    @foreach($article->authors as $author)
                        <div class="article-author-bio">
                            <x-public.author-card :author="$author" />
                            @if($author->biography)<p>{{ $author->biography }}</p>@endif
                            <a class="text-link" href="{{ route('authors.show', $author->slug) }}">More from {{ $author->name }} <x-public.icon name="arrow-right" /></a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if(data_get($site, 'features.comments', true) && $article->comments_enabled)
            <section class="discussion-section" id="discussion" aria-labelledby="discussion-heading">
                <div class="container container-reading-wide discussion-grid">
                    <div class="discussion-list">
                        <div class="discussion-heading">
                            <div><p class="eyebrow">Reader discussion</p><h2 id="discussion-heading">Join the conversation</h2></div>
                            <span>{{ $comments->sum(fn($comment) => 1 + $comment->replies->count()) }} approved {{ Str::plural('comment', $comments->sum(fn($comment) => 1 + $comment->replies->count())) }}</span>
                        </div>
                        @forelse($comments as $comment)
                            @php($commentName = $comment->user?->name ?: $comment->guest_name ?: 'Journal reader')
                            <article class="comment">
                                <div class="comment-avatar" aria-hidden="true">{{ mb_substr($commentName, 0, 1) }}</div>
                                <div>
                                    <header><strong>{{ $commentName }}</strong><time datetime="{{ $comment->approved_at?->toIso8601String() ?? $comment->created_at->toIso8601String() }}">{{ ($comment->approved_at ?? $comment->created_at)->diffForHumans() }}</time></header>
                                    <p>{!! nl2br(e($comment->body)) !!}</p>
                                    @foreach($comment->replies as $reply)
                                        @php($replyName = $reply->user?->name ?: $reply->guest_name ?: 'Editorial team')
                                        <article class="comment comment-reply">
                                            <div class="comment-avatar" aria-hidden="true">{{ mb_substr($replyName, 0, 1) }}</div>
                                            <div><header><strong>{{ $replyName }}</strong><time datetime="{{ $reply->approved_at?->toIso8601String() ?? $reply->created_at->toIso8601String() }}">{{ ($reply->approved_at ?? $reply->created_at)->diffForHumans() }}</time></header><p>{!! nl2br(e($reply->body)) !!}</p></div>
                                        </article>
                                    @endforeach
                                </div>
                            </article>
                        @empty
                            <div class="discussion-empty"><p>No approved comments yet. Add a considered response to begin the discussion.</p></div>
                        @endforelse
                    </div>
                    <aside class="comment-form-card">
                        <p class="eyebrow">Your perspective</p>
                        <h2>Leave a comment</h2>
                        <p>Comments are reviewed for relevance, civility, and substance before publication.</p>
                        @if($errors->hasAny(['guest_name', 'guest_email', 'body', 'comment_website']))
                            <div class="form-errors" role="alert"><strong>Please review your comment.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                        @endif
                        <form action="{{ route('comments.store', $article->slug) }}" method="post" class="comment-form" data-submitting-form>
                            @csrf
                            <div class="honeypot" aria-hidden="true"><label>Website<input type="text" name="comment_website" tabindex="-1" autocomplete="off"></label></div>
                            @guest
                                <div class="form-row"><div><label for="comment-name">Name</label><input id="comment-name" name="guest_name" value="{{ old('guest_name') }}" required autocomplete="name" maxlength="120">@error('guest_name')<small class="field-error">{{ $message }}</small>@enderror</div><div><label for="comment-email">Email <span>(never published)</span></label><input id="comment-email" type="email" name="guest_email" value="{{ old('guest_email') }}" required autocomplete="email" maxlength="254">@error('guest_email')<small class="field-error">{{ $message }}</small>@enderror</div></div>
                            @else
                                <p class="commenting-as">Commenting as <strong>{{ auth()->user()->name }}</strong>.</p>
                            @endguest
                            <div><label for="comment-body">Comment</label><textarea id="comment-body" name="body" rows="6" minlength="10" maxlength="5000" required>{{ old('body') }}</textarea>@error('body')<small class="field-error">{{ $message }}</small>@enderror</div>
                            <div class="comment-form-footer"><small>Keep responses focused on the article and its ideas.</small><button class="button button-primary" type="submit">Submit for review <x-public.icon name="arrow-right" /></button></div>
                        </form>
                    </aside>
                </div>
            </section>
        @endif

        @if($previous || $next)
            <nav class="article-pagination container" aria-label="Adjacent articles">
                @if($previous)<a href="{{ route('articles.show', $previous->slug) }}"><span><x-public.icon name="arrow-left" /> Previous</span><strong>{{ $previous->title }}</strong></a>@else<span></span>@endif
                @if($next)<a class="next" href="{{ route('articles.show', $next->slug) }}"><span>Next <x-public.icon name="arrow-right" /></span><strong>{{ $next->title }}</strong></a>@endif
            </nav>
        @endif

        @if($related->isNotEmpty())
            <section class="section related-section">
                <div class="container">
                    <div class="section-heading"><div><p class="eyebrow">Continue reading</p><h2>Related perspectives</h2></div></div>
                    <div class="article-grid article-grid-three">@foreach($related as $item)<x-public.article-card :article="$item" />@endforeach</div>
                </div>
            </section>
        @endif
    </article>
@endsection
