<x-filament-panels::page>
    @php $data = $this->reportData(); @endphp

    <p class="text-sm text-gray-600 dark:text-gray-400">
        Only submitted entries are included. Expiring documents and clothing sizes use an association's Short List when its Phase 4 is submitted, otherwise its Long List.
    </p>

    <div class="flex flex-wrap items-end gap-3">
        <label class="text-sm">
            Report
            <select wire:model.live="report" class="block rounded border-gray-300 text-sm dark:bg-white/5">
                @foreach (\App\Services\Reports\GameReports::REPORTS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <x-filament::button wire:click="export" icon="heroicon-o-arrow-down-tray">Export to Excel</x-filament::button>
    </div>

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500">@foreach ($data['headings'] as $h)<th class="py-1 pr-3">{{ $h }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @forelse (array_slice($data['rows'], 0, 500) as $row)
                        <tr class="border-t border-gray-200 dark:border-white/10">@foreach ($row as $cell)<td class="py-1 pr-3">{{ $cell }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($data['headings']) }}" class="py-2 text-gray-500">No rows.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (count($data['rows']) > 500)
            <p class="mt-2 text-xs text-gray-500">Showing the first 500 of {{ count($data['rows']) }} rows; the Excel export has all of them.</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
