@extends('layouts.portal')
@section('title', 'Choose a workspace')
@section('auth-eyebrow', 'Role-based access')
@section('page-title', 'Choose your workspace')
@section('page-description', 'Use the secure sign-in page assigned to your publication role.')
@section('content')
<div class="choice-grid">
    <a class="choice-card" style="display:block" href="{{ route('author.login') }}"><strong>Author</strong><small style="display:block;color:var(--portal-muted);margin-top:.35rem">Write, submit, and track manuscripts.</small></a>
    <a class="choice-card" style="display:block" href="{{ route('editor.login') }}"><strong>Editor</strong><small style="display:block;color:var(--portal-muted);margin-top:.35rem">Manage editorial review and publishing.</small></a>
    <a class="choice-card" style="display:block" href="{{ route('reviewer.login') }}"><strong>Reviewer</strong><small style="display:block;color:var(--portal-muted);margin-top:.35rem">Open assigned peer reviews securely.</small></a>
    <a class="choice-card" style="display:block" href="{{ route('admin.login') }}"><strong>Administrator</strong><small style="display:block;color:var(--portal-muted);margin-top:.35rem">Admin and super-admin access.</small></a>
</div>
<div class="auth-links" style="margin-top:1.2rem"><span>Need an account? <a href="{{ route('register') }}">View registration options</a></span><a href="{{ route('home') }}">Browse the journal</a></div>
@endsection
