<x-filament-panels::page>
@php
    $clothing = $this->clothingItems();
    $today = $this->longListToday();
    $rows = $this->gameSports()->map(function ($gs) use ($today) {
        $now = $today[$gs->id] ?? ['men' => 0, 'women' => 0, 'officials' => 0];

        return ['gs' => $gs, 'now' => $now, 'over' => $this->overBy($gs->id, $now)];
    });
    $overCount = $rows->filter(fn ($r) => $r['over'])->count();
    $ticked = $clothing->whereIn('id', $items)->count();
    $stepBtn = 'width: 40px; height: 40px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; font: 600 16px Inter, sans-serif; cursor: pointer';
    $labels = ['men' => 'max male', 'women' => 'max female', 'officials' => 'max team officials'];
@endphp

<section aria-labelledby="cloth-title" style="background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 14px; padding: 20px; display: flex; flex-direction: column; gap: 16px">
  <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px">
    <div style="display: flex; flex-direction: column; gap: 2px">
      <x-sta.phase-chip :phase="3" style="align-self: flex-start" />
      <h2 id="cloth-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 19px; font-weight: 600; color: #92400E">Clothing sizes to collect</h2>
      <span style="font-size: 13px; color: #475569">{{ $ticked }} items · associations enter a size for each person in the Long List; missing sizes are flagged</span>
    </div>
    <a href="{{ $this->clothingUrl() }}" style="display: inline-flex; align-items: center; min-height: 44px; font-weight: 600">Manage clothing items</a>
  </div>
  <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px">
    @foreach ($clothing as $c)
      @php $on = in_array($c->id, $items, true); @endphp
      <label wire:key="item-{{ $c->id }}" style="display: flex; flex-direction: column; gap: 6px; padding: 14px 16px; border-radius: 12px; cursor: pointer; {{ $on ? 'border: 2px solid #1F3A5F; background: #F1F5FB' : 'border: 1px solid #E2E8F0; background: #FFFFFF' }}">
        <span style="display: flex; justify-content: space-between; align-items: center; gap: 10px">
          <span style="font-weight: 600; font-size: 15px">{{ $c->name }}</span>
          <input type="checkbox" @checked($on) wire:click="toggleItem({{ $c->id }})" style="width: 20px; height: 20px; accent-color: #1F3A5F">
        </span>
        <span style="font-size: 13px; color: #475569">{{ ['athlete' => 'Athletes only', 'official' => 'Team officials only', 'both' => 'Athletes and officials'][$c->applies_to->value] }}</span>
        <span style="font-size: 12px; color: #334155">Sizes: {{ implode(', ', $c->sizes ?? []) }}</span>
      </label>
    @endforeach
  </div>
</section>

<section aria-labelledby="sl-title" style="background: #F5F3FF; border: 1px solid #DDD6FE; border-radius: 14px">
  <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; padding: 18px 20px; border-bottom: 1px solid #DDD6FE; background: #EDE9FE; border-radius: 13px 13px 0 0">
    <div style="display: flex; flex-direction: column; gap: 2px">
      <x-sta.phase-chip :phase="4" style="align-self: flex-start" />
      <h2 id="sl-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 19px; font-weight: 600; color: #5B21B6">Short list maximums</h2>
      <span style="font-size: 13px; color: #475569">Per association. Going over is allowed with one justification, and shows in the over-limit report.</span>
    </div>
    <span style="display: inline-flex; align-items: center; height: 30px; padding: 0 12px; border-radius: 999px; font-size: 13px; font-weight: 600; {{ $overCount ? 'background: #FFEDD5; color: #9A3412' : 'background: #DCFCE7; color: #166534' }}">{{ $overCount ? $overCount.' sports would need a justification today' : 'All long lists fit these maximums' }}</span>
  </div>
  <div style="overflow-x: auto">
    <table style="width: 100%; min-width: 760px; border-collapse: collapse">
      <thead>
        <tr style="text-align: left; font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 0.05em">
          <th scope="col" style="padding: 12px 20px; font-weight: 600">Sport</th>
          <th scope="col" style="padding: 12px; font-weight: 600">Max male</th>
          <th scope="col" style="padding: 12px; font-weight: 600">Max female</th>
          <th scope="col" style="padding: 12px; font-weight: 600">Max team officials</th>
          <th scope="col" style="padding: 12px 20px; font-weight: 600">Long list today (M · W · officials)</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($rows as $r)
          @php $gs = $r['gs']; @endphp
          <tr wire:key="cap-{{ $gs->id }}" style="border-top: 1px solid #EEF2F6">
            <th scope="row" style="padding: 10px 20px; text-align: left">
              <span style="display: flex; flex-direction: column; gap: 2px">
                <span style="font-weight: 600">{{ $gs->sport->name }}</span>
                <span style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 400; color: #475569"><x-sta.assoc-avatar :association="$gs->sport->association" size="sm" />{{ $gs->sport->association?->name }}</span>
              </span>
            </th>
            @foreach (['men', 'women', 'officials'] as $k)
              <td style="padding: 10px 12px">
                <div style="display: inline-flex; align-items: center; gap: 6px">
                  <button type="button" aria-label="Lower {{ $labels[$k] }} for {{ $gs->sport->name }}" wire:click="bump({{ $gs->id }}, '{{ $k }}', -1)" style="{{ $stepBtn }}">−</button>
                  <output style="width: 28px; text-align: center; font-weight: 700; font-size: 16px">{{ $caps[$gs->id][$k] ?? '–' }}</output>
                  <button type="button" aria-label="Raise {{ $labels[$k] }} for {{ $gs->sport->name }}" wire:click="bump({{ $gs->id }}, '{{ $k }}', 1)" style="{{ $stepBtn }}">+</button>
                </div>
              </td>
            @endforeach
            <td style="padding: 10px 20px">
              <span style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px">
                <span style="font-weight: 600">{{ $r['now']['men'] }} · {{ $r['now']['women'] }} · {{ $r['now']['officials'] }}</span>
                <span style="display: inline-flex; align-items: center; height: 24px; padding: 0 9px; border-radius: 999px; font-size: 12px; font-weight: 600; {{ $r['over'] ? 'background: #FFEDD5; color: #9A3412' : 'background: #DCFCE7; color: #166534' }}">{{ $r['over'] ? 'Over: '.implode(', ', $r['over']) : 'Within limits' }}</span>
              </span>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" style="padding: 14px 20px; color: #475569">No sports yet. Tick sports for the invited associations in step 1.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; padding: 14px 20px; border-top: 1px solid #E2E8F0">
    <span style="font-size: 13px; color: #475569">The Short List starts as a copy of each association's submitted Long List; they untick people to fit.</span>
    <button type="button" wire:click="saveAndContinue" style="display: inline-flex; align-items: center; min-height: 44px; padding: 0 18px; border-radius: 10px; border: 0; background: #1F3A5F; color: #FFFFFF; font: 600 14px Inter, sans-serif; cursor: pointer">Save &amp; go to phases →</button>
  </div>
</section>
</x-filament-panels::page>
