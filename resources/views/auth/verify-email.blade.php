@extends('layouts.portal')
@section('title', 'Verify email')
@section('section', 'Account security')
@section('eyebrow', 'One final step')
@section('page-title', 'Verify your email address')
@section('page-description', 'We sent a signed verification link to '.auth()->user()->email.'. Verification protects your identity and unlocks manuscript submission.')
@section('content')
<div class="portal-card"><div class="portal-card-body"><div class="empty-state"><span><x-portal.icon name="mail" :size="28" /></span><h3>Check your inbox</h3><p>The link expires for your protection. If it has not arrived, check spam or request another message.</p><form method="post" action="{{ route('verification.send') }}" data-loading>@csrf<button class="portal-button primary" type="submit">Resend verification email</button></form></div></div></div>
@endsection
