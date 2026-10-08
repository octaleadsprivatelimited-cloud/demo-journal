@extends('layouts.public')
@section('title', $user->name)
@section('description', 'View the public profile and publications of '.$user->name.'.')
@section('canonical', route('people.show', $user))
@section('image', $user->profile_image_url)
@section('content')
<section class="profile-hero profile-hero-compact"><div class="container">
    <x-public.breadcrumbs :items="['Member profiles' => route('people.index'), $user->name => null]" />
    <div class="profile-grid">
        <div class="profile-portrait"><img src="{{ $user->profile_image_url }}" alt="Portrait of {{ $user->name }}" width="520" height="620"></div>
        <div class="profile-copy"><p class="eyebrow">Member profile</p><h1>{{ $user->name }}</h1>
            @if($user->designation || $user->organization)<p class="profile-role">{{ collect([$user->designation, $user->organization])->filter()->join(' · ') }}</p>@endif
        </div>
    </div>
</div></section>
@if($articles->isNotEmpty())
<section class="section"><div class="container">
    <div class="section-heading"><h2>Publications by {{ $user->name }}</h2></div>
    <div class="article-grid article-grid-two">@foreach($articles as $article)<x-public.article-card :article="$article" layout="horizontal" />@endforeach</div>
    <x-public.pagination :paginator="$articles" />
</div></section>
@endif
@endsection
