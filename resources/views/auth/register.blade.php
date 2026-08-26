@extends('layouts.portal')
@section('title', 'Create your account')
@section('auth-eyebrow', 'Welcome to Octaleads Journal')
@section('page-title', 'Find your role')
@section('page-description', 'One thoughtful step is all it takes to get started.')
@section('content')
<style>
    .auth-card:has(.registration-choices){width:min(100%,820px)}
    .auth-card:has(.registration-choices) h1{font-size:clamp(1.7rem,3vw,2.2rem)}
</style>
<div class="registration-choices">
    <div class="registration-intro"><span>Choose an account</span><small>You can sign in after your account is approved.</small></div>
    <div class="registration-grid">
    @if($authorRegistrationEnabled)
        <a class="registration-role role-author" href="{{ route('author.register') }}"><span class="registration-role-icon"><x-portal.icon name="article" :size="21" /></span><span class="registration-role-copy"><strong>Author</strong><small>Share your research and submit manuscripts.</small></span><x-portal.icon class="registration-arrow" name="arrow" :size="18" /></a>
    @else
        <div class="registration-role is-disabled" aria-disabled="true"><span class="registration-role-icon"><x-portal.icon name="article" :size="21" /></span><span class="registration-role-copy"><strong>Author</strong><small>Author applications are closed.</small></span></div>
    @endif
    <a class="registration-role role-editor" href="{{ route('editor.register') }}"><span class="registration-role-icon"><x-portal.icon name="users" :size="21" /></span><span class="registration-role-copy"><strong>Editor</strong><small>Guide editorial workflow and decisions.</small></span><x-portal.icon class="registration-arrow" name="arrow" :size="18" /></a>
    <a class="registration-role role-reviewer" href="{{ route('reviewer.register') }}"><span class="registration-role-icon"><x-portal.icon name="review" :size="21" /></span><span class="registration-role-copy"><strong>Reviewer</strong><small>Support fair, rigorous peer review.</small></span><x-portal.icon class="registration-arrow" name="arrow" :size="18" /></a>
    <a class="registration-role role-author" href="{{ route('contributor.register') }}"><span class="registration-role-icon"><x-portal.icon name="article" :size="21" /></span><span class="registration-role-copy"><strong>Contributor</strong><small>Create and submit journal content.</small></span><x-portal.icon class="registration-arrow" name="arrow" :size="18" /></a>
    <a class="registration-role role-editor" href="{{ route('admin.register') }}"><span class="registration-role-icon"><x-portal.icon name="users" :size="21" /></span><span class="registration-role-copy"><strong>Administrator</strong><small>Request operational access.</small></span><x-portal.icon class="registration-arrow" name="arrow" :size="18" /></a>
</div>
</div>
<div class="registration-signin"><span>Already have an account?</span><a href="{{ route('login') }}">Sign in <x-portal.icon name="arrow" :size="15" /></a></div>
@endsection
