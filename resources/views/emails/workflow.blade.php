<p>Hello {{ $payload['recipient_name'] ?? 'there' }},</p>
<p>{{ $payload['message'] ?? 'There is an update in the journal workflow.' }}</p>
<p><strong>{{ $payload['manuscript_title'] ?? '' }}</strong><br>
Manuscript ID: {{ $payload['manuscript_id'] ?? '' }}<br>
Current status: {{ $payload['status'] ?? '' }}</p>
@if(!empty($payload['action_url']))
<p><a href="{{ $payload['action_url'] }}">Open in the journal dashboard</a></p>
@endif
<p>{{ $payload['journal_name'] ?? config('app.name') }}</p>
