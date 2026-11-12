{{-- Canvas AdminReportBuilder.dc.html: the 320px settings aside (Subject, Columns, Filters, Group by, Sort) beside the
     live Results card. Every control binds the existing $def state; ReportDefinition::fromArray() validates each run. --}}
@use(App\Services\Reports\Builder\FieldType)
@use(App\Services\Reports\Builder\ReportDefinition)
@php
    [$result, $problems] = $this->result();
    $subject = $this->subject();
    $fields = $subject?->fields(auth()->user()) ?? [];
    $noValue = ['empty', 'not_empty', 'yes', 'no'];
    $columns = array_values((array) ($def['columns'] ?? []));
    $groups = array_values((array) ($def['groups'] ?? []));
    $label = 'font-size: 12px; font-weight: 700; color: var(--sta-fg-strong, #334155); text-transform: uppercase; letter-spacing: 0.05em';
    $block = 'padding: 16px 18px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0); display: flex; flex-direction: column; gap: 10px';
    $select = 'min-height: 40px; padding: 0 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; background: var(--sta-bg-surface, #FFFFFF); font: 14px Inter, sans-serif; color: var(--sta-fg-text, #0F172A); min-width: 0; max-width: 100%';
    $chipSelect = 'min-height: 32px; padding: 0 4px; border: 1px solid transparent; border-radius: 8px; background: transparent; font: inherit; max-width: 100%';
    $chipInput = 'min-height: 32px; flex: 1 1 110px; min-width: 0; padding: 0 8px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 8px; background: var(--sta-bg-surface, #FFFFFF); font: 600 13px Inter, sans-serif; color: var(--sta-fg-brand, #1F3A5F)';
    $iconBtn = 'width: 32px; height: 32px; flex-shrink: 0; border: 0; border-radius: 8px; background: transparent; color: var(--sta-fg-muted, #475569); display: inline-flex; align-items: center; justify-content: center; cursor: pointer';
    $handle = '<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" style="fill: var(--sta-fg-faint, #94A3B8); flex-shrink: 0"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>';
    $cross = '<svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>';
    $groupable = collect($fields)->filter(fn ($f) => $f->type->groupable());
    $n = $result ? count($result->columns) : 0;
    $pad = fn (int $i, string $v = '10px') => $i === 0 ? "{$v} 20px {$v} ".($groups !== [] ? '36px' : '20px') : ($i === $n - 1 ? "{$v} 20px" : "{$v} 12px");
    // A subtotal or total line: its label spans the leading columns that have no total.
    $span = function (array $cells) { foreach ($cells as $i => $c) { if ($i > 0 && $c !== '') { return $i; } } return count($cells); };
