@extends('layouts.portal')
@section('title', 'Author sign in')
@section('auth-eyebrow', 'Author access')
@section('page-title', 'Author sign in')
@section('page-description', 'Access your manuscripts, submissions, and contributor profile.')
@section('content')
@include('auth._login-form', ['submitRoute' => 'author.login.store', 'registrationRoute' => 'author.register', 'portalLabel' => 'Author Studio'])
@endsection
