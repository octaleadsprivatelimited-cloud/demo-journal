@if(config('services.google.enabled'))
    <div class="oauth-divider" role="separator"><span>or</span></div>
    <a class="portal-button oauth-button" href="{{ route('google.redirect', ['portal' => $portal]) }}">
        <span class="oauth-google-mark" aria-hidden="true">G</span>
        Continue with Google
    </a>
    <p class="portal-help oauth-help">First use creates a secure application where that role accepts applications. Access begins after Super Admin approval.</p>
@endif
