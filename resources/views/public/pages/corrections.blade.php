@extends('layouts.public')
@section('title','Corrections & retractions archive')
@section('description','Published corrections, retractions and expressions of concern linked to journal articles.')
@section('content')
<section class="section journal-information"><div class="container container-reading"><x-public.breadcrumbs :items="['Policies & guidance'=>route('policies.index'),'Publication notices'=>null]" /><header class="page-heading"><p class="eyebrow">Publication record</p><h1>Corrections & retractions</h1><p>Notices remain linked to the affected published article.</p></header>
<form class="journal-directory-search" method="get"><div><label for="notice-search">Article title</label><input id="notice-search" name="q" value="{{ $q }}"></div><div><label for="notice-type">Notice type</label><select id="notice-type" name="type"><option value="">All notices</option>@foreach(['correction'=>'Correction','retraction'=>'Retraction','expression_of_concern'=>'Expression of concern'] as $key=>$label)<option value="{{ $key }}" @selected($type===$key)>{{ $label }}</option>@endforeach</select></div><button class="button button-primary">Filter</button></form>
@forelse($notices as $article)<article class="journal-directory-card"><p class="eyebrow">{{ str($article->publication_notice)->headline() }}</p><h2><a href="{{ route('articles.show',$article->slug) }}">{{ $article->title }}</a></h2><p>{{ $article->excerpt }}</p><a href="{{ route('articles.show',$article->slug) }}">Read article and notice →</a></article>@empty<p>No published notices match these filters.</p>@endforelse
{{ $notices->links() }}
</div></section>
@endsection
