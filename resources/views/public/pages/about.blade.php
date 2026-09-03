@extends('layouts.public')

@section('title', 'About us')
@section('description', 'Learn about Larix International, our open-access publishing mission, and our commitment to rigorous scientific scholarship.')

@section('content')
    <section class="about-hero">
        <div class="container">
            <x-public.breadcrumbs :items="['About' => null]" />
            <div class="about-hero-grid">
                <div><p class="eyebrow light">About us</p><h1>Advancing science, medicine, and technology through open access.</h1></div>
                <div><p>Larix International publishes high-quality, open-access scientific and medical journals, supported by dedicated editors, reviewers, and authors around the world.</p><a class="text-link text-link-light" href="{{ route('editorial-board') }}">Meet our editorial board <x-public.icon name="arrow-right" /></a></div>
            </div>
        </div>
    </section>
    <section class="section about-story">
        <div class="container about-story-grid">
            <div><p class="eyebrow">Establishment of Larix</p><h2>Publishing research that moves knowledge forward.</h2></div>
            <div class="prose-large">
                <p>Larix International is a private limited company involved in publishing open-access science- and medicine-related journals. The company is run by a team of dedicated editors, reviewers, and authors. Over the years, Larix has evolved into a forerunner in the publishing industry worldwide, continuing to lead through technology, people, and innovation.</p>
            </div>
        </div>
    </section>
    <section class="principles-section">
        <div class="container">
            <div class="section-heading"><div><p class="eyebrow">Why publish with Larix?</p><h2>Rigorous review. Open access. Effective publication.</h2></div></div>
            <div class="principles-grid">
                <article><span>01</span><h3>High-quality research</h3><p>We publish original research papers, case reports, and other scholarly manuscripts across science and medicine.</p></article>
                <article><span>02</span><h3>Stringent peer review</h3><p>Our editorial board oversees a multi-level peer-review process, from evaluating submissions and selecting reviewers to assessing reports and making editorial decisions.</p></article>
                <article><span>03</span><h3>Open access</h3><p>We support unrestricted access, reproduction, and distribution of published work in any medium, provided the original work is properly cited.</p></article>
                <article><span>04</span><h3>Efficient service</h3><p>We offer researchers a broad range of services and are known for efficient, effective online publication.</p></article>
            </div>
        </div>
    </section>
    <section class="stats-band" data-live-stats data-stats-url="{{ route('about.stats') }}">
        <div class="container stats-grid">
            <div><strong data-live-stat="published">{{ number_format($publishedCount) }}</strong><span>Published works</span></div>
            <div><strong data-live-stat="contributors">{{ number_format($authorCount) }}</strong><span>Contributors</span></div>
            <div><strong data-live-stat="fields">{{ number_format($categoryCount) }}</strong><span>Fields of inquiry</span></div>
            <div><strong>Open</strong><span>Access to research</span></div>
        </div>
    </section>
    <section class="section about-cta"><div class="container"><div><p class="eyebrow">Vision and mission</p><h2>Making scholarly literature accessible to a global audience.</h2></div><div><p><strong>Vision of Larix:</strong> We seek to shape future growth in science, medicine, and technology by promoting open access to scholarly manuscripts—free to read or download for any lawful purpose.</p><p><strong>Mission of Larix:</strong> We publish, discuss, and analyse valuable research, applications, and expert opinions. We bring original papers with strong analytical messages to an international audience and ensure published research provides meaningful insight into the journal’s scope.</p>@if(data_get($site, 'features.author_registration', true) && Route::has('author.register'))<a class="button button-primary" href="{{ route('author.register') }}">Submit your work</a>@else<a class="button button-primary" href="{{ route('contact') }}">Contact the editors</a>@endif</div></div></section>
@endsection
