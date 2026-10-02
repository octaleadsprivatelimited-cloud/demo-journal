@extends('layouts.portal')
@section('title','My account')
@section('page-title','My account')
@section('page-description','Change your administrator sign-in email or password. Public website contact details are managed separately in Site settings.')
@section('content')
<div class="portal-card"><div class="portal-card-body">
@if($account->is_local_admin_bypass)
<p>You are using localhost demo access. Sign in with a real administrator account to change login credentials.</p>
@else
<form class="portal-form" method="post" action="{{ route('admin.account.update') }}" data-loading>
@csrf @method('put')
<div class="portal-field"><label for="email">Sign-in email address</label><input class="portal-input" id="email" name="email" type="email" value="{{ old('email',$account->email) }}" autocomplete="username" required><x-portal.field-error name="email" /></div>
<div class="portal-field"><label for="current_password">Current password</label><input class="portal-input" id="current_password" name="current_password" type="password" autocomplete="current-password" required><x-portal.field-error name="current_password" /></div>
<div class="portal-field"><label for="password">New password (optional)</label><input class="portal-input" id="password" name="password" type="password" autocomplete="new-password"><p class="portal-help">Leave blank to keep your password. Use at least 9 characters, including uppercase, lowercase, a number and a symbol.</p><x-portal.field-error name="password" /></div>
<div class="portal-field"><label for="password_confirmation">Confirm new password</label><input class="portal-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"></div>
<button class="portal-button primary" type="submit">Save account settings</button>
</form>
@endif
</div></div>
@endsection
