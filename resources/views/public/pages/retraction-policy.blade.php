@extends('layouts.public')

@section('title', 'Retraction Policy')
@section('description', 'Larix Journals policy for investigating and retracting manuscripts affected by scientific malpractice.')

@section('content')
    <section class="page-hero">
        <div class="container">
            <x-public.breadcrumbs :items="['Retraction policy' => null]" />
            <div class="page-hero-grid">
                <div><p class="eyebrow">Publishing integrity</p><h1>Retraction Policy</h1></div>
                <p>We safeguard the integrity of the scholarly record through rigorous review and accountable editorial action.</p>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container container-reading">
            <div class="article-content">
                <h2>Retraction Policy</h2>
                <p>Larix Journals uncompromisingly follows COPE guidelines and does not entertain any kind of scientific malpractice in manuscripts. Larix Journals strongly believes in maintaining standards and never accepts an alteration in the quality of manuscripts. Manuscripts are evaluated in various aspects of plagiarism, misrepresentation of data, manipulation of images, falsified interpretation of data, and related concerns.</p>
                <p>Larix Journals applies a serious retraction policy: manuscripts that meet the above-stated conditions will be retracted from publishing and displayed on a separate web page titled “Retracted Manuscripts”. This policy helps ensure that the research and information submitted by authors are original and are not stolen or misappropriated data.</p>
                <p>The Editor-in-Chief of the journal holds responsibility for retraction of manuscripts and takes the necessary action against these malpractices. If information provided in a manuscript is found to be plagiarized from previously published work, the editor will recheck the genuineness of the research by inquiring with the authors’ institution. All authors of the manuscript hold equal responsibility and are liable for the stated consequences if claims are proved.</p>
                <p>Larix Journals displays retracted articles in all formats on its website to avoid further unprofessional and unethical practices. Authors of retracted manuscripts may contact the editorial office and demonstrate the genuineness of their work with proper fact sheets and evidence supporting their data as original and not fabricated.</p>
            </div>
        </div>
    </section>
@endsection
