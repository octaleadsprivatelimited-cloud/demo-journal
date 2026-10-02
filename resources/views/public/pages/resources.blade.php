@extends('layouts.public')
@section('title','Resources')
@section('description','Submission templates and author resources for the Singapore Journal of Cardiology.')
@section('content')
<section class="page-hero"><div class="container"><x-public.breadcrumbs :items="['Resources'=>null]"/><div class="page-hero-grid"><div><p class="eyebrow">Author centre</p><h1>Resources</h1></div><p>Download the templates and forms you need to prepare and submit your manuscript.</p></div></div></section>
<section class="section"><div class="container container-reading"><div class="downloads-list">
@foreach([['Guidelines','Larix Guidelines.pdf','/author-resources/guidelines.pdf','Author and manuscript preparation requirements.'],['Copyright form','SJC Copyright and Authorization Form.pdf','/author-resources/copyright-form.pdf','Copyright and publication authorization form.'],['Cover letter','SJC Cover Letter.pdf','/author-resources/cover-letter.pdf','Cover letter template for new submissions.']] as [$title,$file,$url,$description])
<article class="download-row"><span class="download-mark">PDF</span><div><h3>{{ $title }}</h3><p>{{ $description }}</p></div><a class="button button-primary" href="{{ $url }}" download="{{ $file }}" download>Download PDF <x-public.icon name="arrow-up-right" /></a></article>
@endforeach
</div></div></section>
@endsection
