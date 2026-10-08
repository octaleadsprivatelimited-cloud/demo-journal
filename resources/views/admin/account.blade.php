@extends('layouts.portal')
@section('title','My account')
@section('page-title','My account')
@section('page-description','Manage your public profile, photo and administrator sign-in details.')
@section('content')
@unless($account->is_local_admin_bypass)
<div class="portal-card"><div class="portal-card-head"><h2>Public profile</h2></div><div class="portal-card-body">
<form class="portal-form" method="post" enctype="multipart/form-data" action="{{ route('admin.account.profile.update') }}" data-loading>
@csrf @method('put')
<div class="form-grid">
<x-portal.profile-image :path="$account->profile_image_path" :name="$account->name" />
@foreach(['name'=>'Full name','organization'=>'Organization','designation'=>'Designation'] as $field=>$label)
<div class="portal-field"><label for="{{ $field }}">{{ $label }}</label><input class="portal-input" id="{{ $field }}" name="{{ $field }}" value="{{ old($field,$account->$field) }}" @required($field==='name')><x-portal.field-error :name="$field" /></div>
@endforeach
</div><button class="portal-button primary" type="submit">Save profile</button>
</form></div></div>
@endunless
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
