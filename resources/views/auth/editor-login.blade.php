@extends('layouts.portal')
@section('title', 'Editor sign in')
@section('auth-eyebrow', 'Editorial access')
@section('page-title', 'Editor sign in')
@section('page-description', 'Review submissions and manage the publication workflow.')
@section('content')
@include('auth._login-form', ['submitRoute' => 'editor.login.store', 'registrationRoute' => 'editor.register', 'portalLabel' => 'Editor Workspace', 'portal' => 'editor'])
@endsection
