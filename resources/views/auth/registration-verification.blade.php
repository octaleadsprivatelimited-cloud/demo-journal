@extends('layouts.portal')
@php
    $emailVerified = $emailVerified ?? false;
    $deliveryUnavailable = app()->isProduction() && (blank(config('mail.default')) || in_array(config('mail.default'), ['log', 'array'], true));
@endphp
@section('title', $emailVerified ? 'Email verified' : 'Verify your application email')
@section('auth-eyebrow', 'Account verification')
@section('page-title', $emailVerified ? 'Email address verified' : 'Verify your application email')
@section('page-description', $emailVerified ? 'Your email has been confirmed. Every account also requires Super Admin approval before access is granted.' : 'Request a fresh link for your pending account application.')
@section('content')
<div class="portal-card"><div class="portal-card-body">
    @if($emailVerified)
        <p>You can sign in once the Super Admin approves your application. We will email you when a decision is made.</p>
        <a class="portal-button primary" href="{{ route('login') }}">View sign-in options</a>
    @elseif($deliveryUnavailable)
        <p>Email verification delivery is not available yet. Please contact the journal administrator.</p>
    @else
        <form class="portal-form" method="post" action="{{ route('registration.verification.send') }}" data-loading>
            @csrf
            <div class="portal-field"><label for="email">Application email address</label><input class="portal-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required><x-portal.field-error name="email" /></div>
            <button class="portal-button primary" type="submit">Resend verification email</button>
            <p class="portal-help">Check your spam folder too. Email verification does not replace Super Admin approval.</p>
        </form>
    @endif
</div></div>
@endsection
