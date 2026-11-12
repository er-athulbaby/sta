{{-- Canvas consent / declaration row: title and description, "Agreed on {date}" / "Not agreed", and a 52x44 switch.
     The date is the saved {field}_at stamp (Person sets it when the consent turns on); a new tick shows today until saved. --}}
@php
    $statePath = $getStatePath();
    $labelId = $getId().'-label';
    $saved = $field->getRecord()?->{$field->getName().'_at'};
@endphp
<div x-data="{ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }}, saved: @js($saved?->format('j M Y')), today: @js(now()->format('j M Y')) }"
    class="sta-consent-row" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 16px; padding: 14px 0">
    <div style="flex: 1 1 280px; min-width: 0; display: flex; flex-direction: column; gap: 2px">
        <span id="{{ $labelId }}" style="font-weight: 600">{{ $getLabel() }}</span>
        <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">{{ $description }}</span>
    </div>
    <div style="display: flex; align-items: center; gap: 12px">
        <span x-text="state ? 'Agreed on ' + (saved || today) : 'Not agreed'" x-bind:style="'font-size: 13px; font-weight: 600; color: ' + (state ? 'var(--sta-fg-green-800, #166534)' : 'var(--sta-fg-muted, #475569)')"></span>
        <button type="button" role="switch" id="{{ $getId() }}" aria-labelledby="{{ $labelId }}" @disabled($isDisabled())
            x-bind:aria-checked="state ? 'true' : 'false'" x-on:click="state = ! state"
            style="width: 52px; height: 44px; padding: 0; border: 0; background: transparent; display: flex; align-items: center; cursor: pointer">
            <span x-bind:style="'position: relative; display: inline-block; width: 44px; height: 26px; border-radius: 999px; flex-shrink: 0; background: ' + (state ? 'var(--sta-bg-brand, #1F3A5F)' : 'var(--sta-bg-faint, #94A3B8)')"><span x-bind:style="'position: absolute; top: 3px; left: ' + (state ? '21px' : '3px') + '; width: 20px; height: 20px; border-radius: 50%; background: var(--sta-bg-surface, #FFFFFF)'"></span></span>
        </button>
    </div>
</div>
