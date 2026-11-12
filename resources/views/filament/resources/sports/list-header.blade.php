{{-- Canvas AdminSports.dc.html header: h1, subtitle and New sport. --}}
<header style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 16px">
    <div style="display: flex; flex-direction: column; gap: 6px">
        <h1 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 700">Sports &amp; events</h1>
        <span style="color: var(--sta-fg-muted, #475569)">Association → sports → events. Only STA can change these; associations see them read-only.</span>
    </div>
    @if (\App\Filament\Resources\Sports\SportResource::canCreate())
        <a href="{{ \App\Filament\Resources\Sports\SportResource::getUrl('create') }}" style="min-height: 44px; padding: 0 18px; border-radius: 10px; border: 0; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF); font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; text-decoration: none; box-sizing: border-box">New sport</a>
    @endif
</header>
