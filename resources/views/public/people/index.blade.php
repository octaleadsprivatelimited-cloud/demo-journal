@extends('layouts.public')
@section('title', 'Member profiles')
@section('description', 'Meet the members of the journal community and explore their public profiles.')
@section('content')
<section class="page-hero"><div class="container">
    <x-public.breadcrumbs :items="['Contributors' => route('authors.index'), 'Member profiles' => null]" />
    <div class="page-hero-grid"><div><p class="eyebrow">Journal community</p><h1>Member profiles</h1></div><p>Meet the people contributing to our journal.</p></div>
</div></section>
<section class="section"><div class="container">
    <form class="directory-search" action="{{ route('people.index') }}" method="get" role="search">
        <label for="member-search">Find a member</label>
        <div class="input-with-icon"><x-public.icon name="search" /><input id="member-search" name="q" type="search" value="{{ $term }}" placeholder="Search by name or institution"><button class="button button-primary" type="submit">Search</button></div>
    </form>
    @if($people->isNotEmpty())
        <div class="author-directory">@foreach($people as $person)<x-public.member-card :person="$person" />@endforeach</div>
        <x-public.pagination :paginator="$people" />
    @else
        <x-public.empty-state title="No member profiles found" message="Members appear here once their profile photo is published." :action="route('authors.index')" action-label="Browse contributors" />
    @endif
</div></section>
@endsection
