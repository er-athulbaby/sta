<x-filament-panels::page>
@php
    $sports = $this->gameSports();
    $current = $this->current();
    $warn = $this->warning();
    $offered = collect($events)->where('offered', true)->count();
    $stepBtn = 'border: 1px solid #CBD5E1; background: #FFFFFF; font: 600 18px Inter, sans-serif; cursor: pointer; border-radius: 10px';
    $inputStyle = 'min-height: 44px; padding: 0 12px; border: 1px solid #CBD5E1; border-radius: 10px; font: 14px Inter, sans-serif';
@endphp
<div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start">

  <section aria-labelledby="sports-title" style="flex: 1 1 260px; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; display: flex; flex-direction: column">
    <h2 id="sports-title" style="margin: 0; padding: 16px 18px; border-bottom: 1px solid #E2E8F0; font-family: 'Space Grotesk', sans-serif; font-size: 17px; font-weight: 600">Sports in this game</h2>
    <ul style="list-style: none; margin: 0; padding: 8px">
      @forelse ($sports as $s)
        @php [$stateLabel, $stateKey] = $this->stateOf($s); @endphp
        <li wire:key="sport-{{ $s->id }}">
          <a href="#" wire:click.prevent="select({{ $s->id }})" aria-current="{{ $s->id === $sportId ? 'page' : 'false' }}"
             style="display: flex; justify-content: space-between; align-items: center; gap: 8px; min-height: 52px; padding: 6px 12px; border-radius: 10px; text-decoration: none; color: #0F172A; {{ $s->id === $sportId ? 'background: #E8EEF6' : '' }}">
            <span style="display: flex; flex-direction: column; gap: 2px">
              <span style="font-weight: 600">{{ $s->sport->name }}</span>
              <span style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #475569"><x-sta.assoc-avatar :association="$s->sport->association" size="sm" />{{ $s->sport->association?->name }}</span>
            </span>
            <x-sta.pill :status="$stateKey" style="height: 24px; padding: 0 9px">{{ $stateLabel }}</x-sta.pill>
          </a>
        </li>
      @empty
        <li style="padding: 12px; color: #475569">No sports yet. Tick sports for the invited associations in step 1.</li>
      @endforelse
    </ul>
  </section>

  @if ($current)
  <div style="flex: 999 1 560px; min-width: 0; display: flex; flex-direction: column; gap: 20px">

    <section aria-labelledby="wl-title" style="background: #F0FDFA; border: 1px solid #99F6E4; border-radius: 14px; padding: 20px; display: flex; flex-direction: column; gap: 18px">
      <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px">
        <div style="display: flex; flex-direction: column; gap: 2px">
          <x-sta.phase-chip :phase="2" label="Phase 2 · Entry by Number" style="align-self: flex-start" />
          <h2 id="wl-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 600">{{ $current->sport->name }} · Phase 2 limits</h2>
          <span style="font-size: 13px; color: #475569">{{ $current->sport->association?->name }} · per association</span>
        </div>
        <span style="font-size: 13px; color: #475569">Team officials are not counted in Phase 2</span>
      </div>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px">
        @foreach ([['men', 'Max male'], ['women', 'Max female'], ['total', 'Max total athletes']] as [$field, $label])
          <div wire:key="limit-{{ $field }}" style="display: flex; flex-direction: column; gap: 8px; padding: 14px; border-radius: 12px; background: #F8FAFC; border: 1px solid #E2E8F0">
            <span style="font-size: 13px; font-weight: 600">{{ $label }}</span>
            <div style="display: flex; align-items: center; gap: 6px">
              <button type="button" aria-label="Lower {{ $label }}" wire:click="bump('{{ $field }}', -1)" style="width: 44px; height: 44px; {{ $stepBtn }}">−</button>
              <output style="flex: 1; text-align: center; font-family: 'Space Grotesk', sans-serif; font-size: 24px; font-weight: 700">{{ $this->{$field} ?? '–' }}</output>
              <button type="button" aria-label="Raise {{ $label }}" wire:click="bump('{{ $field }}', 1)" style="width: 44px; height: 44px; {{ $stepBtn }}">+</button>
            </div>
            @error($field) <span role="alert" style="font-size: 12px; color: #991B1B">{{ $message }}</span> @enderror
          </div>
        @endforeach
      </div>
      @if ($warn)
        <div role="status" style="padding: 10px 12px; border-radius: 10px; background: #FFF7ED; color: #9A3412; font-size: 13px">{{ $warn }}</div>
      @endif
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px">
        <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600">Born on or after (optional)
          <input type="date" wire:model="dobFrom" style="{{ $inputStyle }}">
          @error('dobFrom') <span role="alert" style="font-weight: 400; color: #991B1B">{{ $message }}</span> @enderror
        </label>
        <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600">Born on or before
          <input type="date" wire:model="dobTo" style="{{ $inputStyle }}">
          @error('dobTo') <span role="alert" style="font-weight: 400; color: #991B1B">{{ $message }}</span> @enderror
        </label>
      </div>
      <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600">Entry policy (shown to associations and printed on the Entry by Event PDF)
        <textarea rows="3" wire:model="policy" style="padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 10px; font: 14px/1.5 Inter, sans-serif; resize: vertical"></textarea>
        @error('policy') <span role="alert" style="font-weight: 400; color: #991B1B">{{ $message }}</span> @enderror
      </label>
    </section>

    <section aria-labelledby="ev-title" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px">
      <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; padding: 16px 20px; border-bottom: 1px solid #E2E8F0">
        <div style="display: flex; flex-direction: column; gap: 2px">
          <h2 id="ev-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 17px; font-weight: 600">Events offered</h2>
          <span style="font-size: 13px; color: #475569">{{ $offered }} of {{ count($events) }} events offered in this game</span>
        </div>
        <span style="font-size: 13px; color: #475569">Events come from Sports &amp; events master data</span>
      </div>
      <div style="overflow-x: auto">
        <table style="width: 100%; min-width: 520px; border-collapse: collapse">
          <thead>
            <tr style="text-align: left; font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 0.05em">
              <th scope="col" style="padding: 12px 20px; font-weight: 600">Offered</th>
              <th scope="col" style="padding: 12px; font-weight: 600">Event</th>
              <th scope="col" style="padding: 12px; font-weight: 600">Gender</th>
              <th scope="col" style="padding: 12px 20px; font-weight: 600">Max athletes per association</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($events as $id => $e)
              <tr wire:key="event-{{ $id }}" style="border-top: 1px solid #EEF2F6{{ $e['offered'] ? '' : '; background: #FAFBFC; color: #475569' }}">
                <td style="padding: 8px 20px"><input type="checkbox" @checked($e['offered']) wire:click="toggleEvent({{ $id }})" aria-label="Offer {{ $e['name'] }}" style="width: 20px; height: 20px; accent-color: #1F3A5F"></td>
                <td style="padding: 8px 12px; font-weight: 600">{{ $e['name'] }}</td>
                <td style="padding: 8px 12px; color: #334155">{{ $e['gender'] }}</td>
                <td style="padding: 8px 20px">
                  <div style="display: inline-flex; align-items: center; gap: 6px">
                    <button type="button" aria-label="Lower max for {{ $e['name'] }}" wire:click="bumpEvent({{ $id }}, -1)" @disabled(! $e['offered']) style="width: 40px; height: 40px; border: 1px solid #CBD5E1; background: #FFFFFF; font: 600 16px Inter, sans-serif; cursor: pointer; border-radius: 10px">−</button>
                    <output style="width: 28px; text-align: center; font-weight: 700; font-size: 16px">{{ $e['offered'] ? ($e['max'] ?? '–') : '–' }}</output>
                    <button type="button" aria-label="Raise max for {{ $e['name'] }}" wire:click="bumpEvent({{ $id }}, 1)" @disabled(! $e['offered']) style="width: 40px; height: 40px; border: 1px solid #CBD5E1; background: #FFFFFF; font: 600 16px Inter, sans-serif; cursor: pointer; border-radius: 10px">+</button>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div style="display: flex; justify-content: flex-end; gap: 10px; padding: 14px 20px; border-top: 1px solid #E2E8F0">
        <button type="button" wire:click="mountAction('copyLimits')" style="min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 14px Inter, sans-serif; cursor: pointer">Copy limits from another game</button>
        <button type="button" wire:click="saveSport" style="min-height: 44px; padding: 0 18px; border-radius: 10px; border: 0; background: #1F3A5F; color: #FFFFFF; font: 600 14px Inter, sans-serif; cursor: pointer">Save {{ $current->sport->name }}</button>
      </div>
    </section>
  </div>
  @endif
</div>
</x-filament-panels::page>
