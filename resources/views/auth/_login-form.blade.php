@if(config('services.google.enabled') && config('services.google.only'))
@include('auth._google-oauth', ['portal' => $portal])
<p class="portal-help">Use your Google account to sign in or create an application. No website password is needed.</p>
<p class="portal-help"><a href="{{ route('password.request') }}">Reset your website password</a></p>
<a href="{{ route('login') }}">Choose another workspace</a>
@else
<form class="portal-form" method="post" action="{{ route($submitRoute) }}" data-loading>@csrf
    <div class="portal-field"><label for="email">Email address</label><input class="portal-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus aria-describedby="email-error"><x-portal.field-error name="email" /></div>
    <div class="portal-field"><div style="display:flex;justify-content:space-between"><label for="password">Password</label><a href="{{ route('password.request') }}" style="font-size:.72rem;color:var(--portal-green-2);font-weight:700">Forgot password?</a></div><input class="portal-input" id="password" name="password" type="password" autocomplete="current-password" required aria-describedby="password-error"><x-portal.field-error name="password" /></div>
    <div class="check-row"><input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))><label for="remember">Keep me signed in on this trusted device</label></div>
    <button class="portal-button primary" type="submit" data-loading-text="Signing in…">Sign in to {{ $portalLabel }} <x-portal.icon name="arrow" :size="17" /></button>
        @include('auth._google-oauth', ['portal' => $portal])
    <div class="auth-links">
        @isset($registrationRoute)<span>Need an account? <a href="{{ route($registrationRoute) }}">Create one</a></span>@endisset
        <a href="{{ route('login') }}">Choose another workspace</a>
    </div>
</form>

@endif
