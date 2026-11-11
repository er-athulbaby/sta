<x-filament-panels::page>
    @forelse ($this->games as $item)
        @php $g = $item['game']; @endphp
        <x-sta.card :heading="$g->name" :description="$g->start_date->format('j M Y').' – '.$g->end_date->format('j M Y')" wire:key="game-{{ $g->id }}">
            <div class="sta-card-body sta-grid-phase">
                @foreach ($item['rows'] as $row)
                    <x-sta.phase-card :phase="$row['phase']" :name="$row['name']" :status="$row['pill']" :status-label="$row['submission']"
                        :meta="$row['status'].($row['deadline'] ? ' · Deadline '.$row['deadline']->format('j M Y H:i') : '')"
                        :active="$row['status'] === 'Open'">
                        @if ($row['url'])
                            <a href="{{ $row['url'] }}" class="sta-btn sta-btn-outline" style="align-self: flex-start"
                               aria-label="Open Phase {{ $row['phase'] }} {{ $row['name'] }} for {{ $g->name }}">Open</a>
                        @endif
                    </x-sta.phase-card>
                @endforeach
            </div>
        </x-sta.card>
    @empty
        <p class="sta-muted">No games yet.</p>
    @endforelse
</x-filament-panels::page>
