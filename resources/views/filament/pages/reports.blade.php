<x-filament-panels::page>
    @php $subjects = \App\Services\Reports\Builder\Registry::all(); @endphp

    @foreach (['My reports' => $this->mine(), 'Shared' => $this->shared()] as $heading => $reports)
        <x-filament::section :heading="$heading">
            @forelse ($reports as $report)
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-t border-gray-200 py-2 first:border-t-0 dark:border-white/10">
                    <a href="{{ \App\Filament\Pages\ReportBuilder::getUrl(['report' => $report]) }}" class="font-medium text-primary-600 hover:underline">{{ $report->name }}</a>
                    <span class="text-xs text-gray-500">
                        {{ ($subjects[$report->subject] ?? null)?->label() ?? $report->subject }}
                        · {{ \App\Models\SavedReport::VISIBILITIES[$report->visibility] ?? $report->visibility }}
                        @if ($heading === 'Shared') · by {{ $report->creator->name }} @endif
                        · last run {{ $report->last_run_at?->format('j M Y H:i') ?? 'never' }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-500">No reports yet.</p>
            @endforelse
        </x-filament::section>
    @endforeach

    <x-filament::section heading="Ready-made game reports">
        @forelse ($this->games() as $id => $gameName)
            <a href="{{ \App\Filament\Resources\Games\GameResource::getUrl('reports', ['record' => $id]) }}" class="mr-4 text-sm text-primary-600 hover:underline">{{ $gameName }}</a>
        @empty
            <p class="text-sm text-gray-500">No games yet.</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
