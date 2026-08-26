@extends('layouts.public')

@section('title', $title)
@section('meta_description', $content ? Str::limit(strip_tags($content), 155) : $title.' — client content required.')

@section('content')
<main class="section">
    <div class="container container-reading">
        <x-public.breadcrumbs :items="['Home' => route('home'), $title => null]" />
        <header class="page-heading"><p class="eyebrow">Journal policy</p><h1>{{ $title }}</h1></header>
        <article class="prose article-body">
            @if(filled($content))
                {!! nl2br(e($content)) !!}
            @else
                <div class="empty-state" role="status"><h2>Client input required</h2><p>This policy has not yet been supplied. An authorised administrator can publish verified wording from Settings.</p></div>
            @endif
        </article>
        @if($page === 'indexing' && $indexingServices->isNotEmpty())
            <section class="article-grid article-grid-three">
                @foreach($indexingServices as $service)
                    <article class="category-card">
                        <h2>{{ $service->name }}</h2>
                        @if($service->description)
                            <p>{{ $service->description }}</p>
                        @endif
                        @if($service->official_url)
                            <a href="{{ $service->official_url }}" rel="external noopener">Official source</a>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif
    </div>
</main>
@endsection
