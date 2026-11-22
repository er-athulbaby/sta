<x-filament-panels::page>
    {{-- Home only (M6): the latest STA remarks, unread ones marked; each opens its phase page (where the association replies). --}}
    @if ($this instanceof \App\Filament\Portal\Pages\Home && ($remarks = $this->remarks())->isNotEmpty())
        @php $unread = $remarks->where('unread', true)->count(); @endphp
        <x-sta.card heading="Remarks from STA" :description="$unread ? $unread.' unread' : 'Latest remarks'">
            <ul class="sta-list">
                @foreach ($remarks as $r)
                    @php $s = $r['comment']->submission; @endphp
                    <li wire:key="remark-{{ $r['comment']->id }}" class="sta-list-item">
                        <div style="flex: 1 1 260px; min-width: 0; display: flex; flex-direction: column; gap: 2px">
                            <span style="font-weight: 600">{{ $s->game->name }} · Phase {{ $s->phase }} {{ \App\Models\GamePhase::NAMES[$s->phase] }}@if ($r['unread']) <x-sta.pill status="warn">New</x-sta.pill>@endif</span>
                            <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">{{ \Illuminate\Support\Str::limit($r['comment']->body, 160) }} · {{ $r['comment']->created_at->timezone('Asia/Bahrain')->format('j M Y H:i') }}</span>
                        </div>
                        <a href="{{ $r['url'] }}" aria-label="Open remark on {{ $s->game->name }} Phase {{ $s->phase }}" style="display: inline-flex; align-items: center; min-height: 44px; font-weight: 600">Open</a>
                    </li>
                @endforeach
            </ul>
        </x-sta.card>
    @endif
    {{-- No portal canvas screen: one card per game with the Main.dc.html phase cards (chip + status pill, name, meta; the open phase ringed). --}}
    @forelse ($this->games as $item)
        @php
            $g = $item['game'];
            $dates = $g->start_date->format('j M').' – '.$g->end_date->format('j M Y');
        @endphp
        <section wire:key="game-{{ $g->id }}" style="background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px; display: flex; flex-direction: column">
            <div style="display: flex; flex-direction: column; gap: 2px; padding: 18px 20px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
                <h2 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 19px; font-weight: 600">{{ $g->name }}</h2>
                <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">{{ $dates }} · {{ $item['rows']->count() }} {{ \Illuminate\Support\Str::plural('phase', $item['rows']->count()) }}</span>
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
