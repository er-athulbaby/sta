{{-- Drawn only when STA has written something (the canvas shows no empty comments card). --}}
@if ($comments->isNotEmpty())
    <x-filament::section heading="Comments from STA">
        @foreach ($comments as $c)
            <div class="border-b border-gray-200 py-2 text-sm last:border-0 dark:border-white/10">
                <div class="text-xs text-gray-500">{{ $c->user?->name ?? 'STA' }} · {{ $c->created_at->format('j M Y H:i') }}</div>
                <p class="whitespace-pre-line">{{ $c->body }}</p>
            </div>
        @endforeach
    </x-filament::section>
@endif
