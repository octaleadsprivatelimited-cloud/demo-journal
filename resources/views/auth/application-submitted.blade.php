@extends('layouts.portal')
@section('title', 'Application submitted')
@section('auth-eyebrow', 'Pending review')
@section('page-title', 'Application submitted')
@section('page-description', 'Your application is waiting for review by the website super-admin.')
@section('content')
@php($application = session('application', []))
<div class="portal-card">
    <div class="portal-card-body">
        <div class="empty-state">
            <span><x-portal.icon name="mail" :size="28" /></span>
            <h3>Access has not been granted yet</h3>
            <p>
                @if(isset($application['role'], $application['email']))
                    The {{ strtolower($application['role']) }} application for {{ $application['email'] }} was received.
                @endif
                You will be able to sign in only after approval and email verification.
            </p>
            @if($application['email_verified'] ?? false)
                <p>Your email address is already verified. Your application is awaiting Super Admin approval.</p>
            @elseif(app()->isProduction() && (blank(config('mail.default')) || in_array(config('mail.default'), ['log', 'array'], true)))
                <p>Email verification delivery is not available yet. Please contact the journal administrator.</p>
            @else
                <p>Check your inbox for the verification link and confirm your email now. Your application will still require Super Admin approval.</p>
                <p><a href="{{ route('registration.verification.notice') }}">Request another verification link</a></p>
            @endif
            <a class="portal-button primary" href="{{ route('login') }}">View sign-in options</a>
        </div>
    </div>
</div>
@endsection
