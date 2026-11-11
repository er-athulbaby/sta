@props(['label', 'value', 'hint' => null, 'tone' => null])
<div {{ $attributes->class('sta-kpi') }}>
    <span class="sta-kpi-label">{{ $label }}</span>
    <span @class(['sta-kpi-value', 'sta-tone-'.$tone => $tone])>{{ $value }}</span>
    @if (filled($hint))<span class="sta-kpi-hint">{{ $hint }}</span>@endif
</div>
