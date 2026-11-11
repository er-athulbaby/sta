@props(['title', 'phase' => null, 'phaseLabel' => null, 'pill' => null, 'pillLabel' => null])
<span class="sta-heading">{{ $title }}
    @if ($phase)<x-sta.phase-chip :phase="$phase" :label="$phaseLabel" />@endif
    @if ($pill)<x-sta.pill :status="$pill">{{ $pillLabel }}</x-sta.pill>@endif
</span>
