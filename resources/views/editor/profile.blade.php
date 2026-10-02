@extends('layouts.portal')
@section('title', 'Editor profile')
@section('section', 'Editorial workspace')
@section('eyebrow', 'My account')
@section('page-title', 'Editor profile')
@section('page-description', 'Update your professional details and profile image.')
@section('content')
<div class="portal-card"><div class="portal-card-body">
    <form class="portal-form" method="post" action="{{ route('editor.profile.update') }}" enctype="multipart/form-data" data-loading>
        @csrf @method('put')
        <div class="form-grid">
            <x-portal.profile-image :path="$user->profile_image_path" :name="$user->name" />
            <div class="portal-field"><label for="name">Full name</label><input class="portal-input" id="name" name="name" value="{{ old('name', $user->name) }}" required><x-portal.field-error name="name" /></div>
            <div class="portal-field"><label for="organization">Organization</label><input class="portal-input" id="organization" name="organization" value="{{ old('organization', $user->organization) }}"><x-portal.field-error name="organization" /></div>
            <div class="portal-field"><label for="designation">Designation</label><input class="portal-input" id="designation" name="designation" value="{{ old('designation', $user->designation) }}"><x-portal.field-error name="designation" /></div>
            <div class="portal-field"><label for="phone">Phone</label><input class="portal-input" id="phone" name="phone" value="{{ old('phone', $user->phone) }}"><x-portal.field-error name="phone" /></div>
        </div>
        <div class="form-actions"><button class="portal-button primary" type="submit">Save profile</button></div>
    </form>
</div></div>
@endsection
