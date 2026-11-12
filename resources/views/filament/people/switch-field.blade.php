{{-- Canvas switch (PlayerForm "WhatsApp is the same as mobile"): a 44x26 track inside a 44px button, the label beside it. --}}
@php $statePath = $getStatePath(); @endphp
<div style="display: flex; align-items: flex-end; height: 100%">
    <button type="button" role="switch" id="{{ $getId() }}" @disabled($isDisabled())
        x-data="{ state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }} }"
        x-bind:aria-checked="state ? 'true' : 'false'" x-on:click="state = ! state"
        style="display: inline-flex; align-items: center; gap: 12px; min-height: 44px; padding: 0; border: 0; background: transparent; color: var(--sta-fg-text, #0F172A); font: 600 14px Inter, sans-serif; cursor: pointer; text-align: left">
        <span x-bind:style="'position: relative; display: inline-block; width: 44px; height: 26px; border-radius: 999px; flex-shrink: 0; background: ' + (state ? 'var(--sta-bg-brand, #1F3A5F)' : 'var(--sta-bg-faint, #94A3B8)')"><span x-bind:style="'position: absolute; top: 3px; left: ' + (state ? '21px' : '3px') + '; width: 20px; height: 20px; border-radius: 50%; background: var(--sta-bg-surface, #FFFFFF)'"></span></span>
        {{ $getLabel() }}
    </button>
</div>
