<x-filament-panels::page>
    @forelse ($this->games as $item)
        <x-filament::section :heading="$item['game']->name" :description="$item['game']->start_date->format('j M Y').' – '.$item['game']->end_date->format('j M Y')">
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($item['rows'] as $row)
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                        <div>
                            <span class="font-medium">Phase {{ $row['phase'] }} · {{ $row['name'] }}</span>
                            <span class="ml-2 text-gray-500">{{ $row['status'] }}</span>
                            @if ($row['deadline'])
                                <span class="ml-2 text-gray-500">Deadline {{ $row['deadline']->format('j M Y H:i') }}</span>
                            @endif
                            <span class="ml-2 text-gray-500">{{ $row['submission'] }}</span>
                        </div>
                        @if ($row['url'])
                            <a href="{{ $row['url'] }}" class="text-primary-600 underline">Open</a>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @empty
        <p class="text-sm text-gray-500">No games yet.</p>
    @endforelse
</x-filament-panels::page>
