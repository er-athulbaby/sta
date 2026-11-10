<x-filament-panels::page>
    @php
        [$result, $problems] = $this->result();
        $subject = $this->subject();
        $fields = $subject?->fields() ?? [];
        $canEdit = $this->canEdit();
        $input = 'rounded border-gray-300 text-sm dark:bg-white/5';
        $noValue = ['empty', 'not_empty', 'yes', 'no'];
    @endphp

    {{-- Top bar --}}
    <div class="flex flex-wrap items-end gap-3">
        <label class="text-sm">Name
            <input type="text" wire:model="name" maxlength="120" @disabled(! $canEdit) class="block {{ $input }}">
        </label>
        <label class="text-sm">Share
            <select wire:model="visibility" @disabled(! $canEdit) class="block {{ $input }}">
                @foreach (\App\Models\SavedReport::VISIBILITIES as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if ($canEdit)
            <x-filament::button wire:click="save">Save</x-filament::button>
        @endif
        @if ($reportId)
            <x-filament::button color="gray" wire:click="saveAsCopy">Save as copy</x-filament::button>
        @endif
        <x-filament::button color="gray" icon="heroicon-o-arrow-down-tray" wire:click="export">Export to Excel</x-filament::button>
        @if ($reportId && $canEdit)
            <x-filament::button color="danger" wire:click="delete" wire:confirm="Delete this report? This cannot be undone.">Delete</x-filament::button>
        @endif
    </div>
    @error('name') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
    @error('visibility') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror

    {{-- Settings --}}
    <x-filament::section heading="Settings">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm">Subject
                <select wire:model.live="def.subject" class="block w-full {{ $input }}">
                    @foreach (\App\Services\Reports\Builder\Registry::all() as $key => $s)
                        <option value="{{ $key }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm">Game
                <select wire:model.live="def.game_id" class="block w-full {{ $input }}">
                    <option value="">{{ $subject?->gameBased() ? 'Choose a game' : 'Any game' }}</option>
                    @foreach (\App\Services\Reports\Builder\ReportDefinition::selectableGames(auth()->user()) as $id => $gameName)
                        <option value="{{ $id }}">{{ $gameName }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <h3 class="mt-4 text-sm font-semibold">Columns</h3>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                @foreach (collect($fields)->groupBy(fn ($f) => $f->section, preserveKeys: true) as $section => $sectionFields)
                    <p class="mt-2 text-xs uppercase text-gray-500">{{ $section }}</p>
                    @foreach ($sectionFields as $key => $field)
                        <label class="mr-3 inline-flex items-center gap-1 text-sm">
                            <input type="checkbox" value="{{ $key }}" wire:model.live="def.columns"> {{ $field->label }}
                        </label>
                    @endforeach
                @endforeach
            </div>
            <ol class="text-sm">
                @foreach ($def['columns'] ?? [] as $i => $key)
                    <li class="flex items-center gap-2" wire:key="col-{{ $key }}">
                        <button type="button" wire:click="moveColumn({{ $i }}, -1)" aria-label="Move up">▲</button>
                        <button type="button" wire:click="moveColumn({{ $i }}, 1)" aria-label="Move down">▼</button>
                        <span>{{ $fields[$key]->label ?? $key }}</span>
                        @if (($fields[$key] ?? null) && $fields[$key]->type->totals() !== [])
                            <select wire:model.live="def.totals.{{ $key }}" class="{{ $input }}" aria-label="Total">
                                <option value="">no total</option>
                                @foreach ($fields[$key]->type->totals() as $fn)
                                    <option value="{{ $fn }}">{{ ['sum' => 'sum', 'avg' => 'average', 'min' => 'minimum', 'max' => 'maximum'][$fn] }}</option>
                                @endforeach
                            </select>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>

        <h3 class="mt-4 text-sm font-semibold">Filters (all must match)</h3>
        @foreach ($def['filters'] ?? [] as $i => $filter)
            @php $f = $fields[$filter['field'] ?? ''] ?? null; $op = $filter['op'] ?? ''; @endphp
            <div class="mt-2 flex flex-wrap items-center gap-2" wire:key="filter-{{ $i }}">
                <select wire:model.live="def.filters.{{ $i }}.field" class="{{ $input }}">
                    @foreach ($fields as $key => $field)
                        <option value="{{ $key }}">{{ $field->section }} › {{ $field->label }}</option>
                    @endforeach
                </select>
                @if ($f)
                    <select wire:model.live="def.filters.{{ $i }}.op" class="{{ $input }}">
                        @foreach ($f->type->operators() as $o)
                            <option value="{{ $o }}">{{ \App\Services\Reports\Builder\FieldType::OPERATORS[$o] }}</option>
                        @endforeach
                    </select>
                    @php $type = match ($f->type) { \App\Services\Reports\Builder\FieldType::Number => 'number', \App\Services\Reports\Builder\FieldType::Date => 'date', default => 'text' }; @endphp
                    @if ($f->type === \App\Services\Reports\Builder\FieldType::Enum)
                        <select multiple wire:model.live="def.filters.{{ $i }}.value" class="{{ $input }}">
                            @foreach ($f->options as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @elseif ($op === 'between')
                        <input type="{{ $type }}" wire:model.live.debounce.500ms="def.filters.{{ $i }}.value.0" class="{{ $input }}"> and
                        <input type="{{ $type }}" wire:model.live.debounce.500ms="def.filters.{{ $i }}.value.1" class="{{ $input }}">
                    @elseif (! in_array($op, $noValue, true))
                        <input type="{{ $type }}" wire:model.live.debounce.500ms="def.filters.{{ $i }}.value" class="{{ $input }}">
                    @endif
                @endif
                <x-filament::button size="xs" color="gray" wire:click="removeFilter({{ $i }})">Remove</x-filament::button>
            </div>
        @endforeach
        @if (count($def['filters'] ?? []) < \App\Services\Reports\Builder\ReportDefinition::MAX_FILTERS)
            <x-filament::button class="mt-2" size="sm" color="gray" wire:click="addFilter">Add filter</x-filament::button>
        @endif

        <h3 class="mt-4 text-sm font-semibold">Group by</h3>
        @foreach ($def['groups'] ?? [] as $i => $group)
            @php $g = $fields[$group['field'] ?? ''] ?? null; @endphp
            <div class="mt-2 flex flex-wrap items-center gap-2" wire:key="group-{{ $i }}">
                <select wire:model.live="def.groups.{{ $i }}.field" class="{{ $input }}">
                    @foreach ($fields as $key => $field)
                        @if ($field->type->groupable())
                            <option value="{{ $key }}">{{ $field->label }}</option>
                        @endif
                    @endforeach
                </select>
                @if ($g?->type === \App\Services\Reports\Builder\FieldType::Date)
                    <select wire:model.live="def.groups.{{ $i }}.bucket" class="{{ $input }}">
                        <option value="month">by month</option>
                        <option value="year">by year</option>
                    </select>
                @endif
                <x-filament::button size="xs" color="gray" wire:click="removeGroup({{ $i }})">Remove</x-filament::button>
            </div>
        @endforeach
        @if (count($def['groups'] ?? []) < \App\Services\Reports\Builder\ReportDefinition::MAX_GROUPS)
            <x-filament::button class="mt-2" size="sm" color="gray" wire:click="addGroup">Add group level</x-filament::button>
        @endif

        <h3 class="mt-4 text-sm font-semibold">Sort</h3>
        <div class="flex gap-2">
            <select wire:model.live="def.sort.field" class="{{ $input }}">
                @foreach ($fields as $key => $field)
                    <option value="{{ $key }}">{{ $field->label }}</option>
                @endforeach
            </select>
            <select wire:model.live="def.sort.dir" class="{{ $input }}">
                <option value="asc">ascending</option>
                <option value="desc">descending</option>
            </select>
        </div>
    </x-filament::section>

    {{-- Results --}}
    <x-filament::section heading="Results">
        @if ($problems !== [])
            <ul class="text-sm text-danger-600">
                @foreach ($problems as $problem)
                    <li>{{ $problem }}</li>
                @endforeach
            </ul>
        @elseif ($result)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            @foreach ($result->columns as $column)
                                <th class="py-1 pr-3">{{ $column->label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result->lines() as $line)
                            <tr @class(['border-t border-gray-200 dark:border-white/10', 'bg-gray-50 font-semibold dark:bg-white/5' => $line['kind'] !== 'row'])>
                                @foreach ($line['cells'] as $cell)
                                    <td class="py-1 pr-3">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($result->truncated())
                <p class="mt-2 text-xs text-gray-500">Showing the first {{ count($result->rows) }} of {{ $result->rowCount }} rows; the Excel export has all of them.</p>
            @endif
        @endif
    </x-filament::section>
</x-filament-panels::page>
