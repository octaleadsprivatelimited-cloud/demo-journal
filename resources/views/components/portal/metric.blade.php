@props(['label', 'value', 'hint' => null, 'tone' => 'default'])
<article class="metric-card tone-{{ $tone }}">
    <p>{{ $label }}</p><strong>{{ is_numeric($value) ? number_format((float) $value) : $value }}</strong>
    @if($hint)<small>{{ $hint }}</small>@endif
</article>
