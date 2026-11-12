{{-- Canvas AdminReports.dc.html: the "Ready-made reports" band (game from the header select), then "Saved reports"
     with My reports / Shared tabs, a card per report and the New report tile. --}}
@use(App\Filament\Pages\ReportBuilder)
@use(App\Models\SavedReport)
@use(App\Support\Sta)
@php
    $tabs = ['mine' => ['My reports', $this->mine()], 'shared' => ['Shared', $this->shared()]];
    $games = $this->games();
    $pill = 'display: inline-flex; align-items: center; height: 24px; padding: 0 9px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; ';
    $hidden = 'position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0)';
@endphp
<x-filament-panels::page>
    @include('filament.shared.ready-made-reports')

    <section aria-labelledby="saved-title" x-data="{ tab: 'mine' }" style="display: flex; flex-direction: column; gap: 12px">
        <div style="display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 12px; background: var(--sta-bg-indigo-50, #EEF2FF)"><span aria-hidden="true" style="width: 32px; height: 32px; border-radius: 9px; background: var(--sta-bg-indigo-600, #4F46E5); color: var(--sta-fg-on-brand, #FFFFFF); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-6-4-6 4z"/></svg></span><h2 id="saved-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 19px; font-weight: 600; color: var(--sta-fg-indigo-800, #3730A3)">Saved reports</h2></div>
        <div role="tablist" aria-label="Saved reports" style="display: flex; gap: 4px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
            @foreach ($tabs as $key => [$label, $reports])
                <button type="button" role="tab" class="sta-tab" id="saved-tab-{{ $key }}" aria-controls="saved-panel-{{ $key }}" x-on:click="tab = '{{ $key }}'"
                    aria-selected="{{ $key === 'mine' ? 'true' : 'false' }}" x-bind:aria-selected="tab === '{{ $key }}' ? 'true' : 'false'">{{ $label }} ({{ $reports->count() }})</button>
            @endforeach
        </div>
        @foreach ($tabs as $key => [$label, $reports])
            <div role="tabpanel" id="saved-panel-{{ $key }}" aria-labelledby="saved-tab-{{ $key }}" x-show="tab === '{{ $key }}'" @if ($key !== 'mine') x-cloak @endif>
              <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px">
                <h3 style="{{ $hidden }}">{{ $label }}</h3>
                @foreach ($reports as $report)
                    @php $d = $this->describe($report, $games); @endphp
                    <article wire:key="saved-{{ $key }}-{{ $report->id }}" style="background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px; padding: 18px; display: flex; flex-direction: column; gap: 12px">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px">
                            <h4 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 16px; font-weight: 600">{{ $report->name }}</h4>
                            <span style="{{ $pill }}{{ $report->visibility === 'shared' ? 'background: var(--sta-bg-green-100, #DCFCE7); color: var(--sta-fg-green-800, #166534)' : 'background: var(--sta-bg-subtle, #F1F5F9); color: var(--sta-fg-strong, #334155)' }}">{{ SavedReport::VISIBILITIES[$report->visibility] ?? $report->visibility }}</span>
                        </div>
                        <dl style="margin: 0; display: grid; grid-template-columns: 76px minmax(0, 1fr); gap: 4px 10px; font-size: 13px">
                            <dt style="color: var(--sta-fg-muted, #475569)">Subject</dt><dd style="margin: 0">{{ $d['subject'] }}</dd>
                            <dt style="color: var(--sta-fg-muted, #475569)">Filters</dt><dd style="margin: 0">{{ $d['filters'] }}</dd>
                            <dt style="color: var(--sta-fg-muted, #475569)">Grouped</dt><dd style="margin: 0">{{ $d['grouped'] }}</dd>
                        </dl>
                        <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">@if ($key === 'shared')by {{ $report->creator->name }} · @endif{{ $report->updated_at ? 'Edited '.lcfirst(Sta::when($report->updated_at)).' · ' : '' }}last run {{ $report->last_run_at ? lcfirst(Sta::when($report->last_run_at)) : 'never' }}</span>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: auto">
                            <a href="{{ ReportBuilder::getUrl(['report' => $report]) }}" aria-label="Open {{ $report->name }}" style="display: inline-flex; align-items: center; min-height: 44px; padding: 0 14px; border-radius: 10px; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF); font-weight: 600; text-decoration: none">Open</a>
                            <button type="button" wire:click="exportSaved({{ $report->id }})" aria-label="Export {{ $report->name }} to Excel" style="min-height: 44px; padding: 0 14px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); font: 600 13px Inter, sans-serif; cursor: pointer">Export to Excel</button>
                        </div>
                    </article>
                @endforeach
                @if ($key === 'mine')
                    <a href="{{ ReportBuilder::getUrl() }}" style="min-height: 200px; border: 1px dashed var(--sta-bd-faint, #94A3B8); border-radius: 14px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: var(--sta-fg-brand, #1F3A5F); font-weight: 600; text-decoration: none">
                        <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                        New report
                    </a>
                @elseif ($reports->isEmpty())
                    <p style="margin: 0; color: var(--sta-fg-muted, #475569)">No one has shared a report yet.</p>
                @endif
              </div>
            </div>
        @endforeach
    </section>
</x-filament-panels::page>
