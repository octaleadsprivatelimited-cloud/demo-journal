@extends('layouts.portal')
@section('title', 'Create author account')
@section('auth-eyebrow', 'Contributor registration')
@section('page-title', 'Create author account')
@section('page-description', 'Create your contributor profile for review by the publication team.')
@section('content')
<style>.auth-card{width:min(100%,760px)}</style>
<form class="portal-form" method="post" action="{{ route('author.register.store') }}" enctype="multipart/form-data" data-loading>@csrf
    <div class="form-grid">
        <div class="portal-field"><label for="name">Full name</label><input class="portal-input" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required><x-portal.field-error name="name" /></div>
        <div class="portal-field"><label for="email">Email address</label><input class="portal-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required><x-portal.field-error name="email" /></div>
        <div class="portal-field"><label for="phone">Phone <span>optional</span></label><input class="portal-input" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel"><x-portal.field-error name="phone" /></div>
        <div class="portal-field"><label for="organization">Institution / organization</label><input class="portal-input" id="organization" name="organization" value="{{ old('organization') }}" autocomplete="organization"><x-portal.field-error name="organization" /></div>
        <div class="portal-field"><label for="designation">Designation</label><input class="portal-input" id="designation" name="designation" value="{{ old('designation') }}" placeholder="Research Fellow, Professor…"><x-portal.field-error name="designation" /></div>
        <div class="portal-field"><label for="profile_image">Profile image <span>JPG, PNG or WebP · 4 MB max</span></label><input class="portal-input" id="profile_image" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp"><x-portal.field-error name="profile_image" /></div>
        <div class="portal-field field-span"><label for="biography">Short biography <span>optional</span></label><textarea class="portal-textarea" id="biography" name="biography" rows="3">{{ old('biography') }}</textarea><x-portal.field-error name="biography" /></div>
        <div class="portal-field"><label for="password">Password</label><input class="portal-input" id="password" name="password" type="password" autocomplete="new-password" required><p class="portal-help">At least 10 characters with upper/lowercase, a number and symbol.</p><x-portal.field-error name="password" /></div>
        <div class="portal-field"><label for="password_confirmation">Confirm password</label><input class="portal-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
    </div>
    <div class="check-row"><input id="terms" name="terms" type="checkbox" value="1" required @checked(old('terms'))><label for="terms">I agree to the publication terms, research integrity standards, and privacy notice.</label></div><x-portal.field-error name="terms" />
    <p class="portal-help">Your profile remains private and inactive until the website super-admin approves this application.</p>
    <button class="portal-button primary" type="submit" data-loading-text="Submitting application…">Submit author application</button>
    @include('auth._google-oauth', ['portal' => 'author'])
    <div class="auth-links"><span>Already approved? <a href="{{ route('author.login') }}">Author sign in</a></span><a href="{{ route('register') }}">Choose another account type</a></div>
</form>
@endsection
