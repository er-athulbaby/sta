{{-- Canvas AdminAssociations.dc.html header: h1, the counts subtitle and New association. --}}
<header style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 16px">
    <div style="display: flex; flex-direction: column; gap: 6px">
        <h1 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 700">Associations</h1>
        <span style="color: var(--sta-fg-muted, #475569)">{{ $this->getSubheading() }}</span>
    </div>
    @if (\App\Filament\Resources\Associations\AssociationResource::canCreate())
        <a href="{{ \App\Filament\Resources\Associations\AssociationResource::getUrl('create') }}" style="min-height: 44px; padding: 0 18px; border-radius: 10px; border: 0; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF); font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; text-decoration: none; box-sizing: border-box">New association</a>
    @endif
</header>
