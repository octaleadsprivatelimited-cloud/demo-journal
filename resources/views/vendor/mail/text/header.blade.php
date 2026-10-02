@props(['url'])
{{ app(\App\Services\PublicationSettings::class)->site()['name'] }}
{{ url('/') }}
