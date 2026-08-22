@extends('layouts.portal')
@section('title', 'Sign in')
@section('page-title', 'Welcome back')
@section('page-description', 'Access your author, reviewer, or editorial workspace securely.')
@section('content')
<form class="portal-form" method="post" action="{{ route('login.store') }}" data-loading>@csrf
    <div class="portal-field"><label for="email">Email address</label><input class="portal-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus aria-describedby="email-error"><x-portal.field-error name="email" /></div>
    <div class="portal-field"><div style="display:flex;justify-content:space-between"><label for="password">Password</label><a href="{{ route('password.request') }}" style="font-size:.72rem;color:var(--portal-green-2);font-weight:700">Forgot password?</a></div><input class="portal-input" id="password" name="password" type="password" autocomplete="current-password" required aria-describedby="password-error"><x-portal.field-error name="password" /></div>
    <div class="check-row"><input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))><label for="remember">Keep me signed in on this trusted device</label></div>
    <button class="portal-button primary" type="submit" data-loading-text="Signing in…">Sign in securely <x-portal.icon name="arrow" :size="17" /></button>
    <div class="auth-links"><span>New contributor? <a href="{{ route('register') }}">Create an author account</a></span><a href="{{ route('home') }}">Browse the journal</a></div>
</form>
@endsection
