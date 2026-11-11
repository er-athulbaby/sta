<x-filament-panels::page>
@php
    $all = $this->associations();
    $shown = $this->visible();
    $invitedCount = $shown->whereIn('id', $invited)->count();
    $sportsCount = $shown->whereIn('id', $invited)->sum(fn ($a) => $a->sports->whereIn('id', $picked)->count());
    $logoUrl = $this->logoUrl();
    $inputStyle = 'min-height: 44px; padding: 0 12px; border: 1px solid #CBD5E1; border-radius: 10px; font: 14px Inter, sans-serif';
@endphp
<div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start">

  <section aria-labelledby="details-title" style="flex: 1 1 340px; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; padding: 20px; display: flex; flex-direction: column; gap: 16px">
    <h2 id="details-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 18px; font-weight: 600">Game details</h2>
    <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600">Name
      <input wire:model="name" style="{{ $inputStyle }}">
      @error('name') <span role="alert" style="font-weight: 400; color: #991B1B">{{ $message }}</span> @enderror
    </label>
    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px">
      <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600">Start date
        <input type="date" wire:model="start_date" style="{{ $inputStyle }}">
        @error('start_date') <span role="alert" style="font-weight: 400; color: #991B1B">{{ $message }}</span> @enderror
      </label>
      <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600">End date
        <input type="date" wire:model="end_date" style="{{ $inputStyle }}">
        @error('end_date') <span role="alert" style="font-weight: 400; color: #991B1B">{{ $message }}</span> @enderror
      </label>
    </div>
    <div style="display: flex; flex-direction: column; gap: 6px">
      <span style="font-size: 13px; font-weight: 600">Game logo</span>
      <div style="display: flex; align-items: center; gap: 14px; padding: 14px; border: 1px dashed #94A3B8; border-radius: 12px">
        @if ($logoUrl)
          <img src="{{ $logoUrl }}" alt="Game logo" style="width: 64px; height: 64px; border-radius: 12px; object-fit: contain; background: #F1F5F9">
        @else
          <div role="img" aria-label="Logo placeholder" style="width: 64px; height: 64px; border-radius: 12px; background: #F1F5F9; color: #475569; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600">[LOGO]</div>
        @endif
        <div style="display: flex; flex-direction: column; gap: 6px">
          <label style="align-self: flex-start; display: inline-flex; align-items: center; min-height: 40px; padding: 0 14px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 13px Inter, sans-serif; cursor: pointer">Upload logo
            <input type="file" wire:model="logo" accept="image/png,image/jpeg" class="sta-sr-only">
          </label>
          <span style="font-size: 12px; color: #475569">PNG or JPG, printed on every PDF</span>
          @error('logo') <span role="alert" style="font-size: 12px; color: #991B1B">{{ $message }}</span> @enderror
        </div>
      </div>
    </div>
    <div style="padding: 12px 14px; border-radius: 10px; background: #E0F2FE; color: #075985; font-size: 13px; line-height: 1.5">Phase 1 (Entry by Sport) uses the list on the right: each invited association answers Yes / No / Maybe for its ticked sports. If Phase 1 is skipped, these sports go straight to Phase 2.</div>
  </section>

  <section aria-labelledby="invite-title" style="flex: 999 1 560px; min-width: 0; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 20px; border-bottom: 1px solid #E2E8F0">
      <div style="display: flex; flex-direction: column; gap: 2px">
        <h2 id="invite-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 18px; font-weight: 600">Invited associations &amp; sports</h2>
        <span style="font-size: 13px; color: #475569">{{ $invitedCount }} of {{ $shown->count() }} shown invited · {{ $sportsCount }} sports ticked</span>
      </div>
      <div style="display: flex; flex-wrap: wrap; gap: 8px">
        <label style="display: flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 12px; border: 1px solid #CBD5E1; border-radius: 10px">
          <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <span style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0)">Search associations</span>
          <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search associations" style="border: 0; outline: none; font: 14px Inter, sans-serif; width: 170px; background: transparent">
        </label>
        <button type="button" wire:click="selectAll" style="min-height: 44px; padding: 0 14px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 13px Inter, sans-serif; cursor: pointer">Select all</button>
      </div>
    </div>
    <ul style="list-style: none; margin: 0; padding: 0">
      @foreach ($shown as $a)
        @php $on = in_array($a->id, $invited, true); @endphp
        <li wire:key="assoc-{{ $a->id }}" style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 10px 20px; border-top: 1px solid #EEF2F6{{ $on ? '' : '; background: #FAFBFC' }}">
          <label style="display: flex; align-items: center; gap: 12px; min-height: 44px; flex: 1 1 280px; cursor: pointer">
            <input type="checkbox" @checked($on) wire:click="toggleAssociation({{ $a->id }})" style="width: 20px; height: 20px; accent-color: #1F3A5F">
            <x-sta.assoc-avatar :association="$a" size="invite" />
            <span style="display: flex; flex-direction: column">
              <span style="font-weight: 600">{{ $a->name }}</span>
              <span style="font-size: 12px; color: #475569">{{ $a->email }}</span>
            </span>
          </label>
          <div role="group" aria-label="Sports of {{ $a->name }}" style="display: flex; flex-wrap: wrap; gap: 6px; flex: 1 1 260px; justify-content: flex-end">
            @foreach ($a->sports as $s)
              @php $pressed = $on && in_array($s->id, $picked, true); @endphp
              <button type="button" wire:key="sport-{{ $s->id }}" wire:click="toggleSport({{ $s->id }})" aria-pressed="{{ $pressed ? 'true' : 'false' }}" @disabled(! $on)
                style="min-height: 36px; padding: 0 12px; border-radius: 999px; font: 600 13px Inter, sans-serif; cursor: pointer; {{ $pressed ? 'border: 0; background: #1F3A5F; color: #FFFFFF' : 'border: 1px solid #CBD5E1; background: #FFFFFF; color: '.($on ? '#334155' : '#94A3B8') }}">{{ $s->name }}</button>
            @endforeach
          </div>
        </li>
      @endforeach
    </ul>
    <div style="padding: 14px 20px; border-top: 1px solid #E2E8F0; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px">
      <span style="font-size: 13px; color: #475569">Showing {{ $shown->count() }} of {{ $all->count() }} associations</span>
      <button type="button" wire:click="saveAndContinue" style="display: inline-flex; align-items: center; min-height: 44px; padding: 0 18px; border-radius: 10px; border: 0; background: #1F3A5F; color: #FFFFFF; font: 600 14px Inter, sans-serif; cursor: pointer">Save &amp; set limits →</button>
    </div>
  </section>
</div>
</x-filament-panels::page>
