{{-- Drawn only when STA has written something (the canvas shows no empty comments card). Canvas card + list vocabulary. --}}
@if ($comments->isNotEmpty())
    <x-sta.card heading="Comments from STA">
        <ul class="sta-list">
            @foreach ($comments as $c)
                <li class="sta-list-item" style="flex-direction: column; align-items: flex-start; gap: 4px">
                    <span class="sta-who-sub">{{ $c->user?->name ?? 'STA' }} · {{ $c->created_at->format('j M Y H:i') }}</span>
                    <p class="whitespace-pre-line" style="margin: 0; line-height: 1.55">{{ $c->body }}</p>
                </li>
            @endforeach
        </ul>
    </x-sta.card>
@endif