@endphp
<x-filament-panels::page>
    @error('name') <p role="alert" style="margin: 0; color: var(--sta-fg-red-700, #B91C1C); font-size: 13px">{{ $message }}</p> @enderror
    @error('visibility') <p role="alert" style="margin: 0; color: var(--sta-fg-red-700, #B91C1C); font-size: 13px">{{ $message }}</p> @enderror

    <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start">

        <aside aria-label="Report settings" class="sta-builder-settings" style="flex: 1 1 320px; max-width: 100%; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px; display: flex; flex-direction: column">
            <div style="padding: 16px 18px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0); display: flex; flex-direction: column; gap: 6px">
                <label for="rb-subject" style="{{ $label }}">Subject</label>
                <select id="rb-subject" wire:model.live="def.subject" style="{{ $select }}; min-height: 44px; padding: 0 12px">
                    @foreach (\App\Services\Reports\Builder\Registry::all() as $key => $s)
                        <option value="{{ $key }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div style="{{ $block }}">
                <div style="display: flex; justify-content: space-between; align-items: center">
                    <span style="{{ $label }}">Columns</span>
                    <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ count($columns) }} shown · drag to reorder</span>
                </div>
                @foreach (collect($fields)->groupBy(fn ($f) => $f->section, preserveKeys: true) as $section => $sectionFields)
                    <div style="display: flex; flex-direction: column; gap: 2px">
                        <span style="font-size: 12px; font-weight: 600; color: var(--sta-fg-muted, #475569); padding: 4px 0">{{ $section }}</span>
                        @foreach ($sectionFields as $key => $field)
                            @php $on = in_array($key, $columns, true); @endphp
                            <label wire:key="col-check-{{ $key }}" style="display: flex; align-items: center; gap: 8px; min-height: 36px; padding: 0 8px; border-radius: 8px; cursor: pointer; {{ $on ? 'background: var(--sta-bg-row-active, #F1F5FB); font-weight: 600' : 'color: var(--sta-fg-strong, #334155)' }}">
                                <input type="checkbox" value="{{ $key }}" wire:model.live="def.columns" style="width: 18px; height: 18px; accent-color: var(--sta-bd-brand, #1F3A5F); flex-shrink: 0">
                                <span>{{ $field->label }}</span>
                            </label>
                        @endforeach
                    </div>
                @endforeach
                @if ($columns !== [])
                    <div style="display: flex; flex-direction: column; gap: 2px">
                        <span style="font-size: 12px; font-weight: 600; color: var(--sta-fg-muted, #475569); padding: 4px 0">Order and totals</span>
                        <ol x-data="{ from: null }" style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px">
                            @foreach ($columns as $i => $key)
                                <li wire:key="col-{{ $key }}" draggable="true" x-on:dragstart="from = {{ $i }}" x-on:dragover.prevent x-on:drop.prevent="from !== null && from !== {{ $i }} && $wire.moveColumnTo(from, {{ $i }}); from = null"
                                    style="display: flex; align-items: center; gap: 8px; min-height: 36px; padding: 0 8px; border-radius: 8px; background: var(--sta-bg-row-active, #F1F5FB); font-weight: 600; cursor: grab">
                                    {!! $handle !!}
                                    <span style="flex: 1 1 auto; min-width: 0">{{ $fields[$key]->label ?? $key }}</span>
                                    @if (($fields[$key] ?? null) && $fields[$key]->type->totals() !== [])
                                        <select wire:model.live="def.totals.{{ $key }}" aria-label="Total of {{ $fields[$key]->label }}" style="{{ $chipSelect }}; border-color: var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); font: 13px Inter, sans-serif">
                                            <option value="">no total</option>
                                            @foreach ($fields[$key]->type->totals() as $fn)
                                                <option value="{{ $fn }}">{{ ['sum' => 'sum', 'avg' => 'average', 'min' => 'minimum', 'max' => 'maximum'][$fn] }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <button type="button" wire:click="moveColumn({{ $i }}, -1)" aria-label="Move {{ $fields[$key]->label ?? $key }} up" @disabled($i === 0) style="{{ $iconBtn }}">▲</button>
                                    <button type="button" wire:click="moveColumn({{ $i }}, 1)" aria-label="Move {{ $fields[$key]->label ?? $key }} down" @disabled($i === count($columns) - 1) style="{{ $iconBtn }}">▼</button>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>

            <div style="{{ $block }}">
                <div style="display: flex; justify-content: space-between; align-items: center">
                    <span style="{{ $label }}">Filters</span>
                    <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">Match all rules</span>
                </div>
                <div class="sta-rule" style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 8px 10px; border-radius: 10px; background: var(--sta-bg-surface-alt, #F8FAFC); border: 1px solid var(--sta-bd-line, #E2E8F0); font-size: 13px">
                    <span style="font-weight: 600">Game</span>
                    <span style="color: var(--sta-fg-muted, #475569)">is</span>
                    <select wire:model.live="def.game_id" aria-label="Game" style="{{ $chipSelect }}; flex: 1 1 auto; min-width: 0; font-weight: 600; color: var(--sta-fg-brand, #1F3A5F)">
                        <option value="">{{ $subject?->gameBased() ? 'Choose a game' : 'Any game' }}</option>
                        @foreach (ReportDefinition::selectableGames(auth()->user()) as $id => $gameName)
                            <option value="{{ $id }}">{{ $gameName }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach ($def['filters'] ?? [] as $i => $filter)
                    @php $f = $fields[$filter['field'] ?? ''] ?? null; $op = $filter['op'] ?? ''; @endphp
                    <div wire:key="filter-{{ $i }}" class="sta-rule" style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 8px 10px; border-radius: 10px; background: var(--sta-bg-surface-alt, #F8FAFC); border: 1px solid var(--sta-bd-line, #E2E8F0); font-size: 13px">
                        <select wire:model.live="def.filters.{{ $i }}.field" aria-label="Rule {{ $i + 1 }} field" style="{{ $chipSelect }}; font-weight: 600; color: var(--sta-fg-text, #0F172A)">
                            @foreach ($fields as $key => $field)
                                <option value="{{ $key }}">{{ $field->section }} › {{ $field->label }}</option>
                            @endforeach
                        </select>
                        @if ($f)
                            <select wire:model.live="def.filters.{{ $i }}.op" aria-label="Rule {{ $i + 1 }} condition" style="{{ $chipSelect }}; color: var(--sta-fg-muted, #475569)">
                                @foreach ($f->type->operators() as $o)
                                    <option value="{{ $o }}">{{ FieldType::OPERATORS[$o] }}</option>
                                @endforeach
                            </select>
                            @php $type = match ($f->type) { FieldType::Number => 'number', FieldType::Date => 'date', default => 'text' }; @endphp
                            @if ($f->type === FieldType::Enum)
                                <select multiple wire:model.live="def.filters.{{ $i }}.value" aria-label="Rule {{ $i + 1 }} values" style="{{ $chipInput }}; padding: 4px 8px">
                                    @foreach ($f->options as $value => $optionLabel)
                                        <option value="{{ $value }}">{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            @elseif ($op === 'between')
                                <input type="{{ $type }}" wire:model.live.debounce.500ms="def.filters.{{ $i }}.value.0" aria-label="Rule {{ $i + 1 }} from" style="{{ $chipInput }}">
                                <span style="color: var(--sta-fg-muted, #475569)">and</span>
                                <input type="{{ $type }}" wire:model.live.debounce.500ms="def.filters.{{ $i }}.value.1" aria-label="Rule {{ $i + 1 }} to" style="{{ $chipInput }}">
                            @elseif (! in_array($op, $noValue, true))
                                <input type="{{ $type }}" wire:model.live.debounce.500ms="def.filters.{{ $i }}.value" aria-label="Rule {{ $i + 1 }} value" style="{{ $chipInput }}">
                            @else
                                <span style="flex: 1 1 auto"></span>
                            @endif
                        @endif
                        <button type="button" wire:click="removeFilter({{ $i }})" aria-label="Remove rule {{ $f?->label ?? $i + 1 }}" style="{{ $iconBtn }}">{!! $cross !!}</button>
                    </div>
                @endforeach
                @if (count($def['filters'] ?? []) < ReportDefinition::MAX_FILTERS)
                    <button type="button" wire:click="addFilter" style="align-self: flex-start; min-height: 40px; padding: 0 12px; border-radius: 10px; border: 1px dashed var(--sta-bd-faint, #94A3B8); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-brand, #1F3A5F); font: 600 13px Inter, sans-serif; cursor: pointer">+ Add rule</button>
                @endif
            </div>

            <div style="{{ $block }}">
                <span style="{{ $label }}">Group by (up to {{ ReportDefinition::MAX_GROUPS }})</span>
                <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px">
                    @foreach (['1st', '2nd'] as $level => $levelLabel)
                        @php $current = $groups[$level]['field'] ?? ''; @endphp
                        <div style="display: flex; flex-direction: column; gap: 6px" wire:key="group-{{ $level }}-{{ $current }}">
                            <label style="display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ $levelLabel }}
                                <select wire:change="setGroup({{ $level }}, $event.target.value)" @disabled($level > count($groups)) style="{{ $select }}">
                                    <option value="" @selected($current === '')>None</option>
                                    @foreach ($groupable as $key => $field)
                                        <option value="{{ $key }}" @selected($current === $key)>{{ $field->label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            @if (($fields[$current] ?? null)?->type === FieldType::Date)
                                <select wire:model.live="def.groups.{{ $level }}.bucket" aria-label="{{ $levelLabel }} group by" style="{{ $select }}">
                                    <option value="month">by month</option>
                                    <option value="year">by year</option>
                                </select>
                            @endif
                        </div>
                    @endforeach
                </div>
                <label title="Groups always show their row count" style="display: flex; align-items: center; gap: 8px; min-height: 36px; font-size: 13px"><input type="checkbox" checked disabled style="width: 18px; height: 18px; accent-color: var(--sta-bd-brand, #1F3A5F)">Count of rows (subtotals and grand total)</label>
            </div>

            <div style="padding: 16px 18px; display: flex; flex-direction: column; gap: 8px">
                <span style="{{ $label }}">Sort</span>
                <div style="display: grid; grid-template-columns: minmax(0, 1fr) 120px; gap: 8px">
                    <select wire:model.live="def.sort.field" aria-label="Sort by" style="{{ $select }}">
                        @foreach ($fields as $key => $field)
                            <option value="{{ $key }}">{{ $field->label }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="def.sort.dir" aria-label="Sort direction" style="{{ $select }}">
                        <option value="asc">A → Z</option>
                        <option value="desc">Z → A</option>
                    </select>
                </div>
            </div>
        </aside>

        <section aria-labelledby="res-title" style="flex: 999 1 560px; min-width: 0; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px; display: flex; flex-direction: column">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; padding: 16px 20px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
                <div style="display: flex; flex-direction: column; gap: 2px">
                    <h2 id="res-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 18px; font-weight: 600">Results</h2>
                    @if ($result)
                        <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">{{ number_format($result->rowCount) }} {{ str('row')->plural($result->rowCount) }}@if ($groups !== []) · grouped by {{ collect($groups)->map(fn ($g) => $fields[$g['field'] ?? '']->label ?? '')->implode(' › ') }}@endif · sorted by {{ $fields[$def['sort']['field'] ?? '']->label ?? '—' }}</span>
                    @endif
                </div>
                <span style="display: inline-flex; align-items: center; height: 28px; padding: 0 12px; border-radius: 999px; background: var(--sta-bg-blue-100, #DBEAFE); color: var(--sta-fg-blue-800, #1E40AF); font-size: 12px; font-weight: 600">Updates as you change settings</span>
            </div>
            @if ($problems !== [])
                <ul role="alert" style="margin: 0; padding: 16px 20px 16px 40px; color: var(--sta-fg-red-700, #B91C1C); font-size: 14px">
                    @foreach ($problems as $problem)
                        <li>{{ $problem }}</li>
                    @endforeach
                </ul>
            @elseif ($result)
                <div style="overflow-x: auto">
                    <table style="width: 100%; min-width: 640px; border-collapse: collapse">
                        <thead>
                            <tr style="text-align: left; font-size: 12px; color: var(--sta-fg-muted, #475569); text-transform: uppercase; letter-spacing: 0.05em">
                                @foreach ($result->columns as $i => $column)
                                    <th scope="col" style="padding: {{ $i === 0 ? '12px 20px' : ($i === $n - 1 ? '12px 20px' : '12px') }}; font-weight: 600">{{ $column->label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result->screenLines($groups !== [] ? ($fields[$groups[0]['field'] ?? '']->label ?? null) : null) as $line)
                                @if ($line['kind'] === 'group' && $line['level'] === 0)
                                    <tr style="background: var(--sta-bg-selected, #E8EEF6); border-top: 1px solid var(--sta-bd-line-strong, #CBD5E1)">
                                        <th scope="colgroup" colspan="{{ $n }}" style="padding: 10px 20px; text-align: left; font-weight: 700; color: var(--sta-fg-brand, #1F3A5F)">{{ $line['label'] }} <span style="font-weight: 500; color: var(--sta-fg-strong, #334155)">· {{ number_format($line['count']) }} {{ str('row')->plural($line['count']) }}</span></th>
                                    </tr>
                                @elseif ($line['kind'] === 'group')
                                    <tr style="background: var(--sta-bg-surface-alt, #F8FAFC); border-top: 1px solid var(--sta-bd-line, #E2E8F0)">
                                        <th scope="colgroup" colspan="{{ $n }}" style="padding: 8px 20px 8px 36px; text-align: left; font-weight: 600; color: var(--sta-fg-strong, #334155)">{{ $line['label'] }} <span style="font-weight: 500; color: var(--sta-fg-muted, #475569)">· {{ number_format($line['count']) }}</span></th>
                                    </tr>
                                @elseif ($line['kind'] === 'row')
                                    <tr style="border-top: 1px solid var(--sta-bd-row, #EEF2F6)">
                                        @foreach ($line['cells'] as $i => $cell)
                                            <td style="padding: {{ $pad($i) }}; white-space: nowrap; {{ $i === 0 ? 'font-weight: 600' : '' }}">{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @else
                                    @php
                                        $cells = $line['cells'];
                                        $text = $line['kind'] === 'subtotal' ? 'Count · '.$line['label'] : $line['label'];
                                        if ($n > 1 && $cells[$n - 1] === '') {
                                            $cells[$n - 1] = number_format($line['count']);
                                        } else {
                                            $text .= ' ('.number_format($line['count']).')';
                                        }
                                        if ($cells[0] !== '') {
                                            $text .= ' · '.$cells[0];
                                        }
                                        $s = $span($cells);
                                        $total = $line['kind'] === 'total';
                                    @endphp
                                    <tr style="{{ $total ? 'border-top: 2px solid var(--sta-bd-brand, #1F3A5F); background: var(--sta-bg-subtle, #F1F5F9)' : 'border-top: 1px solid var(--sta-bd-line, #E2E8F0)' }}">
                                        <th scope="row" colspan="{{ $s }}" style="padding: {{ $total ? '14px 20px' : '8px 20px 8px 36px' }}; text-align: left; font-weight: {{ $total ? '700' : '600' }}; {{ $total ? '' : 'font-size: 13px; color: var(--sta-fg-muted, #475569)' }}">{{ $text }}</th>
                                        @foreach (array_slice($cells, $s, null, true) as $i => $cell)
                                            <td style="padding: {{ $total ? '14px' : '8px' }} {{ $i === $n - 1 ? '20px' : '12px' }}; font-weight: 700; {{ $total ? "font-family: 'Space Grotesk', sans-serif; font-size: 18px" : '' }}">{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; padding: 12px 20px; border-top: 1px solid var(--sta-bd-line, #E2E8F0)">
                    <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">Showing {{ number_format(count($result->rows)) }} of {{ number_format($result->rowCount) }} — export for all</span>
                    <button type="button" wire:click="export" style="min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); font: 600 14px Inter, sans-serif; cursor: pointer">Export to Excel</button>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
