@props(['name'])
@error($name)<p class="portal-field-error" id="{{ str($name)->slug() }}-error">{{ $message }}</p>@enderror
