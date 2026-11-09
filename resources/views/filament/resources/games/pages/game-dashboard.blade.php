<x-filament-panels::page>
    @php
        $phases = $this->phases();
        $colors = ['submitted' => 'success', 'awaiting' => 'info', 'draft' => 'warning', 'overdue' => 'danger', 'no_response' => 'gray'];
    @endphp

    <div class="flex items-center justify-between gap-4">
        <p class="text-sm text-gray-500">
            {{ $record->start_date?->format('d M Y') }} - {{ $record->end_date?->format('d M Y') }}
        </p>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="pendingOnly">
            Pending only
        </label>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left">
                    <th class="p-2">Association</th>
                    @foreach ($phases as $p)
                        <th class="p-2">Phase {{ $p->phase }} - {{ \App\Models\GamePhase::NAMES[$p->phase] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($this->rows() as $a)
                    <tr wire:key="row-{{ $a->id }}" class="border-t align-top">
                        <td class="p-2">{{ $a->name }} <span class="text-gray-500">({{ $a->code }})</span></td>
                        @foreach ($phases as $p)
                            @php
                                $c = $this->cell($a, $p->phase);
                                $s = $c['submission'];
                            @endphp
                            <td class="p-2 space-y-1" wire:key="cell-{{ $a->id }}-{{ $p->phase }}">
                                <x-filament::badge :color="$colors[$c['status']]">{{ $c['label'] }}</x-filament::badge>
                                @if ($s?->deadline_override)
                                    <div class="text-xs">Deadline: {{ $s->deadline_override->format('d M Y H:i') }}</div>
                                @endif
                                @if ($c['status'] === 'submitted')
                                    <div class="text-xs">{{ $s->reference_no }}</div>
                                    @foreach ($this->pdfLinks($s) as $link)
                                        <div><a href="{{ $link['url'] }}" target="_blank" class="text-xs underline">{{ $link['name'] }}</a></div>
                                    @endforeach
                                @endif
                                @if ($this->canManage())
                                    <div class="flex gap-2">
                                        @if ($c['status'] !== 'submitted')
                                            <x-filament::link tag="button" size="xs" wire:click="mountAction('extend', { association: {{ $a->id }}, phase: {{ $p->phase }} })">Extend</x-filament::link>
                                        @endif
                                        @if (in_array($c['status'], ['submitted', 'awaiting']))
                                            <x-filament::link tag="button" size="xs" color="danger" wire:click="mountAction('reopen', { association: {{ $a->id }}, phase: {{ $p->phase }} })">Re-open</x-filament::link>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
