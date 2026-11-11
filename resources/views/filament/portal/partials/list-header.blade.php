{{-- Canvas LongList.dc.html / LongListEvents.dc.html / ShortList.dc.html header band (phases 3 and 4): breadcrumb, h1 + "Phase N"
     chip + status pill, the deadline line, and Preview PDF (portal Long List and Short List only) / Save as draft / Submit.
     $eventsScreen: the LongListEvents variant (its subtitle, no Preview PDF, the note for editors). The buttons call the page's
     existing actions; STA edit pages show their own header actions (Back to submission) and save only. --}}
@php
    $p = $this->submission->phase;
    $status = $this->submission->status;
    $saved = $status === \App\Enums\SubmissionStatus::Draft && $this->submission->updated_at ? ' · saved '.$this->submission->updated_at->format('H:i') : '';
    $portal = filament()->getId() === 'portal';
    $crumbs = $this->getBreadcrumbs();
    $deadline = $this->submission->gamePhase()->effectiveDeadline($this->submission);
    $line = [$deadline ? 'Deadline '.$deadline->format('j M Y') : 'No deadline set', \App\Support\Sta::daysLeft($deadline)];
    if ($eventsScreen ?? false) {
        $line[] = 'Only sports you entered in Phase 1 are shown';
    } elseif ($p === 3) {
        $items = $this->gameModel->clothingItems->pluck('name');
        $line[] = $items->isNotEmpty() ? 'Clothing items for this game: '.$items->implode(', ') : null;
    } else {
        $line[] = 'Choose from your submitted Long List';
    }
    $can = fn (string $name) => $portal && ($this->getAction($name, isMounting: false)?->isVisible() ?? false);
    $preview = $portal && ! ($eventsScreen ?? false) && ($p === 3 || $this->longListSubmitted());
    $editorNote = ($eventsScreen ?? false) && $portal && auth()->user()->role !== \App\Enums\Role::AssocAdmin;
    $secondary = 'min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 14px Inter, sans-serif; cursor: pointer';
    $primary = 'min-height: 44px; padding: 0 18px; border-radius: 10px; border: 0; background: #1F3A5F; color: #FFFFFF; font: 600 14px Inter, sans-serif; cursor: pointer';
@endphp
<header class="sta-ph-{{ $p }}" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 16px; background: var(--ph-tint); border: 1px solid var(--ph-border); border-radius: 14px; padding: 16px 20px">
    <div style="display: flex; flex-direction: column; gap: 8px">
        <span style="font-size: 13px; color: #475569">
            @foreach ($crumbs as $url => $label)
                @if (! $loop->first) / @endif
                @if (is_string($url))<a href="{{ $url }}">{{ $label }}</a>@else{{ $label }}@endif
            @endforeach
            @if ($portal) / Phase {{ $p }}@endif
        </span>
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px">
            <h1 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 700">{{ $title }}</h1>
            <x-sta.phase-chip :phase="$p" style="height: 28px" />
            <x-sta.pill :status="\App\Support\Sta::submissionPill($status)" style="height: 28px; padding: 0 12px">{{ $status->label().$saved }}</x-sta.pill>
        </div>
        <span style="color: #475569">{{ implode(' · ', array_filter($line)) }}</span>
    </div>
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px">
        @unless ($portal)
            <x-filament::actions :actions="$this->getCachedHeaderActions()" />
        @endunless
        @if ($preview)
            <button type="button" wire:click="previewPdf" wire:loading.attr="disabled" wire:target="previewPdf" style="{{ $secondary }}">Preview PDF</button>
        @endif
        @unless ($this->readOnlyReason)
            <button type="button" wire:click="save" style="{{ $secondary }}">Save as draft</button>
        @endunless
        @if ($can('submit'))
            <button type="button" wire:click="mountAction('submit')" style="{{ $primary }}">{{ $this->getAction('submit', isMounting: false)->getLabel() }}</button>
        @endif
        @if ($can('return'))
            <button type="button" wire:click="mountAction('return')" style="{{ $secondary }}">Return to draft</button>
        @endif
        @if ($can('approve'))
            <button type="button" wire:click="mountAction('approve')" style="{{ $primary }}">Approve and submit to STA</button>
        @endif
        @if ($editorNote && $can('submit'))
            <span style="flex-basis: 100%; text-align: right; font-size: 12px; color: #475569">The association admin sees <strong>Submit to STA</strong> here.</span>
        @endif
    </div>
</header>
