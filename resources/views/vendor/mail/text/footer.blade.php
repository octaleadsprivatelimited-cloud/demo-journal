@php($journal = app(\App\Services\PublicationSettings::class)->site())
© {{ date('Y') }} {{ $journal['name'] }}
Website: {{ url('/') }}
Contact: {{ url('/contact') }}
@foreach($journal['contact_emails'] ?? [] as $email)
{{ $email }}
@endforeach
{{ $journal['phone'] ?? '' }}
{{ $journal['address'] ?? '' }}
