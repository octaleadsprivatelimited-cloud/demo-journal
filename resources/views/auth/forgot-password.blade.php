@extends('layouts.portal')
@section('title', 'Reset password')
@section('page-title', 'Recover your account')
@section('page-description', 'Enter the email address registered with your author, reviewer, editor or administrator account. We will email you a secure link to choose a new password.')
@section('content')
<p class="portal-help">Check your inbox and spam folder. Reset links expire after {{ config('auth.passwords.users.expire', 60) }} minutes and can be used once.</p>
<form class="portal-form" method="post" action="{{ route('password.email') }}" data-loading>@csrf
    <div class="portal-field"><label for="email">Email address</label><input class="portal-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus><x-portal.field-error name="email" /></div>
    <button class="portal-button primary" type="submit" data-loading-text="Sending…">Send reset link</button><div class="auth-links"><a href="{{ route('login') }}">← Back to sign in</a></div>
</form>
@endsection
