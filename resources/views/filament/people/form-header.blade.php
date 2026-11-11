{{-- Canvas PlayerForm.dc.html / OfficialForm.dc.html header card: photo, breadcrumb, name, chips (phase, code, Complete /
     Incomplete), "Missing: …" and Cancel / Save as draft / Save (the page's own create/save; Save also returns to the list). --}}
@php
    $person = $this->personRecord();
    $type = $this->personType();
    $official = $type === \App\Enums\PersonType::Official;
    $resource = static::getResource();
    $listLabel = filament()->getId() === 'portal' ? 'Athletes & officials' : 'People';
    $name = $person?->fullName() ?: ($official ? 'New team official' : 'New athlete');
    $crumb = $person ? ($official ? 'Edit team official' : 'Edit athlete') : 'Add person';
    $missing = $person?->missingFields() ?? [];
    $lower = fn (string $s) => preg_match('/^[A-Z][a-z]/', $s) ? lcfirst($s) : $s;
    $phase = $this->personPhase();
    $photo = $person?->photo_path ? \App\Filament\Shared\AttachmentActions::url($person->photo_path) : null;
    $secondary = 'min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; text-decoration: none; box-sizing: border-box; cursor: pointer';
@endphp
<header style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; padding: 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px 24px">
    <div style="flex: 1 1 420px; min-width: 0; display: flex; align-items: center; gap: 16px">
        @if ($photo)
            <img src="{{ $photo }}" alt="Photo of {{ $name }}" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; flex-shrink: 0">
        @else
            <div role="img" aria-label="Photo of {{ $name }}" style="width: 64px; height: 64px; border-radius: 50%; background: #2B4C7E; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 22px; flex-shrink: 0">{{ $person ? \App\Support\Sta::initials($name) : '' }}</div>
        @endif
        <div style="display: flex; flex-direction: column; gap: 6px; min-width: 0">
            <span style="font-size: 13px; color: #475569"><a href="{{ $resource::getUrl('index') }}">{{ $listLabel }}</a> / {{ $crumb }}</span>
            <h1 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 26px; font-weight: 700; letter-spacing: -0.01em">{{ $name }}</h1>
            @if ($person)
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px">
                    @if ($phase)
                        <span class="sta-ph-{{ $phase }}" style="display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: var(--ph-bg); color: var(--ph-fg); white-space: nowrap"><span aria-hidden="true" style="width: 8px; height: 8px; border-radius: 50%; background: var(--ph-dot); flex-shrink: 0"></span>Phase {{ $phase }} · {{ $phase === 3 ? 'Long List' : 'Short List' }}</span>
                    @endif
                    <span style="display: inline-flex; align-items: center; height: 26px; padding: 0 10px; border-radius: 8px; background: #E8EEF6; color: #1F3A5F; font: 600 13px 'Space Grotesk', sans-serif; letter-spacing: 0.02em">{{ $person->code }}</span>
                    <span style="display: inline-flex; align-items: center; height: 26px; padding: 0 10px; border-radius: 999px; font-size: 12px; font-weight: 600; {{ $missing ? 'background: #FFEDD5; color: #9A3412' : 'background: #DCFCE7; color: #166534' }}">{{ $missing ? 'Incomplete' : 'Complete' }}</span>
                    @if ($missing)
                        <span style="font-size: 13px; color: #9A3412">Missing: {{ implode(', ', array_map($lower, $missing)) }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 12px">
        <a href="{{ $resource::getUrl('index') }}" style="{{ $secondary }}">Cancel</a>
        <button type="button" wire:click="{{ $this->personSubmitMethod() }}" wire:loading.attr="disabled" style="{{ $secondary }}">Save as draft</button>
        <button type="button" wire:click="saveAndClose" wire:loading.attr="disabled" style="min-height: 44px; padding: 0 20px; border-radius: 10px; border: 0; background: #1F3A5F; color: #FFFFFF; font: 600 14px Inter, sans-serif; cursor: pointer">Save</button>
    </div>
</header>
