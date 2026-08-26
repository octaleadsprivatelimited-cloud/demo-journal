@extends('layouts.portal')
@section('title','Create contributor account') @section('auth-eyebrow','Contributor application') @section('page-title','Become a contributor') @section('page-description','Request bounded access to draft, submit, and track content.')
@section('content') @include('auth._staff-register-form',['submitRoute'=>'contributor.register.store','loginRoute'=>'contributor.login','roleLabel'=>'Contributor','portal'=>'contributor']) @endsection
