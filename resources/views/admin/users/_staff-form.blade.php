<form class="portal-form" method="post" action="{{ route('admin.users.store') }}" data-loading>
    @csrf
    <input type="hidden" name="roles[]" value="{{ $staffRole->id }}">
    <input type="hidden" name="is_active" value="1">
    <input type="hidden" name="email_verified" value="0">
    <p class="admin-help-text">Creating an <strong>{{ $staffRole->name }}</strong> login. A verification link is emailed after creation. Share the password with the person through your usual secure channel.</p>
    <x-portal.field-error name="roles" />
    <div class="form-grid">
        <div class="portal-field"><label for="name">Full name</label><input class="portal-input" id="name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="120" required><x-portal.field-error name="name" /></div>
        <div class="portal-field"><label for="email">Email address</label><input class="portal-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required><x-portal.field-error name="email" /></div>
        <div class="portal-field"><label for="password">Password</label><input class="portal-input" id="password" name="password" type="password" autocomplete="new-password" minlength="9" required><small>At least 9 characters, with uppercase, lowercase, a number and a symbol.</small><x-portal.field-error name="password" /></div>
        <div class="portal-field"><label for="password_confirmation">Confirm password</label><input class="portal-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="9" required><x-portal.field-error name="password_confirmation" /></div>
    </div>
    <details class="admin-help" @if($errors->hasAny(['phone','organization','designation'])) open @endif>
        <summary>Professional details <span>optional</span></summary>
        <div class="form-grid">
            <div class="portal-field"><label for="organization">Organization</label><input class="portal-input" id="organization" name="organization" value="{{ old('organization') }}" maxlength="160"><x-portal.field-error name="organization" /></div>
            <div class="portal-field"><label for="designation">Designation</label><input class="portal-input" id="designation" name="designation" value="{{ old('designation') }}" maxlength="120"><x-portal.field-error name="designation" /></div>
            <div class="portal-field"><label for="phone">Phone</label><input class="portal-input" id="phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="32"><x-portal.field-error name="phone" /></div>
        </div>
    </details>
    <div class="form-actions"><a class="portal-button" href="{{ route('admin.users.index') }}">Cancel</a><button class="portal-button primary" type="submit">Create {{ $staffRole->slug }} account</button></div>
</form>
