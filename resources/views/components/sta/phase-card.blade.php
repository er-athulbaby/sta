@props(['phase', 'name', 'status', 'statusLabel', 'meta' => null, 'active' => false])
<div {{ $attributes->class(['sta-phase-card', 'is-active' => $active]) }}>
    <div class="sta-row-between">
        <x-sta.phase-chip :phase="$phase" />
        <x-sta.pill :status="$status">{{ $statusLabel }}</x-sta.pill>
    </div>
    <span class="sta-phase-card-name">{{ $name }}</span>
    @if (filled($meta))<span class="sta-muted">{{ $meta }}</span>@endif
    {{ $slot }}
</div>
