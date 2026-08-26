@extends('layouts.portal')
@section('title', 'Reviewer sign in')
@section('auth-eyebrow', 'Peer review access')
@section('page-title', 'Reviewer sign in')
@section('page-description', 'Open and complete the reviews assigned to your account.')
@section('content')
@include('auth._login-form', ['submitRoute' => 'reviewer.login.store', 'registrationRoute' => 'reviewer.register', 'portalLabel' => 'Reviewer Workspace', 'portal' => 'reviewer'])
@endsection
