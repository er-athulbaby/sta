{{-- Canvas AdminReports.dc.html "Ready-made reports" band: the report list beside the preview of the chosen report.
     Used by the Reports page (game from its Game select) and a game's Reports page. Needs ShowsReadyMadeReports. --}}
@use(App\Services\Reports\GameReports)
@php
    $hasGame = $this->reportGame() !== null;
    $data = $this->reportData();
    $total = count($data['rows']);
    $preview = array_slice($data['rows'], 0, 6);
    [$phase, $hint] = GameReports::META[$this->report];
    $summary = GameReports::summary($this->report, $data);
    $firstSum = array_key_first($summary['sums']);
    $numeric = array_map(fn (int $i) => $data['rows'] !== [] && collect($data['rows'])->every(fn ($r) => $r[$i] === null || is_int($r[$i]) || is_float($r[$i])), array_keys($data['headings']));
    $last = count($data['headings']) - 1;
    $pad = fn (int $i) => $i === 0 ? '12px 20px' : ($i === $last ? '12px 20px' : '12px');
    $listChip = 'display: inline-flex; align-items: center; height: 24px; padding: 0 9px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; background: var(--ph-bg, #F1F5F9); color: var(--ph-fg, #334155)';
    $outline = 'min-height: 44px; padding: 0 18px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; gap: 8px; cursor: pointer';
@endphp
<section aria-labelledby="ready-title" style="display: flex; flex-direction: column; gap: 12px">
    <div style="display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 12px; background: #EFF6FF"><span aria-hidden="true" style="width: 32px; height: 32px; border-radius: 9px; background: #2563EB; color: #FFFFFF; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="M4 10h16"/><path d="M10 10v10"/></svg></span><h2 id="ready-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 19px; font-weight: 600; color: #1E40AF">Ready-made reports</h2></div>
    <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-start">
        <ul aria-label="Ready-made reports" style="flex: 1 1 280px; list-style: none; margin: 0; padding: 8px; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; display: flex; flex-direction: column; gap: 2px">
            @foreach (GameReports::REPORTS as $key => $label)
                <li wire:key="ready-{{ $key }}">
                    <button type="button" wire:click="$set('report', '{{ $key }}')" @if ($this->report === $key) aria-current="true" @endif class="sta-report-item"
                        style="width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 10px; min-height: 52px; padding: 6px 12px; border: 0; border-radius: 10px; text-align: left; font: inherit; color: #0F172A; cursor: pointer; background: {{ $this->report === $key ? '#E8EEF6' : 'transparent' }}">
                        <span style="display: flex; flex-direction: column; gap: 2px">
                            <span style="font-weight: 600">{{ $label }}</span>
                            <span style="font-size: 12px; color: #475569">{{ GameReports::META[$key][1] }}</span>
                        </span>
                        <span @class(['sta-ph-'.GameReports::META[$key][0] => GameReports::META[$key][0]]) style="{{ $listChip }}">{{ GameReports::META[$key][0] ? 'Phase '.GameReports::META[$key][0] : 'Any' }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
        <div style="flex: 999 1 560px; min-width: 0; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; display: flex; flex-direction: column">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid #E2E8F0">
                <div style="display: flex; flex-direction: column; gap: 2px">
                    @if ($phase)
                        <span class="sta-phase-chip sta-ph-{{ $phase }}" style="align-self: flex-start">Phase {{ $phase }}</span>
                    @else
                        <span style="display: inline-flex; align-items: center; height: 26px; align-self: flex-start; padding: 0 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #F1F5F9; color: #334155; white-space: nowrap">Any</span>
                    @endif
                    <h3 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 18px; font-weight: 600">{{ GameReports::REPORTS[$this->report] }}</h3>
                    <span style="font-size: 13px; color: #475569">{{ $hint }} · preview of the first {{ count($preview) }} of {{ $total }} {{ str('row')->plural($total) }}</span>
                </div>
                <button type="button" wire:click="export" @disabled(! $hasGame) style="{{ $outline }}">
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                    Export to Excel
                </button>
            </div>
            <div style="overflow-x: auto">
                <table style="width: 100%; min-width: 560px; border-collapse: collapse">
                    <thead>
                        <tr style="text-align: left; font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 0.05em">
                            @foreach ($data['headings'] as $i => $h)
                                <th scope="col" style="padding: {{ $pad($i) }}; font-weight: 600; {{ $numeric[$i] ? 'text-align: right' : '' }}">{{ $h }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($preview as $row)
                            <tr style="border-top: 1px solid #EEF2F6">
                                @foreach ($row as $i => $cell)
                                    <td style="padding: {{ $pad($i) }}; white-space: nowrap; {{ $i === 0 ? 'font-weight: 600;' : '' }} {{ $numeric[$i] ? 'text-align: right' : '' }}">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr style="border-top: 1px solid #EEF2F6"><td colspan="{{ max(1, count($data['headings'])) }}" style="padding: 12px 20px; color: #475569">{{ $hasGame ? 'No rows yet: no association has submitted this phase.' : 'No games yet.' }}</td></tr>
                        @endforelse
                        @if ($total > 0)
                            <tr style="border-top: 2px solid #E2E8F0; background: #F8FAFC">
                                <th scope="row" colspan="{{ $firstSum ?? count($data['headings']) }}" style="padding: 12px 20px; text-align: left; font-weight: 700">All {{ $summary['associations'] }} {{ str('association')->plural($summary['associations']) }}</th>
                                @if ($firstSum !== null)
                                    @foreach (array_slice($data['headings'], $firstSum, null, true) as $i => $h)
                                        <td style="padding: {{ $pad($i) }}; text-align: right; font-weight: 700">{{ $summary['sums'][$i] ?? '' }}</td>
                                    @endforeach
                                @endif
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div style="padding: 12px 20px; border-top: 1px solid #E2E8F0; font-size: 13px; color: #475569">Export to Excel gives every row. Only submitted entries are included. Expiring documents and clothing sizes use an association's Short List when its Phase 4 is submitted, otherwise its Long List.</div>
        </div>
    </div>
</section>
