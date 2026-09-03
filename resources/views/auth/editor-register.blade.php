@extends('layouts.portal')
@section('title', 'Editor application')
@section('auth-eyebrow', 'Editorial registration')
@section('page-title', 'Editor application')
@section('page-description', 'Request access to the journal editorial workflow.')
@section('content')
<style>.auth-card{width:min(100%,760px)}</style>
@include('auth._staff-register-form', ['submitRoute' => 'editor.register.store', 'loginRoute' => 'editor.login', 'roleLabel' => 'Editor'])
@endsection
