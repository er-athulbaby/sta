@props(['label', 'value' => null, 'max' => null])
@php
    $value = $value ?? 0;
    $hasMax = $max !== null && (int) $max > 0;
    $over = $hasMax && $value > $max;
    $pct = $hasMax ? min(100, (int) round($value / (int) $max * 100)) : 0;
@endphp
<div {{ $attributes->class(['sta-meter', 'is-over' => $over]) }}>
    <div class="sta-meter-row">
        <span class="sta-meter-label">{{ $label }}</span>
        <span class="sta-meter-value">{{ $value }}{{ $max !== null ? ' of '.$max : '' }}</span>
    </div>
    @if ($hasMax)
        <div class="sta-meter-track" role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="{{ $max }}" aria-valuenow="{{ $value }}" aria-valuetext="{{ $value }} of {{ $max }}">
            <div class="sta-meter-bar" style="width: {{ $pct }}%"></div>
        </div>
    @endif
</div>
