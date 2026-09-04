@extends('layouts.portal')
@section('title', 'Choose a new password')
@section('page-title', 'Set a new password')
@section('page-description', 'Use a unique password you have not used on another service.')
@section('content')
<form class="portal-form" method="post" action="{{ route('password.store') }}" data-loading>@csrf<input type="hidden" name="token" value="{{ $token }}">
    <div class="portal-field"><label for="email">Email address</label><input class="portal-input" id="email" name="email" type="email" value="{{ old('email', $email) }}" required readonly><x-portal.field-error name="email" /></div>
    <div class="portal-field"><label for="password">New password</label><input class="portal-input" id="password" name="password" type="password" autocomplete="new-password" minlength="9" required><p class="portal-help">At least 9 characters with uppercase and lowercase letters, a number, and a symbol. Passwords are case-sensitive.</p><x-portal.field-error name="password" /></div>
    <div class="portal-field"><label for="password_confirmation">Confirm new password</label><input class="portal-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
    <button class="portal-button primary" type="submit" data-loading-text="Resetting…">Reset password</button>
</form>
@endsection
