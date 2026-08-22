@extends('layouts.portal') @section('title','Edit '.$article->title) @section('section','Author studio') @section('eyebrow','Draft editor') @section('page-title','Edit manuscript')
@section('page-description','Autosave protects text changes. Use “Save version” for a named snapshot before major revisions.')
@section('page-actions')<a class="portal-button" href="{{ route('author.articles.show',$article) }}">View record</a>@endsection @section('content') @include('author.articles._form') @endsection
