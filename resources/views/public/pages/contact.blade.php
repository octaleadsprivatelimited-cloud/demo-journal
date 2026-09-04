@extends('layouts.public')

@section('title', 'Contact the journal')
@section('description', 'Contact our editorial team about submissions, permissions, partnerships, technical questions, or the journal.')

@section('content')
    <section class="contact-hero">
        <div class="container">
            <x-public.breadcrumbs :items="['Contact' => null]" />
            <div class="page-hero-grid"><div><p class="eyebrow">Start a conversation</p><h1>Contact the journal</h1></div><p>Questions about a submission, a published article, or working with us? Send a note to the right desk and our team will respond thoughtfully.</p></div>
        </div>
    </section>
    <section class="section contact-section">
        <div class="container contact-grid">
            <div class="contact-aside">
                <p class="eyebrow">Direct enquiries</p>
                <h2>We read every message.</h2>
                <p>Choose the closest enquiry type so your message reaches the right editor. Please do not send sensitive personal or confidential research data through this form.</p>
                <dl>
                    @foreach(data_get($site, 'contact_emails', array_filter([data_get($site, 'contact_email')])) as $email)<div><dt>Email</dt><dd><a href="mailto:{{ $email }}">{{ $email }}</a></dd></div>@endforeach
                    @if(data_get($site, 'whatsapp'))<div><dt>WhatsApp</dt><dd><a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', data_get($site, 'whatsapp')) }}" target="_blank" rel="noopener noreferrer">{{ data_get($site, 'whatsapp') }}</a></dd></div>@endif
                    @if(data_get($site, 'phone'))<div><dt>Telephone</dt><dd><a href="tel:{{ preg_replace('/[^+0-9]/', '', data_get($site, 'phone')) }}">{{ data_get($site, 'phone') }}</a></dd></div>@endif
                    @if(data_get($site, 'address'))<div><dt>Correspondence</dt><dd>{{ data_get($site, 'address') }}</dd></div>@endif
                    <div><dt>Response window</dt><dd>Usually within three working days</dd></div>
                </dl>
            </div>
            <div class="contact-form-card">
                <div class="form-heading"><p class="eyebrow">Send a message</p><h2>How can we help?</h2><p>Fields marked with an asterisk are required.</p></div>
                @if($errors->any())
                    <div class="form-errors" role="alert" tabindex="-1" data-form-errors>
                        <strong>Please review the highlighted fields.</strong>
                        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <form action="{{ route('contact.store') }}" method="post" class="contact-form" data-submitting-form novalidate>
                    @csrf
                    <div class="honeypot" aria-hidden="true"><label>Company website<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label></div>
                    <div class="form-row">
                        <div><label for="contact-name">Full name <span aria-hidden="true">*</span></label><input id="contact-name" name="name" value="{{ old('name') }}" required autocomplete="name" maxlength="120" @class(['is-invalid' => $errors->has('name')]) aria-describedby="@error('name') contact-name-error @enderror">@error('name')<small id="contact-name-error" class="field-error">{{ $message }}</small>@enderror</div>
                        <div><label for="contact-email">Email address <span aria-hidden="true">*</span></label><input id="contact-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" maxlength="254" @class(['is-invalid' => $errors->has('email')])>@error('email')<small class="field-error">{{ $message }}</small>@enderror</div>
                    </div>
                    <div class="form-row">
                        <div><label for="contact-phone">Telephone <span>(optional)</span></label><input id="contact-phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="40">@error('phone')<small class="field-error">{{ $message }}</small>@enderror</div>
                        <div><label for="contact-category">Enquiry type</label><select id="contact-category" name="category"><option value="general">General enquiry</option><option value="editorial" @selected(old('category') === 'editorial')>Editorial question</option><option value="submissions" @selected(old('category') === 'submissions')>Author submission</option><option value="permissions" @selected(old('category') === 'permissions')>Permissions &amp; republication</option><option value="partnerships" @selected(old('category') === 'partnerships')>Partnership</option><option value="technical" @selected(old('category') === 'technical')>Technical support</option></select></div>
                    </div>
                    <div><label for="contact-subject">Subject <span aria-hidden="true">*</span></label><input id="contact-subject" name="subject" value="{{ old('subject') }}" required maxlength="180" @class(['is-invalid' => $errors->has('subject')])>@error('subject')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div><label for="contact-message">Message <span aria-hidden="true">*</span></label><textarea id="contact-message" name="message" rows="8" required maxlength="10000" @class(['is-invalid' => $errors->has('message')])>{{ old('message') }}</textarea><div class="field-help"><span>Please include relevant article or submission details.</span><span data-character-count>0 / 10,000</span></div>@error('message')<small class="field-error">{{ $message }}</small>@enderror</div>
                    <div class="form-consent"><p>By submitting this form, you agree that we may use your details to respond to this enquiry.</p><button class="button button-primary" type="submit"><span>Send message</span><x-public.icon name="arrow-right" /></button></div>
                </form>
            </div>
        </div>
    </section>
@endsection
