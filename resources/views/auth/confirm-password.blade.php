@extends('layouts.portal')
@section('title', 'Confirm password')
@section('page-title', 'Confirm this sensitive action')
@section('page-description', 'Re-enter your password to continue. This protects editorial decisions and security settings.')
@section('content')
<div class="portal-card"><div class="portal-card-body"><form class="portal-form" method="post" action="{{ route('password.confirm.store') }}" data-loading>@csrf
    <div class="portal-field"><label for="password">Current password</label><input class="portal-input" id="password" name="password" type="password" autocomplete="current-password" required autofocus><x-portal.field-error name="password" /></div><button class="portal-button primary" type="submit">Confirm password</button>
</form></div></div>
@endsection
