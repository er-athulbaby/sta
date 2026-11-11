{{-- Canvas AdminDashboard.dc.html header: h1, the date subtitle and New game. --}}
<header style="display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px">
    <div style="display: flex; flex-direction: column; gap: 6px">
        <h1 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 700; letter-spacing: -0.01em">Dashboard</h1>
        <span style="color: #475569">{{ $this->getSubheading() }}</span>
    </div>
    @if ($this->allowed(\App\Enums\StaPermission::ManageGames))
        <a href="{{ \App\Filament\Resources\Games\GameResource::getUrl('create') }}" style="min-height: 44px; padding: 0 18px; border-radius: 10px; background: #1F3A5F; color: #FFFFFF; font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; gap: 8px; text-decoration: none">
            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            New game
        </a>
    @endif
</header>
