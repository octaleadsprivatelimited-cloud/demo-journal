@extends('layouts.portal')
@section('title','Website pages')
@section('page-title','Pages & policies')
@section('page-description','Manage journal information, author guidance and policies. Save a draft, then publish approved wording.')
@section('content')
<form method="get" class="portal-card portal-card-body portal-form"><label for="page-search">Find a page</label><input class="portal-input" id="page-search" name="q" value="{{ $q }}" placeholder="Search by title"><label for="page-group">Section</label><select class="portal-select" name="group" id="page-group"><option value="">All sections</option>@foreach(\App\Services\JournalPages::groups() as $key=>$label)<option value="{{ $key }}" @selected($group===$key)>{{ $label }}</option>@endforeach</select><button class="portal-button" type="submit">Apply filters</button><a href="{{ route('admin.pages.index') }}">Clear filters</a></form>
<div class="portal-card portal-card-body"><p>Pages with existing features link to their public view. Edit articles to manage publication notices; use Site settings for verified journal identifiers and publisher information.</p><div style="overflow-x:auto"><table class="portal-table"><thead><tr><th>Page</th><th>Section</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($pages as $slug=>$definition)
@php($state=$definition['route'] ? null : \App\Services\JournalPages::state($slug))
<tr><td>{{ $definition['title'] }}</td><td>{{ \App\Services\JournalPages::groups()[$definition['group']] }}</td><td>{{ $definition['route'] ? 'Existing feature' : ($state['published'] ? 'Published' : 'Not published') }}</td><td>@unless($definition['route'])<a href="{{ route('admin.pages.edit',$slug) }}">Edit page</a> · @endunless<a href="{{ \App\Services\JournalPages::url($slug) }}" target="_blank" rel="noopener">View page</a></td></tr>
@empty<tr><td colspan="4">No pages match these filters.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
