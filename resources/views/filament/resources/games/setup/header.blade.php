<header style="display: flex; flex-direction: column; gap: 14px">
    <span style="font-size: 13px; color: #475569"><a href="{{ \App\Filament\Resources\Games\GameResource::getUrl() }}">Games</a> / {{ $record->name }} / Setup</span>
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px">
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px">
            <h1 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 30px; font-weight: 700">Game setup</h1>
            @if ($record->isPublished())
                <span style="display: inline-flex; align-items: center; height: 28px; padding: 0 12px; border-radius: 999px; background: #DCFCE7; color: #166534; font-size: 12px; font-weight: 600">Published</span>
            @else
                <span style="display: inline-flex; align-items: center; height: 28px; padding: 0 12px; border-radius: 999px; background: #E0E7FF; color: #1E3A8A; font-size: 12px; font-weight: 600">Draft · not visible to associations</span>
            @endif
        </div>
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px">
            @unless ($record->isPublished())
                <span style="font-size: 12px; color: #475569">Publish needs 1+ invited association and 1+ sport</span>
            @endunless
            <button type="button" wire:click="saveDraft" style="min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 14px Inter, sans-serif; cursor: pointer">{{ $record->isPublished() ? 'Save' : 'Save as draft' }}</button>
            @unless ($record->isPublished())
                <button type="button" wire:click="mountAction('publish')" style="min-height: 44px; padding: 0 18px; border-radius: 10px; border: 0; background: #1F3A5F; color: #FFFFFF; font: 600 14px Inter, sans-serif; cursor: pointer">Publish</button>
            @endunless
        </div>
    </div>
    <div role="tablist" aria-label="Setup steps" style="display: flex; flex-wrap: wrap; gap: 4px; border-bottom: 1px solid #E2E8F0">
        @foreach ($tabs as $page => $label)
            <a href="{{ \App\Filament\Resources\Games\GameResource::getUrl($page, ['record' => $record]) }}" @if ($page === $step) aria-current="page" @endif
               style="display: inline-flex; align-items: center; min-height: 44px; padding: 0 16px; border-bottom: 2px solid {{ $page === $step ? '#1F3A5F' : 'transparent' }}; color: {{ $page === $step ? '#0F172A' : '#475569' }}; font-weight: 600; text-decoration: none">{{ $label }}</a>
        @endforeach
    </div>
</header>
