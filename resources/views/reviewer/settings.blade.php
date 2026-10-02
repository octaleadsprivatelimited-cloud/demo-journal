@extends('layouts.portal')
@section('title','Reviewer settings')
@section('section','Peer review')
@section('page-title','Settings')
@section('page-description','Manage your availability for new reviews and your account security.')
@section('content')
<div class="portal-form" style="max-width:800px">
<div class="portal-card"><div class="portal-card-head"><h2>Review availability</h2></div><div class="portal-card-body">
<form class="portal-form" method="post" action="{{ route('reviewer.settings.update') }}">@csrf @method('put')
<div class="portal-field"><label for="available">Accepting new review invitations</label><select class="portal-select" id="available" name="available"><option value="1" @selected((string)old('available',(int)data_get($user->reviewer_profile,'available',true))==='1')>Available</option><option value="0" @selected((string)old('available',(int)data_get($user->reviewer_profile,'available',true))==='0')>Unavailable</option></select><p class="portal-help">Editors cannot assign new workflow reviews while you are unavailable. Existing assignments and deadlines remain in place.</p><x-portal.field-error name="available" /></div>
<button class="portal-button primary" type="submit">Save availability</button></form></div></div>
<div class="portal-card"><div class="portal-card-head"><h2>Account security</h2></div><div class="portal-card-body">
@if($user->is_local_admin_bypass)<p>You are using the local reviewer demo account. No password is needed on localhost; password changes are disabled for this temporary identity.</p>
@elseif(config('services.google.enabled') && config('services.google.only'))<p>Your account uses Google sign-in. Manage your password through your Google account.</p>
@else
<form class="portal-form" method="post" action="{{ route('reviewer.settings.password') }}">@csrf @method('put')
@foreach(['current_password'=>'Current password','password'=>'New password','password_confirmation'=>'Confirm new password'] as $field=>$label)
<div class="portal-field"><label for="{{ $field }}">{{ $label }}</label><input class="portal-input" type="password" id="{{ $field }}" name="{{ $field }}" autocomplete="{{ $field==='current_password'?'current-password':'new-password' }}" required><x-portal.field-error :name="$field" /></div>
@endforeach
<p class="portal-help">Use at least 12 characters, including uppercase and lowercase letters, a number, and a symbol.</p><button class="portal-button primary" type="submit">Update password</button></form>
@endif
</div></div></div>
@endsection
