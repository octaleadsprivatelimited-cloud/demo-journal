<footer class="site-footer">
    <div class="container">
        <div class="footer-intro">
            <div class="footer-brand">
                <a class="footer-journal-name" href="{{ route('home') }}">{{ $siteName }}</a>
                <p>Open access cardiology research, clinical case reports and reviews in cardiovascular medicine.</p>
            </div>
            @if(data_get($site, 'features.newsletter', true))
                <div class="footer-signup"><h2>Journal updates</h2><x-public.newsletter source="footer" :compact="true" /></div>
            @endif
        </div>
        <nav class="footer-grid" aria-label="Journal links and contact">
            <div><h3>About</h3>
                <a href="{{ route('about') }}">About the journal</a>
                <a href="{{ route('editorial-board') }}">Editorial board</a>
                <a href="{{ route('policies.show', 'aims-scope') }}">Aims &amp; Scope</a>
                <a href="{{ route('authors.index') }}">Contributors</a>
            </div>
            <div><h3>For Authors</h3>
                <a href="{{ route('policies.show', 'author-guidelines') }}">Author guidelines</a>
                <a href="{{ route('policies.show', 'manuscript-preparation') }}">Manuscript preparation</a>
                <a href="{{ route('resources') }}">Forms &amp; resources</a>
                @if(data_get($site, 'features.author_registration', true) && Route::has('author.register'))<a href="{{ route('author.register') }}">Submit Manuscript</a>@endif
            </div>
            <div><h3>Policies &amp; Peer Review</h3>
                <a href="{{ route('policies.show', 'peer-review') }}">Peer review process</a>
                <a href="{{ route('policies.show', 'publication-ethics') }}">Publication ethics</a>
                <a href="{{ route('policies.show', 'reviewer-conduct') }}">Reviewer Code of Conduct</a>
                <a href="{{ route('policies.show', 'privacy') }}">Privacy policy</a>
                <a href="{{ route('policies.index') }}">All policies &amp; guidance</a>
            </div>
            <div><h3>Archives</h3>
                <a href="{{ route('archive.index') }}">Browse the archives</a>
                <a href="{{ route('articles.index') }}">Latest articles</a>
                <a href="{{ route('corrections.index') }}">Corrections &amp; retractions</a>
                @if(data_get($site, 'features.pdf_downloads', true))<a href="{{ route('downloads') }}">Article downloads</a>@endif
            </div>
            <div class="footer-contact"><h3>Contact</h3>
                <a href="{{ route('contact') }}">Contact the editorial office</a>
                @foreach(collect(data_get($site, 'contact_emails', array_filter([data_get($site, 'contact_email')])))->filter()->unique() as $email)<a href="mailto:{{ $email }}">{{ $email }}</a>@endforeach
                @if(data_get($site, 'whatsapp'))<a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', data_get($site, 'whatsapp')) }}" target="_blank" rel="noopener noreferrer">WhatsApp: {{ data_get($site, 'whatsapp') }}</a>@endif
                @if(data_get($site, 'address'))<p>{{ data_get($site, 'address') }}</p>@endif
                <div class="social-links" aria-label="Follow the journal">
                    @foreach(collect(data_get($site, 'social', []))->filter() as $network => $url)
                        @php($networkLabel = match($network) { 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'x', 'twitter' => 'X (Twitter)', default => str($network)->headline()->toString() })
                        <a class="social-link" href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $networkLabel }}" title="{{ $networkLabel }}"><x-public.social-icon :network="$network" /></a>
                    @endforeach
                </div>
            </div>
        </nav>
        <div class="footer-bottom">
            <p>&copy; {{ now()->year }} {{ $siteName }}. All rights reserved.</p>
            <div><a href="{{ route('sitemap') }}">Sitemap</a><a href="https://www.octaleads.com" target="_blank" rel="noopener noreferrer">Developed by octaleads</a></div>
        </div>
    </div>
</footer>
