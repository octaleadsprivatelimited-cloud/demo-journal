@extends('layouts.portal')
@section('title', 'Choose an application')
@section('auth-eyebrow', 'Registration')
@section('page-title', 'Choose an account type')
@section('page-description', 'Every application is reviewed before workspace access is granted.')
@section('content')
<div class="choice-grid">
    <a class="choice-card" style="display:block" href="{{ route('author.register') }}"><strong>Author application</strong><small style="display:block;color:var(--portal-muted);margin-top:.35rem">Create a contributor profile and propose manuscripts.</small></a>
    <a class="choice-card" style="display:block" href="{{ route('editor.register') }}"><strong>Editor application</strong><small style="display:block;color:var(--portal-muted);margin-top:.35rem">Request editorial workflow access.</small></a>
    <a class="choice-card" style="display:block" href="{{ route('admin.register') }}"><strong>Admin application</strong><small style="display:block;color:var(--portal-muted);margin-top:.35rem">Request operational administration access.</small></a>
</div>
<div class="auth-links" style="margin-top:1.2rem"><span>Already registered? <a href="{{ route('login') }}">Choose a sign-in page</a></span></div>
@endsection
