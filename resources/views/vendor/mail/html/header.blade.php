@props(['url'])
@php($journal = app(\App\Services\PublicationSettings::class)->site())
<tr><td class="header" style="text-align:center;padding:24px">
<a href="{{ url('/') }}" style="display:inline-block;text-decoration:none"><img src="{{ asset('assets/larix-logo-transparent.png') }}" width="160" alt="{{ $journal['name'] }}" style="width:160px;max-width:100%;height:auto;border:0"><br><span style="font-size:18px;color:#203d37">{{ $journal['name'] }}</span></a>
</td></tr>
