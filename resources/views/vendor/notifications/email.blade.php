@php
$type = $templateType ?? \App\Services\EmailPresentation::type(($subject ?? '').' '.implode(' ', $introLines));
$design = \App\Services\EmailPresentation::designs()[$type];
@endphp
<x-mail::message>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;border-top:6px solid {{ $design[2] }}"><tr><td style="padding:20px 0 8px;color:{{ $design[2] }};font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase">{{ $design[0] }}</td></tr><tr><td style="font-size:28px;line-height:1.25;font-weight:bold;color:#182638;padding-bottom:14px">{{ $design[1] }}</td></tr></table>
@if(!empty($isPreview))
<p style="font-size:12px;color:#68758a">SAMPLE PREVIEW — example content; the button does not perform an account or manuscript action.</p>
@endif
@if(!empty($greeting))
{{ $greeting }}

@endif
@foreach($introLines as $line)
{{ $line }}

@endforeach
@if(in_array($type, ['submission', 'revision', 'proof', 'acceptance', 'newsletter']))
<x-mail::panel>
{{ $design[3] }}
</x-mail::panel>
@endif
@isset($actionText)
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0"><tr><td style="background:{{ $design[2] }};border-radius:6px"><a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 24px;color:#ffffff;text-decoration:none;font-weight:bold">{{ $actionText }}</a></td></tr></table>
@endisset
@foreach($outroLines as $line)
{{ $line }}

@endforeach
@if(!in_array($type, ['submission', 'revision', 'proof', 'acceptance', 'newsletter']))
<p style="border-left:3px solid {{ $design[2] }};padding:10px 14px;color:#526078;font-size:13px">{{ $design[3] }}</p>
@endif
{{ $salutation ?: 'Regards, '.config('app.name') }}
@isset($actionText)
<x-slot:subcopy>
If the button does not work, copy this address into your browser:
<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
