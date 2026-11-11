@props(['label', 'value', 'max' => null])
@php
    $over = $max !== null && $value > $max;
    $pct = $max !== null ? min(100, (int) round($value / max((int) $max, 1) * 100)) : 0;
@endphp
<div {{ $attributes->class(['sta-meter', 'is-over' => $over]) }}>
    <div class="sta-meter-row">
        <span class="sta-meter-label">{{ $label }}</span>
        <span class="sta-meter-value">{{ $value }}{{ $max !== null ? ' of '.$max : '' }}</span>
    </div>
    @if ($max !== null)
        <div class="sta-meter-track" role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="{{ $max }}" aria-valuenow="{{ $value }}">
            <div class="sta-meter-bar" style="width: {{ $pct }}%"></div>
        </div>
    @endif
</div>
