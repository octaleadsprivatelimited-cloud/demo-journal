@extends('layouts.portal')

@section('title', 'Settings')
@section('section', 'Audience & system')
@section('eyebrow', 'Publication configuration')
@section('page-title', 'Settings')
@section('page-description', 'Public website identity, contact details, social profiles, and reader-facing publication controls.')

@section('content')
    @php
        $groupDescriptions = [
            'general' => 'Used throughout the public website and as the default search and social metadata.',
            'social' => 'Published in the website footer when a valid profile URL is present.',
            'publication' => 'Reader-facing switches enforced by both the interface and their public endpoints.',
        ];
        $oldSettings = old('settings', []);
    @endphp

    <div class="portal-form">
        @foreach ($definitions as $group => $fields)
            <section class="portal-card">
                <div class="portal-card-head">
                    <div>
                        <h2>{{ str($group)->headline() }}</h2>
                        <p>{{ $groupDescriptions[$group] }}</p>
                    </div>
                </div>
                <div class="portal-card-body">
                    <form class="portal-form" method="post" action="{{ route('admin.settings.update') }}" data-loading>
                        @csrf
                        @method('put')
                        <input type="hidden" name="group" value="{{ $group }}">

                        <div class="form-grid">
                            @foreach ($fields as $key => $definition)
                                @php
                                    $fieldId = $group.'-'.str_replace('.', '-', $key);
                                    $current = old('group') === $group && is_array($oldSettings) && array_key_exists($key, $oldSettings)
                                        ? $oldSettings[$key]
                                        : $values->get($key, $definition['default']);
                                @endphp

                                @if ($definition['type'] === 'boolean')
                                    <div class="portal-field field-span">
                                        <input type="hidden" name="settings[{{ $key }}]" value="0">
                                        <label class="check-row" for="{{ $fieldId }}">
                                            <input
                                                id="{{ $fieldId }}"
                                                type="checkbox"
                                                name="settings[{{ $key }}]"
                                                value="1"
                                                @checked(filter_var($current, FILTER_VALIDATE_BOOL))
                                            >
                                            <span>{{ $definition['label'] }}</span>
                                        </label>
                                    </div>
                                @elseif ($definition['type'] === 'textarea')
                                    <div class="portal-field field-span">
                                        <label for="{{ $fieldId }}">{{ $definition['label'] }}</label>
                                        <textarea
                                            class="portal-textarea"
                                            id="{{ $fieldId }}"
                                            name="settings[{{ $key }}]"
                                            rows="3"
                                        >{{ $current }}</textarea>
                                    </div>
                                @else
                                    <div class="portal-field">
                                        <label for="{{ $fieldId }}">{{ $definition['label'] }}</label>
                                        <input
                                            class="portal-input"
                                            id="{{ $fieldId }}"
                                            name="settings[{{ $key }}]"
                                            type="{{ $definition['type'] }}"
                                            value="{{ $current }}"
                                        >
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        @if (old('group') === $group)
                            <x-portal.field-error name="settings" />
                        @endif

                        <div class="form-actions">
                            <button class="portal-button primary" type="submit">Save {{ $group }}</button>
                        </div>
                    </form>
                </div>
            </section>
        @endforeach

        <section class="portal-card">
            <div class="portal-card-head">
                <div>
                    <h2>Environment-managed configuration</h2>
                    <p>Outbound mail transport and sender identity, analytics and site verification, CAPTCHA integrations, login rate limits, and session lifetime are deployment settings.</p>
                </div>
            </div>
            <div class="portal-card-body">
                <p>Update these values in the deployment environment and rebuild the configuration cache. They are intentionally read-only here because changing them safely may require credentials, infrastructure changes, or an application restart.</p>
            </div>
        </section>
    </div>
@endsection
