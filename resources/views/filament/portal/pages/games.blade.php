<x-filament-panels::page>
    {{-- No portal canvas screen: one card per game with the Main.dc.html phase cards (chip + status pill, name, meta; the open phase ringed). --}}
    @forelse ($this->games as $item)
        @php
            $g = $item['game'];
            $dates = $g->start_date->format('j M').' – '.$g->end_date->format('j M Y');
        @endphp
        <section wire:key="game-{{ $g->id }}" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; display: flex; flex-direction: column">
            <div style="display: flex; flex-direction: column; gap: 2px; padding: 18px 20px; border-bottom: 1px solid #E2E8F0">
                <h2 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 19px; font-weight: 600">{{ $g->name }}</h2>
                <span style="font-size: 13px; color: #475569">{{ $dates }} · {{ $item['rows']->count() }} {{ \Illuminate\Support\Str::plural('phase', $item['rows']->count()) }}</span>
            </div>
            <div style="padding: 16px 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px">
                @foreach ($item['rows'] as $row)
                    {{-- The card itself is the link (open and closed phases); a phase not opened yet is not a link. --}}
                    <x-sta.phase-card :phase="$row['phase']" :name="$row['name']" :status="$row['pill']" :status-label="$row['submission']"
                        :meta="$row['meta']" :active="$row['status'] === 'Open'" :href="$row['url']"
                        :aria-label="$row['url'] ? 'Phase '.$row['phase'].' '.$row['name'].' for '.$g->name.': '.$row['submission'].' · '.$row['meta'] : null" />
                @endforeach
            </div>
        </section>
    @empty
        <p class="sta-muted">No games yet.</p>
    @endforelse
</x-filament-panels::page>
