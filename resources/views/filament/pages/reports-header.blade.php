{{-- Canvas AdminReports.dc.html header: h1, subtitle, the Game select of the ready-made reports and New report. --}}
<header style="display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px">
    <div style="display: flex; flex-direction: column; gap: 6px">
        <h1 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 700; letter-spacing: -0.01em">Reports</h1>
        <span style="color: var(--sta-fg-muted, #475569)">Ready-made reports for every game, plus reports you build and save yourself</span>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end">
        <label style="display: flex; flex-direction: column; gap: 4px; font-size: 12px; font-weight: 600; color: var(--sta-fg-muted, #475569)">Game
            <select wire:model.live="game" @disabled($this->games()->isEmpty()) style="min-height: 44px; padding: 0 12px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; background: var(--sta-bg-surface, #FFFFFF); font: 14px Inter, sans-serif; color: var(--sta-fg-text, #0F172A)">
                @forelse ($this->games() as $id => $gameName)
                    <option value="{{ $id }}">{{ $gameName }}</option>
                @empty
                    <option value="">No games yet</option>
                @endforelse
            </select>
        </label>
        <a href="{{ \App\Filament\Pages\ReportBuilder::getUrl() }}" style="min-height: 44px; padding: 0 18px; border-radius: 10px; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF); font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; gap: 8px; text-decoration: none">
            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            New report
        </a>
    </div>
</header>
