{{-- Canvas Main.dc.html header: breadcrumb, h1, subtitle, Export Excel and "Remind pending (N)"; then the existing
     dashboard links the canvas does not draw (Edit game, Game system files, Reports) in the secondary style. --}}
@php
    $secondary = 'min-height: 44px; padding: 0 18px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; box-sizing: border-box; cursor: pointer';
    $links = collect(['editGame', 'gameFiles', 'reports'])->map(fn ($n) => $this->getAction($n, false))->filter(fn ($a) => $a?->isVisible());
    $remind = $this->getAction('remindPending', false);
@endphp
<header style="display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px">
    <div style="display: flex; flex-direction: column; gap: 6px">
        <span style="font-size: 13px; color: #475569"><a href="{{ \App\Filament\Resources\Games\GameResource::getUrl() }}">Games</a> / {{ $this->record->name }}</span>
        <h1 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 700; letter-spacing: -0.01em">{{ $this->record->name }}</h1>
        <span style="color: #475569">{{ $this->getSubheading() }}</span>
    </div>
    <div style="display: flex; flex-wrap: wrap; gap: 12px">
        @if ($this->canExport())
            <button type="button" wire:click="exportExcel" style="{{ $secondary }}">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                Export Excel
            </button>
        @endif
        @if ($remind?->isVisible())
            <button type="button" wire:click="mountAction('remindPending')" style="min-height: 44px; padding: 0 18px; border-radius: 10px; border: 0; background: #1F3A5F; color: #FFFFFF; font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; gap: 8px; cursor: pointer">
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
                {{ $remind->getLabel() }}
            </button>
        @endif
        @foreach ($links as $a)
            <a href="{{ $a->getUrl() }}" style="{{ $secondary }}">{{ $a->getLabel() }}</a>
        @endforeach
    </div>
</header>
