@props(['game', 'current'])
<ol {{ $attributes->class('sta-stepper') }} aria-label="Phases">
    @foreach ($game->phases->sortBy('phase') as $p)
        <li class="sta-ph-{{ $p->phase }}" @if ($p->phase === (int) $current) aria-current="step" @endif>{{ $p->phase }} · {{ \App\Models\GamePhase::NAMES[$p->phase] }}@unless ($p->enabled) — skipped @endunless</li>
    @endforeach
</ol>
