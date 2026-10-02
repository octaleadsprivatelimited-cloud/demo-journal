@extends('layouts.portal')
@section('title','Reviewer profile')
@section('section','Peer review')
@section('page-title','My profile')
@section('page-description','Keep your professional background and expertise up to date for the editorial team.')
@section('content')
<div class="portal-card"><div class="portal-card-head"><h2>Professional details</h2></div><div class="portal-card-body">
<form class="portal-form" method="post" action="{{ route('reviewer.profile.update') }}">@csrf @method('put')
<div class="form-grid">
@foreach(['name'=>'Full name','organization'=>'Institution / organization','designation'=>'Position / title','phone'=>'Phone','department'=>'Department','country'=>'Country','orcid'=>'ORCID'] as $field=>$label)
<div class="portal-field"><label for="{{ $field }}">{{ $label }}</label><input class="portal-input" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, in_array($field,['name','organization','designation','phone']) ? $user->$field : data_get($user->reviewer_profile,$field)) }}" @required($field==='name')><x-portal.field-error :name="$field" /></div>
@endforeach
</div>
<div class="portal-field"><label>Email address</label><p>{{ $user->email }}</p></div>
@foreach(['expertise'=>'Areas of expertise','research_interests'=>'Research interests'] as $field=>$label)
<div class="portal-field"><label for="{{ $field }}">{{ $label }}</label><textarea class="portal-textarea" id="{{ $field }}" name="{{ $field }}" rows="4">{{ old($field,data_get($user->reviewer_profile,$field)) }}</textarea><x-portal.field-error :name="$field" /></div>
@endforeach
<button class="portal-button primary" type="submit">Save profile</button>
</form></div></div>
@endsection
