@extends('layouts.public')

@section('title', 'Appeal')
@section('description', 'Information about appealing an editorial decision at Larix Journals.')

@section('content')
    <section class="page-hero">
        <div class="container">
            <x-public.breadcrumbs :items="['Appeal' => null]" />
            <div class="page-hero-grid">
                <div><p class="eyebrow">Editorial process</p><h1>Appeal</h1></div>
                <p>Authors may appeal an editorial decision when they believe relevant scientific information has not been fully considered.</p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container container-reading">
            <div class="article-content">
                <h2>Appeal</h2>
                <p>The manuscripts submitted to <strong>Larix Journals</strong> undergo a proper peer-review process, where the final decision of acceptance or rejection of the manuscript is provided by the Chief Editors.</p>
                <p>These editorial decisions are unbiased and purely based on the scientific eminence of the manuscript, the originality in the research, the way authors present their opinions along with the relevant evidence, and the usefulness of the information to society. If a manuscript is rejected by the editor after taking into consideration all these factors and the author disagrees with the decision, the author can appeal against the editors’ decision.</p>
                <p>The appeal should be an electronic letter containing the reasons behind the authors’ disagreement with the editorial decision, the facts supporting the prominence of the manuscript, and a detailed explanation addressing all editorial queries and comments provided for the betterment of the manuscript.</p>
                <p>The appeal from authors should be submitted within 30 days of communicating the rejection of the manuscript from the editorial office. After the mentioned timeline, Larix does not owe any responsibility for such appeals.</p>
            </div>
        </div>
    </section>
@endsection
