@extends('layouts.portal')
@section('title', 'Create editor account')
@section('auth-eyebrow', 'Editorial registration')
@section('page-title', 'Create editor account')
@section('page-description', 'Request access to the journal editorial workflow.')
@section('content')
<style>.auth-card{width:min(100%,760px)}</style>
@include('auth._staff-register-form', ['submitRoute' => 'editor.register.store', 'loginRoute' => 'editor.login', 'roleLabel' => 'Editor', 'portal' => 'editor'])
@endsection
