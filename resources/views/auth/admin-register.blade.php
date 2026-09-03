@extends('layouts.portal')
@section('title', 'Administrator application')
@section('auth-eyebrow', 'Administrative registration')
@section('page-title', 'Administrator application')
@section('page-description', 'Request operational access. Super-admin access cannot be requested here.')
@section('content')
<style>.auth-card{width:min(100%,760px)}</style>
@include('auth._staff-register-form', ['submitRoute' => 'admin.register.store', 'loginRoute' => 'admin.login', 'roleLabel' => 'Administrator'])
@endsection
