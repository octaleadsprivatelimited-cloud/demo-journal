@extends('layouts.public')
@section('title','Archives')
@section('content')
<section class="section"><div class="container"><h1>Archives</h1>
@forelse($volumes->groupBy('year') as $publicationYear=>$items)
<section class="archive-year"><h2>{{ $publicationYear }}</h2><div class="archive-issues">@foreach($items as $volume)@foreach($volume->issues as $issue)<a href="{{ route('archive.issue',$issue) }}"><strong>Issue {{ $issue->number }}{{ $issue->title ? ' — '.$issue->title : '' }}</strong>@if($issue->description)<p>{{ $issue->description }}</p>@endif<small>Volume {{ $volume->number }} · {{ $issue->articles_count }} articles</small></a>@endforeach @endforeach</div></section>
@empty<p>No issues are available.</p>@endforelse
@foreach($articleYears as $articleYear=>$yearArticles)
<section class="archive-year"><h2>{{ $articleYear }} articles</h2><div class="archive-issues">@foreach($yearArticles as $article)<a href="{{ route('articles.show',$article->slug) }}">{{ $article->title }}</a>@endforeach</div></section>
@endforeach
<x-public.pagination :paginator="$volumes"/></div></section>
@endsection
