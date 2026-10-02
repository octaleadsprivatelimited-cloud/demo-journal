@extends('layouts.portal')
@section('title','Email templates')
@section('page-title','Email templates')
@section('page-description','Preview purpose-specific email designs. The journal logo, website name and public contact details appear on notification emails.')
@section('content')
<div class="portal-card"><div class="portal-card-body"><p>Update public contact information under Site settings. Each template has its own heading, colour, instructions and action. Previews show sample content and do not send email.</p><div class="portal-form">
@foreach($templates as $type=>$label)<a class="portal-button" href="{{ route('admin.email-templates',['preview'=>$type]) }}" target="_blank" rel="noopener">Preview: {{ $label }}</a>@endforeach
</div></div></div>
@endsection
