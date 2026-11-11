@props(['phase', 'name' => '', 'status' => 'none', 'statusLabel' => '', 'meta' => null, 'active' => false, 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['sta-phase-card', 'is-active' => $active, 'is-link' => $href]) }} @if ($active) aria-current="true" @endif>
    <div class="sta-row-between">
        <x-sta.phase-chip :phase="$phase" />
        <x-sta.pill :status="$status">{{ $statusLabel }}</x-sta.pill>
    </div>
    <span class="sta-phase-card-name">{{ $name }}</span>
    @if (filled($meta))<span class="sta-muted">{{ $meta }}</span>@endif
    {{ $slot }}
</{{ $tag }}>
