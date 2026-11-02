@extends('layouts.public')
@section('title','Resources')
@section('description','Submission templates and author resources for '.data_get($site, 'name', config('publication.name')).'.')
@section('content')
<section class="page-hero"><div class="container"><x-public.breadcrumbs :items="['Resources'=>null]"/><div class="page-hero-grid"><div><p class="eyebrow">Author centre</p><h1>Resources</h1></div><p>Download the templates and forms you need to prepare and submit your manuscript.</p></div></div></section>
<section class="section"><div class="container container-reading"><div class="downloads-list">
@forelse(app(\App\Services\PublicationSettings::class)->resources() as [$title,$file,$url,$description])
<article class="download-row"><span class="download-mark">PDF</span><div><h3>{{ $title }}</h3><p>{{ $description }}</p></div><a class="button button-primary" href="{{ $url }}" download="{{ $file }}" download>Download PDF <x-public.icon name="arrow-up-right" /></a></article>
@empty
<div class="downloads-empty"><h2>Submission resources are being prepared.</h2><p>Please contact the editorial office for the current submission templates.</p><a class="text-link" href="{{ route('contact') }}">Contact the editorial office <x-public.icon name="arrow-right" /></a></div>
@endforelse
</div></div></section>
@endsection
