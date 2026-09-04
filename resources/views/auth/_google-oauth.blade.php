@if(config('services.google.enabled'))
    @php
        $googleNonce = Illuminate\Support\Str::random(48);
        session()->put('google_one_tap.'.$portal, ['nonce' => $googleNonce, 'expires' => time() + 600]);
    @endphp
    <div class="google-access" data-google-access>
        <div id="google-signin-button"></div>
        <p class="portal-help" data-google-status>Loading Google sign-in…</p>
        <a class="portal-button oauth-button" href="{{ route('google.redirect', ['portal' => $portal]) }}">Continue with Google</a>
        <p class="portal-help">First-time users create an application for this workspace. Access begins after Super Admin approval. Existing users sign in to their approved role.</p>
    </div>
    @push('scripts')
    <script>
    window.initializeJournalGoogle = function () {
        const root = document.querySelector('[data-google-access]');
        google.accounts.id.initialize({
            client_id: @json(config('services.google.client_id')),
            nonce: @json($googleNonce),
            auto_select: false,
            use_fedcm_for_prompt: true,
            callback: function (response) {
                if (!response.credential) return;
                const form = document.createElement('form'); form.method = 'post'; form.action = @json(route('google.one-tap', ['portal' => $portal]));
                for (const [name, value] of Object.entries({_token: @json(csrf_token()), credential: response.credential})) {
                    const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.append(input);
                }
                document.body.append(form); form.submit();
            }
        });
        google.accounts.id.renderButton(document.getElementById('google-signin-button'), {type:'standard', theme:'outline', size:'large', text:'continue_with', width:Math.min(360, root.clientWidth)});
        root.querySelector('[data-google-status]').textContent = 'Choose your Google account. No website password is required.';
        google.accounts.id.prompt();
    };
    </script>
    <script src="https://accounts.google.com/gsi/client" async defer onload="initializeJournalGoogle()" onerror="document.querySelector('[data-google-status]').textContent='Use Continue with Google below to sign in.'"></script>
    @endpush
@endif
