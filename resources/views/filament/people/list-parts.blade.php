{{-- Shared canvas list pieces (Registry.dc.html / AdminPeople.dc.html), bound to the Filament table state of the page.
     $part: 'search' | 'type' | 'name-th' | 'foot'. --}}
@switch($part)
    @case('search')
        <label style="display: flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 12px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; flex: 1 1 {{ $basis }}; background: var(--sta-bg-surface, #FFFFFF)">
            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" style="stroke: var(--sta-fg-muted, #475569)"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <span style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0)">{{ $label }}</span>
            <input type="search" wire:model.live.debounce.500ms="tableSearch" placeholder="{{ $placeholder }}" style="border: 0; outline: none; font: 14px Inter, sans-serif; width: 100%; background: transparent">
        </label>
        @break
    @case('type')
        <div role="group" aria-label="Type" style="display: flex; background: var(--sta-bg-subtle, #F1F5F9); border-radius: 10px; padding: 4px; gap: 4px">
            @foreach (['all' => 'All', 'athletes' => 'Athletes', 'officials' => 'Officials'] as $key => $tabLabel)
                @php $on = ($this->activeTab ?? 'all') === $key; @endphp
                <button type="button" aria-pressed="{{ $on ? 'true' : 'false' }}" wire:click="$set('activeTab', '{{ $key }}')"
                    style="min-height: 36px; padding: 0 14px; border: 0; border-radius: 8px; font: 600 13px Inter, sans-serif; cursor: pointer; {{ $on ? 'background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); box-shadow: 0 1px 2px rgba(15,23,42,0.08)' : 'background: transparent; color: var(--sta-fg-strong, #334155)' }}">{{ $tabLabel }}</button>
            @endforeach
        </div>
        @break
    @case('name-th')
        @php $sorted = $this->getTableSortColumn() === 'last_name' ? $this->getTableSortDirection() : null; @endphp
        <th scope="col" @if ($sorted) aria-sort="{{ $sorted === 'asc' ? 'ascending' : 'descending' }}" @endif style="padding: 12px 20px; font-weight: 600">
            <button type="button" wire:click="sortTable('last_name')" style="display: inline-flex; align-items: center; gap: 4px; min-height: 24px; padding: 0; border: 0; background: transparent; font: inherit; color: inherit; text-transform: inherit; letter-spacing: inherit; cursor: pointer">Name @if ($sorted)<span aria-hidden="true">{{ $sorted === 'asc' ? '↑' : '↓' }}</span>@endif</button>
        </th>
        @break
    @case('foot')
        @php $pageName = $this->getTablePaginationPageName(); @endphp
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px; padding: 14px 20px; border-top: 1px solid var(--sta-bd-line, #E2E8F0); font-size: 13px; color: var(--sta-fg-muted, #475569)">
            <span>{{ $text }}</span>
            @if ($records instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $records->lastPage() > 1)
                <span style="display: inline-flex; align-items: center; gap: 8px">
                    <button type="button" wire:click="previousPage('{{ $pageName }}')" @disabled($records->onFirstPage()) style="min-height: 44px; padding: 0 12px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); font: 600 13px Inter, sans-serif; cursor: pointer">Previous</button>
                    <span>Page {{ $records->currentPage() }} of {{ $records->lastPage() }}</span>
                    <button type="button" wire:click="nextPage('{{ $pageName }}')" @disabled(! $records->hasMorePages()) style="min-height: 44px; padding: 0 12px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); font: 600 13px Inter, sans-serif; cursor: pointer">Next</button>
                </span>
            @endif
        </div>
        @break
@endswitch
