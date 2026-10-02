@extends('layouts.portal')
@section('title',$definition['title'])
@section('page-title',$definition['title'])
@section('page-description','Draft changes stay private until you publish. Confirm journal facts and approved wording before publication.')
@section('content')
<p><a href="{{ route('admin.pages.index') }}">← All website pages</a> · <a href="{{ \App\Services\JournalPages::url($page) }}" target="_blank" rel="noopener">View public page</a></p>
<form method="post" action="{{ route('admin.pages.update',$page) }}" class="portal-card portal-card-body portal-form">@csrf @method('PUT')<input type="hidden" name="version" value="{{ $state['version'] }}">
<p><strong>Status:</strong> {{ $state['published'] ? 'Published' : 'Not published' }}</p>
<label for="page-content">Page wording</label><p class="portal-help">Use ## for section headings, a blank line between paragraphs and - for list items. Draft prompts are a starting point for the publisher and are never published automatically.</p><textarea class="portal-textarea" id="page-content" name="content" rows="22">{{ old('content',$state['draft']) }}</textarea><x-portal.field-error name="content" />
<label><input type="checkbox" name="approved" value="1"> I confirm this wording and the journal facts are approved for publication.</label><x-portal.field-error name="approved" />
<div><button class="portal-button" name="action" value="draft">Save draft</button> <button class="portal-button primary" name="action" value="publish">Publish page</button> @if($state['published'])<button class="portal-button" name="action" value="unpublish">Unpublish page</button>@endif</div></form>
@if($state['published'])<details class="portal-card portal-card-body"><summary>Currently published wording</summary><div style="white-space:pre-wrap">{{ $state['content'] }}</div></details>@endif
@endsection
