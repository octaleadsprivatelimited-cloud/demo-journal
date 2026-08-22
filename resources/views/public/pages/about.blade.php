@extends('layouts.public')

@section('title', 'About the journal')
@section('description', 'Learn about our editorial mission, standards, and commitment to clear, consequential ideas.')

@section('content')
    <section class="about-hero">
        <div class="container">
            <x-public.breadcrumbs :items="['About' => null]" />
            <div class="about-hero-grid">
                <div><p class="eyebrow light">About the journal</p><h1>Ideas with enough room to become useful.</h1></div>
                <div><p>We believe careful thought is a public good. Our journal creates space for original research, informed argument, and writing that crosses disciplines without flattening their complexity.</p><a class="text-link text-link-light" href="{{ route('editorial-board') }}">Meet our editorial board <x-public.icon name="arrow-right" /></a></div>
            </div>
        </div>
    </section>
    <section class="section about-story">
        <div class="container about-story-grid">
            <div><p class="eyebrow">Our purpose</p><h2>Depth is not the opposite of accessibility.</h2></div>
            <div class="prose-large">
                <p>The best scholarship does more than accumulate knowledge: it changes the quality of a conversation. We work with researchers, practitioners, and writers to make serious ideas legible beyond the boundaries of a single field.</p>
                <p>Every piece is selected for the clarity of its question, the integrity of its evidence, and its capacity to reward sustained attention. Our editors preserve the writer’s voice while asking the difficult questions that make an argument stronger.</p>
            </div>
        </div>
    </section>
    <section class="principles-section">
        <div class="container">
            <div class="section-heading"><div><p class="eyebrow">Editorial principles</p><h2>How we approach the work</h2></div></div>
            <div class="principles-grid">
                <article><span>01</span><h3>Rigor before certainty</h3><p>We value evidence, transparent reasoning, and the courage to name what remains unresolved.</p></article>
                <article><span>02</span><h3>Clarity without reduction</h3><p>Accessible writing can retain nuance. We edit for precision, not simplification.</p></article>
                <article><span>03</span><h3>Plural perspectives</h3><p>Knowledge advances when methods, disciplines, and lived experiences encounter one another.</p></article>
                <article><span>04</span><h3>Enduring relevance</h3><p>We publish work that stays useful beyond a single news cycle or institutional moment.</p></article>
            </div>
        </div>
    </section>
    <section class="stats-band">
        <div class="container stats-grid">
            <div><strong>{{ number_format($publishedCount) }}</strong><span>Published works</span></div>
            <div><strong>{{ number_format($authorCount) }}</strong><span>Contributors</span></div>
            <div><strong>{{ number_format($categoryCount) }}</strong><span>Fields of inquiry</span></div>
            <div><strong>Open</strong><span>To consequential ideas</span></div>
        </div>
    </section>
    <section class="section about-cta"><div class="container"><div><p class="eyebrow">Join the conversation</p><h2>Bring us a question worth pursuing.</h2></div><div><p>We welcome original scholarship, essays, reviews, and field notes that meet our editorial standards.</p>@if(data_get($site, 'features.author_registration', true) && Route::has('author.register'))<a class="button button-primary" href="{{ route('author.register') }}">Submit your work</a>@else<a class="button button-primary" href="{{ route('contact') }}">Contact the editors</a>@endif</div></div></section>
@endsection
