{{-- Canvas Phase2.dc.html header band: breadcrumb, h1 + phase chip, phase stepper. STA edit pages also show their header actions. --}}
@php
    $p = $this->submission->phase;
    [$phaseState] = \App\Support\Sta::phaseStatus($this->gameModel->phase($p));
    $status = $this->submission->status;
    // Phase2.dc.html draws no status pill for a draft; Phase 1 (no canvas) keeps the Long List style "Draft · saved" pill.
    $showPill = $p !== 2 || $status !== \App\Enums\SubmissionStatus::Draft;
    $saved = $status === \App\Enums\SubmissionStatus::Draft && $this->submission->updated_at ? ' · saved '.$this->submission->updated_at->format('H:i') : '';
    $crumbs = $this->getBreadcrumbs();
    $staEdit = filament()->getId() === 'admin';
@endphp
<header class="sta-ph-{{ $p }}" style="display: flex; flex-direction: column; gap: 14px; background: var(--ph-tint); border: 1px solid var(--ph-border); border-radius: 14px; padding: 16px 20px">
    <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">
        @foreach ($crumbs as $url => $label)
            @if (! $loop->first) / @endif
            @if (is_string($url))<a href="{{ $url }}">{{ $label }}</a>@else{{ $label }}@endif
        @endforeach
    </span>
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px">
        <h1 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 700">{{ $title }}</h1>
        <x-sta.phase-chip :phase="$p" :label="'Phase '.$p.' of 4 · '.$phaseState" style="height: 28px" />
        @if ($showPill)
            <x-sta.pill :status="\App\Support\Sta::submissionPill($status)" style="height: 28px; padding: 0 12px">{{ $status->label().$saved }}</x-sta.pill>
        @endif
        @if ($staEdit)
            <div style="margin-left: auto"><x-filament::actions :actions="$this->getCachedHeaderActions()" /></div>
        @endif
    </div>
    <x-sta.stepper :game="$this->gameModel" :current="$p" />
</header>
