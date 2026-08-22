@props(['source' => 'website', 'compact' => false])
<form action="{{ route('newsletter.subscribe') }}" method="post" @class(['newsletter-form', 'newsletter-compact' => $compact]) data-submitting-form>
    @csrf
    <input type="hidden" name="source" value="{{ $source }}">
    <div class="honeypot" aria-hidden="true">
        <label>Website <input type="text" name="newsletter_website" tabindex="-1" autocomplete="off"></label>
    </div>
    @unless($compact)
        <label for="newsletter-name-{{ $source }}">Name <span>(optional)</span></label>
        <input id="newsletter-name-{{ $source }}" type="text" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="120">
    @endunless
    <label for="newsletter-email-{{ $source }}">Email address</label>
    <div class="newsletter-row">
        <input id="newsletter-email-{{ $source }}" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" required maxlength="254">
        <button class="button button-accent" type="submit"><span>Subscribe</span><x-public.icon name="arrow-right" /></button>
    </div>
    <p>One considered dispatch each month. Unsubscribe in one click.</p>
</form>
