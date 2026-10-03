@extends('layouts.portal')
@php
    $verificationDeliveryUnavailable = app()->isProduction() && (blank(config('mail.default')) || in_array(config('mail.default'), ['log', 'array'], true));
@endphp
@section('title', 'Verify email')
@section('section', 'Account security')
@section('eyebrow', 'One final step')
@section('page-title', 'Verify your email address')
@section('page-description', $verificationDeliveryUnavailable
    ? 'Email verification delivery is not available yet. Please contact the journal administrator.'
    : 'A signed verification link is required to confirm '.auth()->user()->email.' and unlock your approved workspace.')
@section('content')
<div class="portal-card">
    <div class="portal-card-body">
        <div class="empty-state">
            <span><x-portal.icon name="mail" :size="28" /></span>
            @if($verificationDeliveryUnavailable)
                <h3>Email delivery unavailable</h3>
                <p>Your account still needs email verification before you can use your workspace.</p>
            @else
                <h3>Check your inbox</h3>
                <p>The link expires for your protection. If it has not arrived, check spam or request another message.</p>
                <form method="post" action="{{ route('verification.send') }}" data-loading>
                    @csrf
                    <button class="portal-button primary" type="submit">Resend verification email</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
