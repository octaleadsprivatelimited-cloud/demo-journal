@php
    $popupErrors = $popupErrors ?? collect(isset($errors) ? $errors->all() : [])->map(fn ($message) => [
        'code' => \App\Support\ErrorCodes::fromMessage($message),
        'message' => preg_replace('/^\[[a-z_]+\]\s*/', '', $message),
    ])->all();
@endphp
<script type="application/json" data-journal-errors>{!! json_encode($popupErrors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script>{!! file_get_contents(resource_path('js/error-popup.js')) !!}</script>
