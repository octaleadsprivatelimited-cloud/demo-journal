@if(session('success') || session('error') || $errors->any())
    <div class="portal-toast-region" aria-live="polite" data-toast>
        <div @class(['portal-toast', 'is-error' => session('error') || $errors->any()])>
            <x-portal.icon :name="session('error') || $errors->any() ? 'alert' : 'check'" />
            <div><strong>{{ session('error') || $errors->any() ? 'Please check this request' : 'Done' }}</strong><p>{{ session('success') ?? session('error') ?? $errors->first() }}</p></div>
            <button type="button" aria-label="Dismiss" data-toast-close><x-portal.icon name="close" :size="16" /></button>
        </div>
    </div>
@endif
