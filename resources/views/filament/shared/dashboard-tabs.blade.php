{{-- Admin home and portal home: "Games dashboard" (the original content) / "General dashboard"; the choice stays in ?tab=. --}}
<div role="tablist" aria-label="Dashboard" style="display: flex; flex-wrap: wrap; gap: 4px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
    @foreach (['games' => 'Games dashboard', 'general' => 'General dashboard'] as $key => $label)
        <button type="button" role="tab" class="sta-tab" aria-selected="{{ $this->tab === $key || ($key === 'games' && $this->tab !== 'general') ? 'true' : 'false' }}"
            wire:click="$set('tab', '{{ $key }}')">{{ $label }}</button>
    @endforeach
</div>
