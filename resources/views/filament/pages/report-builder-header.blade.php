{{-- Canvas AdminReportBuilder.dc.html header: breadcrumb, the report name as an inline-editable h1, then Share, Export to
     Excel, Save as copy, Save and Delete. Save, Share and Delete only for someone who may edit the report. --}}
@php
    $canEdit = $this->canEdit();
    $saved = $this->reportId ? \App\Models\SavedReport::find($this->reportId) : null;
    $outline = 'min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; gap: 8px; cursor: pointer';
@endphp
<header style="display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px">
    <div style="display: flex; flex-direction: column; gap: 6px; flex: 1 1 360px; min-width: 0">
        <nav aria-label="Breadcrumb" style="font-size: 13px; color: var(--sta-fg-muted, #475569)"><a href="{{ \App\Filament\Pages\Reports::getUrl() }}" style="color: var(--sta-fg-brand, #1F3A5F)">Reports</a> / Report builder</nav>
        <label style="display: flex; flex-direction: column; gap: 4px">
            <span style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0)">Report name</span>
            <input type="text" wire:model="name" maxlength="120" @disabled(! $canEdit) class="sta-title-input"
                style="min-height: 48px; padding: 0 12px; margin-left: -12px; border: 1px solid transparent; border-radius: 10px; background: transparent; font: 700 28px 'Space Grotesk', sans-serif; color: var(--sta-fg-text, #0F172A); max-width: 100%">
        </label>
        <span style="color: var(--sta-fg-muted, #475569)">{{ $saved ? 'Saved '.lcfirst(\App\Support\Sta::when($saved->updated_at)) : 'Not saved yet' }} · {{ $this->subject()?->label() ?? 'No subject' }}</span>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center">
        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: var(--sta-fg-strong, #334155)">Share
            <select wire:model="visibility" @disabled(! $canEdit) style="min-height: 44px; padding: 0 12px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; background: var(--sta-bg-surface, #FFFFFF); font: 14px Inter, sans-serif; color: var(--sta-fg-text, #0F172A)">
                @foreach (\App\Models\SavedReport::VISIBILITIES as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button type="button" wire:click="export" style="{{ $outline }}">
            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
            Export to Excel
        </button>
        @if ($this->reportId)
            <button type="button" wire:click="saveAsCopy" style="{{ $outline }}">Save as copy</button>
        @endif
        @if ($canEdit)
            <button type="button" wire:click="save" style="min-height: 44px; padding: 0 18px; border-radius: 10px; border: 0; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF); font: 600 14px Inter, sans-serif; cursor: pointer">Save</button>
        @endif
        @if ($this->reportId && $canEdit)
            <button type="button" wire:click="delete" wire:confirm="Delete this report? This cannot be undone." style="min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid var(--sta-bd-red-200, #FECACA); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-red-700, #B91C1C); font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; gap: 8px; cursor: pointer">
                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M6 7l1 13h10l1-13"/><path d="M9 7V4h6v3"/></svg>
                Delete
            </button>
        @endif
    </div>
</header>
