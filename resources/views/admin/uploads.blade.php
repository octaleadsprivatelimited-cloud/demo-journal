@extends('layouts.portal')
@section('title','Uploaded files')
@section('section','Editorial')
@section('page-title','Uploaded files')
@section('page-description','Replace or permanently delete uploaded PDFs, supplementary documents, review attachments, and images. Deleting a file removes it from server storage.')
@section('content')
@if($article)<p>{{ $article->title }} · <a href="{{ route('admin.articles.edit',$article) }}">Edit article / upload a new PDF or image</a></p>@endif
@if($article)<div class="portal-card"><div class="portal-card-body"><form class="portal-form" method="post" enctype="multipart/form-data" action="{{ route('admin.uploads.store') }}">@csrf<input type="hidden" name="article_id" value="{{ $article->id }}"><label>Upload type<select class="portal-select" name="purpose">@foreach(['pdf'=>'Public reading PDF','image'=>'Featured image','supplementary'=>'Supplementary file','manuscript'=>'Manuscript','cover_letter'=>'Cover letter','response'=>'Response to reviewers'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label><label>New file<input class="portal-input" type="file" name="file" required></label><button class="portal-button primary">Upload file</button></form></div></div>@endif
<div class="portal-form">
@forelse($entries as $entry)
<div class="portal-card"><div class="portal-card-head"><div><h2>{{ $entry['name'] }}</h2><p>{{ ucfirst($entry['type']) }} · {{ $entry['label'] }}</p></div></div><div class="portal-card-body">
<form class="portal-form" method="post" enctype="multipart/form-data" action="{{ route('admin.uploads.update',[$entry['type'],$entry['id']]) }}">@csrf @method('put')<label>Replacement file<input class="portal-input" type="file" name="file" required></label><button class="portal-button" type="submit">Upload replacement</button></form>
<form method="post" action="{{ route('admin.uploads.destroy',[$entry['type'],$entry['id']]) }}" data-confirm="Permanently delete this file from the server? This cannot be undone.">@csrf @method('delete')<button class="portal-button danger" type="submit">Delete file permanently</button></form>
</div></div>
@empty<x-portal.empty title="No uploaded files" message="Upload files through the article, workflow, or media forms. They will appear here." />@endforelse
</div>
@endsection
