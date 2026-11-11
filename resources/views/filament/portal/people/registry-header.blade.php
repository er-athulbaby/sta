{{-- Canvas Registry.dc.html header: h1, the counts subtitle, Download template / Import Excel / Add person. --}}
@php
    $canImport = \App\Filament\Portal\Pages\ImportPeople::canAccess();
    $secondary = 'min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; text-decoration: none; box-sizing: border-box; cursor: pointer';
@endphp
<header style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 16px">
    <div style="display: flex; flex-direction: column; gap: 6px">
        <h1 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 700">Athletes &amp; officials</h1>
        <span style="color: #475569">{{ $this->getSubheading() }}</span>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 12px">
        @if ($canImport)
            <button type="button" wire:click="downloadTemplate" style="{{ $secondary }}">Download template</button>
            <a href="{{ \App\Filament\Portal\Pages\ImportPeople::getUrl() }}" style="{{ $secondary }}">Import Excel</a>
        @endif
        @if (\App\Filament\Portal\Resources\People\PersonResource::canCreate())
            <a href="{{ \App\Filament\Portal\Resources\People\PersonResource::getUrl('create') }}" class="sta-btn-primary" style="min-height: 44px; padding: 0 18px; border-radius: 10px; border: 0; background: #1F3A5F; color: #FFFFFF; font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; text-decoration: none; box-sizing: border-box">Add person</a>
        @endif
    </div>
</header>
