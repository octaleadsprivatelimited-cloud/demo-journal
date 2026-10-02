@extends('layouts.portal')
@section('title','Assign reviewers')
@section('section','Peer review')
@section('page-title','Assign reviewers')
@section('page-description','Choose a manuscript, select an available reviewer, and send an invitation.')
@section('content')
<div class="portal-card"><div class="portal-card-head"><h2>1. Choose a manuscript</h2></div><form class="filter-bar" method="get"><div class="portal-field search-field"><label for="q">Find a manuscript</label><input class="portal-input" id="q" name="q" value="{{ request('q') }}" placeholder="Search by title, author, or keyword"></div><button class="portal-button primary">Search</button><a class="portal-button" href="{{ route('admin.assign-reviewer.index') }}">Reset</a></form>
<div class="portal-card-body portal-form">@forelse($articles as $article)<div class="portal-card"><div class="portal-card-body"><h3>{{ $article->title }}</h3><p>{{ str($article->workflow?->stage ?? $article->status->value)->headline() }} · Editor: {{ $article->assignedEditor?->name ?? 'Not assigned' }}</p><a class="portal-button primary" href="{{ route('admin.assign-reviewer.show',$article) }}">{{ ($article->workflow ? in_array($article->workflow->stage,['reviewer_assignment','under_review','reviewer_recheck']) : in_array($article->status->value,['submitted','under_review'])) ? 'Choose reviewer →' : 'Complete checks →' }}</a></div></div>@empty<x-portal.empty title="No manuscripts awaiting review" message="Submitted manuscripts appear here. Try another search or open Articles to check a manuscript’s status." />@endforelse{{ $articles->links() }}</div></div>
@endsection
