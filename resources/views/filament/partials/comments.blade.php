<x-filament::section heading="Comments from STA">
    @forelse ($comments as $c)
        <div class="border-b border-gray-200 py-2 text-sm last:border-0 dark:border-white/10">
            <div class="text-xs text-gray-500">{{ $c->user?->name ?? 'STA' }} · {{ $c->created_at->format('j M Y H:i') }}</div>
            <p class="whitespace-pre-line">{{ $c->body }}</p>
        </div>
    @empty
        <p class="text-sm text-gray-500">No comments.</p>
    @endforelse
</x-filament::section>
