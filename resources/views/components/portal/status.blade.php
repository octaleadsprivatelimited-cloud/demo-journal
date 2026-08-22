@props(['value'])
@php $raw = $value instanceof \BackedEnum ? $value->value : (string) $value; @endphp
<span {{ $attributes->class(['status-pill', 'status-'.str($raw)->replace('_','-')]) }}><i aria-hidden="true"></i>{{ str($raw)->replace('_', ' ')->headline() }}</span>
