@php($journal = app(\App\Services\PublicationSettings::class)->site())
<tr><td><table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation"><tr><td class="content-cell" align="center" style="font-size:12px;color:#666">
<p>&copy; {{ date('Y') }} {{ $journal['name'] }}. All rights reserved.</p>
<p><a href="{{ url('/') }}">Visit website</a> · <a href="{{ url('/contact') }}">Contact the journal</a></p>
@foreach($journal['contact_emails'] ?? [] as $email)<p><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endforeach
@if(!empty($journal['phone']))<p>{{ $journal['phone'] }}</p>@endif
@if(!empty($journal['address']))<p>{{ $journal['address'] }}</p>@endif
</td></tr></table></td></tr>
