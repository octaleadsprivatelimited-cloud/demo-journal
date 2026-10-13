@extends('layouts.portal')
@section('title', $staffRole ? 'Add '.$staffRole->slug : 'New user')
@section('section', 'People')
@section('page-title', $staffRole ? 'Add '.$staffRole->slug : 'New user')
@section('page-description', 'As Super Admin, you approve this account when you create it. The person must verify their email before signing in.')
@section('page-actions')<a class="portal-button" href="{{ route('admin.users.index') }}">Back to People</a>@endsection
@section('content')
    <div class="portal-card admin-staff-form">
        <div class="portal-card-body">
            @if($staffRole)
                @include('admin.users._staff-form')
            @else
                <p>Choose the account's roles below. Public Editorial board appointments are managed separately under Website.</p>
                @include('admin.users._form')
            @endif
        </div>
    </div>
@endsection
