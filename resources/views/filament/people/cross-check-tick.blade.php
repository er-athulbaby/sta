{{-- The required cross-check tick of the side-panel forms (Long List short form, registry aside); the full form uses
     PersonForm's Checkbox. Never pre-ticked: the page resets it after every save. $model: wire:model path; $note: "Cross-checked by …". --}}
<div style="display: flex; flex-direction: column; gap: 6px">
    <label style="display: flex; align-items: flex-start; gap: 10px; min-height: 44px; padding: 10px 12px; box-sizing: border-box; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface-alt, #F8FAFC); font-size: 13px; font-weight: 600; cursor: pointer">
        <input type="checkbox" wire:model="{{ $model }}" @error($model) aria-invalid="true" @enderror style="width: 18px; height: 18px; margin: 0; flex-shrink: 0; accent-color: var(--sta-bg-brand, #1F3A5F)">
        <span>{{ \App\Models\Person::CROSS_CHECK_LABEL }}</span>
    </label>
    @error($model)<span role="alert" style="font-size: 12px; font-weight: 600; color: var(--sta-fg-red-700, #B91C1C)">{{ $message }}</span>@enderror
    @if (filled($note ?? null))
        <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ $note }}</span>
    @endif
</div>
