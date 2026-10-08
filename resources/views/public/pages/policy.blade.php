@extends('layouts.public')
@section('title',$title)
@section('description',$content ? Str::limit(strip_tags($content),155) : $title.' — journal information and enquiries.')
@section('robots', $content || $facts || $indexingServices->isNotEmpty() ? 'index, follow' : 'noindex, follow')
@section('content')
<section class="section journal-information"><div class="container">
<x-public.breadcrumbs :items="['Policies & guidance'=>route('policies.index'),$title=>null]" />
<div class="journal-guide-layout"><aside class="journal-guide-sidebar"><h2>{{ \App\Services\JournalPages::groups()[$definition['group']] }}</h2><nav aria-label="Related journal pages">@foreach(\App\Services\JournalPages::all() as $slug=>$item)@if($item['group']===$definition['group'])<a href="{{ \App\Services\JournalPages::url($slug) }}" @if($slug===$page) aria-current="page" @endif>{{ $item['title'] }}</a>@endif @endforeach</nav><a href="{{ route('policies.index') }}">All pages & guidance →</a></aside>
<div><header class="page-heading"><p class="eyebrow">{{ \App\Services\JournalPages::groups()[$definition['group']] }}</p><h1>{{ $title }}</h1>@if($state['published'] && $state['updated_at'])<p>Updated {{ \Illuminate\Support\Carbon::parse($state['updated_at'])->format('j F Y') }}</p>@endif</header>
<article class="prose article-body">@if(filled($content)){!! Str::markdown($content,['html_input'=>'strip','allow_unsafe_links'=>false]) !!}@elseif(!$facts && $indexingServices->isEmpty())<p>Published information for this topic is not yet available. Please contact the editorial office for guidance.</p><a href="{{ route('contact') }}">Contact the journal</a>@endif</article>
@if($page === 'privacy' && config('publication.integrations.tracking_enabled'))
@include('components.public.analytics-privacy')
@endif
@if($facts)<dl class="journal-facts">@foreach($facts as $label=>$value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>@endif
@if($indexingServices->isNotEmpty())<section class="journal-directory-grid">@foreach($indexingServices as $service)<article class="category-card"><h2>{{ $service->name }}</h2><p>{{ $service->description }}</p>@if($service->official_url)<a href="{{ $service->official_url }}" rel="external noopener">Verify database listing</a>@endif</article>@endforeach</section>@endif
@if(isset($definition['action']))<div class="journal-guide-action"><a class="button button-primary" href="{{ route($definition['action']['route'],$definition['action']['parameters']) }}">{{ $definition['action']['label'] }} →</a></div>@endif
</div></div></div></section>
@endsection
