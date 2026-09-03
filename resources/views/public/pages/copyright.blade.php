@extends('layouts.public')

@section('title', 'Copyright')
@section('description', 'Copyright information and author sharing rights for Larix Journals publications.')

@section('content')
    <section class="page-hero">
        <div class="container">
            <x-public.breadcrumbs :items="['Copyright' => null]" />
            <div class="page-hero-grid">
                <div><p class="eyebrow">Copy Right</p><h1>Copyright information</h1></div>
                <p>Our copyright policy protects authorship while supporting responsible scholarly sharing.</p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container container-reading">
            <div class="article-content">
                <h2>Copyright Information</h2>
                <p>All material published in Larix Journals represents the opinions of the authors and does not reflect the opinions of the Editors, or the institutions with which the authors are affiliated. Authors submitting manuscripts to Larix Journals, once a manuscript is accepted, assign the copyright in the article, including the right to reproduce the article in all forms and media, exclusively to the corresponding author. All coauthors will be required to sign their copyright agreement. This can be done at any time following initial submission of a manuscript but must be completed before an accepted article is posted to First Edition or otherwise published in the journal.</p>
                <p>Larix Journals allows authors to retain a number of nonexclusive rights to their published article. Signatures for copyright transfer are collected online or by email. Authors have permission to do the following after their article has been published in Larix Journals, either in print or online as a First Edition Paper.</p>

                <h2>The Accepted, First Edition version of articles can be shared by authors:</h2>
                <ul>
                    <li>Privately with students or colleagues for their personal use in presentations and other educational endeavors.</li>
                    <li>Privately on the authors’ institutional repositories.</li>
                    <li>On personal websites.</li>
                </ul>

                <h2>The Final or Print version of articles can be shared by authors:</h2>
                <ul>
                    <li>As listed above; and</li>
                    <li>As a link to the article on the journal’s website anywhere at any time.</li>
                </ul>
            </div>
        </div>
    </section>
@endsection
