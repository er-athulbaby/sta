{{-- The user-group permissions grid: rows = STA areas (then the special rights), columns = Access, New, Save, Delete,
     Print. A cell that does not apply to a row is left blank. A column header box ticks or clears the whole column, a row
     box its whole row; ticking New, Save, Delete or Print also ticks Access, and clearing Access clears the row. The
     server normalises the same way (StaArea::normalise). --}}
@php
    use App\Enums\StaAction;
    use App\Enums\StaArea;

    $actions = StaAction::cases();
    $rows = collect(StaArea::cases())->map(fn (StaArea $a) => [
        'key' => $a->value, 'label' => $a->label(), 'special' => $a->isSpecial(),
        'cells' => collect($a->actions())->mapWithKeys(fn (StaAction $x) => [$x->value => $a->permission($x)])->all(),
    ])->values();
    $disabled = $isDisabled();
    $cellStyle = 'padding: 0; text-align: center; width: 72px';
    $boxLabel = 'display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; cursor: pointer';
    $box = 'width: 18px; height: 18px; accent-color: var(--sta-bg-brand, #1F3A5F); cursor: pointer';
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle(@js($getStatePath())),
            rows: @js($rows),
            has(p) { return Array.isArray(this.state) && this.state.includes(p) },
            set(p, on) {
                let s = new Set(Array.isArray(this.state) ? this.state : []);
                on ? s.add(p) : s.delete(p);
                this.state = Array.from(s);
            },
            toggle(row, action, on) {
                const cells = row.cells;
                this.set(cells[action], on);
                if (on && action !== 'access' && cells.access) this.set(cells.access, true);
                if (! on && action === 'access') Object.values(cells).forEach(p => this.set(p, false));
            },
            rowOn(row) { return Object.values(row.cells).every(p => this.has(p)) },
            toggleRow(row, on) { Object.keys(row.cells).forEach(a => this.toggle(row, a, on)) },
            colRows(action) { return this.rows.filter(r => r.cells[action]) },
            colOn(action) { const r = this.colRows(action); return r.length > 0 && r.every(row => this.has(row.cells[action])) },
            toggleCol(action, on) { this.colRows(action).forEach(row => this.toggle(row, action, on)) },
        }"
        style="overflow: auto; max-height: 70vh; border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 12px; background: var(--sta-bg-surface, #FFFFFF)"
    >
        <table style="width: 100%; min-width: 620px; border-collapse: separate; border-spacing: 0; font-size: 14px">
            <caption style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0)">Permissions of this group</caption>
            <thead>
                <tr>
                    <th scope="col" style="position: sticky; top: 0; z-index: 1; text-align: left; padding: 8px 16px; background: var(--sta-bg-subtle, #F1F5F9); border-bottom: 1px solid var(--sta-bd-line, #E2E8F0); font-weight: 600">Page / module</th>
                    @foreach ($actions as $a)
                        <th scope="col" style="position: sticky; top: 0; z-index: 1; {{ $cellStyle }}; background: var(--sta-bg-subtle, #F1F5F9); border-bottom: 1px solid var(--sta-bd-line, #E2E8F0); font-weight: 600">
                            <label style="display: flex; flex-direction: column; align-items: center; gap: 2px; min-height: 44px; justify-content: center; cursor: pointer">
                                <span>{{ $a->label() }}</span>
                                <input type="checkbox" style="{{ $box }}" aria-label="All rows: {{ $a->label() }}" @disabled($disabled)
                                    :checked="colOn('{{ $a->value }}')" x-on:change="toggleCol('{{ $a->value }}', $event.target.checked)">
                            </label>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $i => $row)
                    @if ($row['special'] && ! $rows[$i - 1]['special'])
                        <tr><th colspan="{{ count($actions) + 1 }}" scope="rowgroup" style="text-align: left; padding: 14px 16px 6px; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--sta-fg-muted, #475569); border-top: 1px solid var(--sta-bd-line, #E2E8F0)">Special rights</th></tr>
                    @endif
                    <tr wire:key="grid-{{ $row['key'] }}" style="background: {{ $i % 2 ? 'var(--sta-bg-surface-alt, #F8FAFC)' : 'var(--sta-bg-surface, #FFFFFF)' }}">
                        <th scope="row" style="text-align: left; padding: 0 16px; font-weight: 500">
                            <label style="display: flex; align-items: center; gap: 10px; min-height: 44px; cursor: pointer">
                                <input type="checkbox" style="{{ $box }}" aria-label="{{ $row['label'] }}: all" @disabled($disabled)
                                    :checked="rowOn(rows[{{ $i }}])" x-on:change="toggleRow(rows[{{ $i }}], $event.target.checked)">
                                <span>{{ $row['label'] }}</span>
                            </label>
                        </th>
                        @foreach ($actions as $a)
                            <td style="{{ $cellStyle }}">
                                @isset($row['cells'][$a->value])
                                    <label style="{{ $boxLabel }}">
                                        <input type="checkbox" style="{{ $box }}" aria-label="{{ $row['label'] }}: {{ $a->label() }}" @disabled($disabled)
                                            :checked="has('{{ $row['cells'][$a->value] }}')" x-on:change="toggle(rows[{{ $i }}], '{{ $a->value }}', $event.target.checked)">
                                    </label>
                                @endisset
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-dynamic-component>
