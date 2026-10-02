@extends('layouts.public')
@section('title','Journal policies & guidance')
@section('description','Find journal information, author instructions, peer review guidance, publication policies and archives.')
@section('content')
<section class="section journal-information"><div class="container"><x-public.breadcrumbs :items="['Policies & guidance'=>null]" /><header class="page-heading"><p class="eyebrow">Journal information</p><h1>Policies & guidance</h1><p>Find what you need before submitting, reviewing or reading research.</p></header>
<form class="journal-directory-search" method="get"><div><label for="guide-search">Search pages</label><input id="guide-search" name="q" value="{{ $q }}" placeholder="Search policies or guidance"></div><div><label for="guide-group">Section</label><select id="guide-group" name="group"><option value="">All sections</option>@foreach(\App\Services\JournalPages::groups() as $key=>$label)<option value="{{ $key }}" @selected($group===$key)>{{ $label }}</option>@endforeach</select></div><button class="button button-primary">Search</button><a href="{{ route('policies.index') }}">Clear</a></form>
<div class="journal-directory-grid">@foreach(\App\Services\JournalPages::groups() as $key=>$label)@php($items=$pages->where('group',$key))@if($items->isNotEmpty())<section class="journal-directory-card"><h2>{{ $label }}</h2><ul>@foreach($items as $slug=>$item)<li><a href="{{ \App\Services\JournalPages::url($slug) }}">{{ $item['title'] }}</a></li>@endforeach</ul></section>@endif @endforeach</div>@if($pages->isEmpty())<p>No pages match your search. Try another term or clear the filters.</p>@endif
</div></section>
@endsection
