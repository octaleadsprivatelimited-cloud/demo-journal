@extends('layouts.portal')
@section('title', 'Administrator sign in')
@section('auth-eyebrow', 'Administrative access')
@section('page-title', 'Administrator sign in')
@section('page-description', 'Secure access for administrators and the website super-admin.')
@section('content')
@include('auth._login-form', ['submitRoute' => 'admin.login.store', 'registrationRoute' => 'admin.register', 'portalLabel' => 'Admin Panel'])
@endsection
