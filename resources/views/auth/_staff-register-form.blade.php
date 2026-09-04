@if(config('services.google.enabled') && config('services.google.only'))
@include('auth._google-oauth', ['portal' => $portal])
<p class="portal-help">Use your Google account to sign in or create an application. No website password is needed.</p>
<a href="{{ route('login') }}">Choose another workspace</a>
@else
<form class="portal-form" method="post" action="{{ route($submitRoute) }}" data-loading>@csrf
    <div class="form-grid">
        <div class="portal-field"><label for="name">Full name</label><input class="portal-input" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required><x-portal.field-error name="name" /></div>
        <div class="portal-field"><label for="email">Email address</label><input class="portal-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required><x-portal.field-error name="email" /></div>
        <div class="portal-field"><label for="phone">Phone <span>optional</span></label><input class="portal-input" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel"><x-portal.field-error name="phone" /></div>
        <div class="portal-field"><label for="organization">Institution / organization</label><input class="portal-input" id="organization" name="organization" value="{{ old('organization') }}" autocomplete="organization"><x-portal.field-error name="organization" /></div>
        <div class="portal-field field-span"><label for="designation">Designation</label><input class="portal-input" id="designation" name="designation" value="{{ old('designation') }}" placeholder="Managing Editor, Publication Administrator…"><x-portal.field-error name="designation" /></div>
        <div class="portal-field"><label for="password">Password</label><input class="portal-input" id="password" name="password" type="password" autocomplete="new-password" required><p class="portal-help">At least 10 characters with upper/lowercase, a number and symbol.</p><x-portal.field-error name="password" /></div>
        <div class="portal-field"><label for="password_confirmation">Confirm password</label><input class="portal-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
    </div>
    <div class="check-row"><input id="terms" name="terms" type="checkbox" value="1" required @checked(old('terms'))><label for="terms">I agree to the publication terms, security requirements, and privacy notice.</label></div><x-portal.field-error name="terms" />
    <p class="portal-help">Your account is activated after a quick review by the website super-admin.</p>
    <button class="portal-button primary" type="submit" data-loading-text="Creating account…">Create {{ strtolower($roleLabel) }} account</button>
    @include('auth._google-oauth', ['portal' => $portal])
    <div class="auth-links"><span>Already approved? <a href="{{ route($loginRoute) }}">Sign in</a></span><a href="{{ route('register') }}">Choose another account type</a></div>
</form>

@endif
