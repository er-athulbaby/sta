<header style="display: flex; flex-direction: column; gap: 14px">
    <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)"><a href="{{ \App\Filament\Resources\Games\GameResource::getUrl() }}">Games</a> / {{ $record->name }} / Setup</span>
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px">
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px">
            <h1 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 700">Game setup</h1>
            @if ($record->isPublished())
                <span style="display: inline-flex; align-items: center; height: 28px; padding: 0 12px; border-radius: 999px; background: var(--sta-bg-green-100, #DCFCE7); color: var(--sta-fg-green-800, #166534); font-size: 12px; font-weight: 600">Published</span>
            @else
                <span style="display: inline-flex; align-items: center; height: 28px; padding: 0 12px; border-radius: 999px; background: var(--sta-bg-indigo-100, #E0E7FF); color: var(--sta-fg-blue-900, #1E3A8A); font-size: 12px; font-weight: 600">Draft · not visible to associations</span>
            @endif
        </div>
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px">
            @unless ($record->isPublished())
                <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">Publish needs 1+ invited association and 1+ sport</span>
            @endunless
            <button type="button" wire:click="saveDraft" style="min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); font: 600 14px Inter, sans-serif; cursor: pointer">{{ $record->isPublished() ? 'Save' : 'Save as draft' }}</button>
            @unless ($record->isPublished())
                <button type="button" wire:click="mountAction('publish')" style="min-height: 44px; padding: 0 18px; border-radius: 10px; border: 0; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF); font: 600 14px Inter, sans-serif; cursor: pointer">Publish</button>
            @endunless
        </div>
    </div>
    <div role="tablist" aria-label="Setup steps" style="display: flex; flex-wrap: wrap; gap: 4px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
        @foreach ($tabs as $page => $label)
            <a href="{{ \App\Filament\Resources\Games\GameResource::getUrl($page, ['record' => $record]) }}" @if ($page === $step) aria-current="page" @endif
               style="display: inline-flex; align-items: center; min-height: 44px; padding: 0 16px; border-bottom: 2px solid {{ $page === $step ? 'var(--sta-bd-brand, #1F3A5F)' : 'transparent' }}; color: {{ $page === $step ? 'var(--sta-fg-text, #0F172A)' : 'var(--sta-fg-muted, #475569)' }}; font-weight: 600; text-decoration: none">{{ $label }}</a>
        @endforeach
    </div>
</header>
