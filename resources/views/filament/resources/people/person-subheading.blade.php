@php $missing = $person->missingFields(); @endphp
<span class="sta-heading">
    <span class="sta-tag">{{ $person->code }}</span>
    <x-sta.pill :status="$missing ? 'warn' : 'ok'">{{ $missing ? 'Incomplete' : 'Complete' }}</x-sta.pill>
    @if ($missing)<span class="sta-tone-warn" style="font-size: 13px">Missing: {{ implode(', ', $missing) }}</span>@endif
</span>
