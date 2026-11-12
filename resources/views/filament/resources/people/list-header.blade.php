{{-- Canvas AdminPeople.dc.html header: h1 People, subtitle, Export Excel (plus Add person, the existing create path). --}}
@php
    $secondary = 'min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; text-decoration: none; box-sizing: border-box; cursor: pointer';
@endphp
<header style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 16px">
    <div style="display: flex; flex-direction: column; gap: 6px">
        <h1 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 700">People</h1>
        <span style="color: var(--sta-fg-muted, #475569)">{{ $this->getSubheading() }}</span>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 12px">
        <button type="button" wire:click="exportExcel" style="{{ $secondary }}">Export Excel</button>
        @if (\App\Filament\Resources\People\PersonResource::canCreate())
            <a href="{{ \App\Filament\Resources\People\PersonResource::getUrl('create') }}" style="{{ $secondary }}">Add person</a>
        @endif
    </div>
</header>
