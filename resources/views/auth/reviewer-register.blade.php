@extends('layouts.portal')
@section('title', 'Create reviewer account')
@section('auth-eyebrow', 'Peer review registration')
@section('page-title', 'Create reviewer account')
@section('page-description', 'Help shape rigorous publishing through thoughtful peer review.')
@section('content')
<style>.auth-card{width:min(100%,760px)}</style>
@include('auth._staff-register-form', ['submitRoute' => 'reviewer.register.store', 'loginRoute' => 'reviewer.login', 'roleLabel' => 'Reviewer', 'portal' => 'reviewer'])
@endsection
