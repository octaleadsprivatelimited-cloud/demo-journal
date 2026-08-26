@extends('layouts.portal')
@section('title','Contributor sign in') @section('auth-eyebrow','Contributor access') @section('page-title','Welcome back') @section('page-description','Create, submit, and track journal contributions.')
@section('content') @include('auth._login-form',['submitRoute'=>'contributor.login.store','registrationRoute'=>'contributor.register','portalLabel'=>'Contributor Workspace','portal'=>'contributor']) @endsection
