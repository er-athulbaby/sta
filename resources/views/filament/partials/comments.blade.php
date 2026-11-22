{{-- Drawn only once STA has written a remark (the canvas shows no empty comments card). Canvas card + list vocabulary.
     Two-way (M6): association admins and editors reply from the portal phase page; STA adds remarks from its own pages. --}}
@if ($comments->isNotEmpty())
    <x-sta.card heading="Remarks" description="Between STA and the association · newest first">
        <ul class="sta-list">
            @foreach ($comments as $c)
                <li class="sta-list-item" style="flex-direction: column; align-items: flex-start; gap: 4px">
                    <span class="sta-who-sub">{{ $c->user?->name ?? ($c->from_association ? 'Association' : 'STA') }} · {{ $c->from_association ? ($c->user?->association?->name ?? 'Association') : 'STA' }} · {{ $c->created_at->timezone('Asia/Bahrain')->format('j M Y H:i') }}</span>
                    <p class="whitespace-pre-line" style="margin: 0; line-height: 1.55">{{ $c->body }}</p>
                </li>
            @endforeach
        </ul>
        @if (filament()->getId() === 'portal')
            <x-slot:footer>
                <form wire:submit="reply" style="display: flex; flex-direction: column; gap: 8px; width: 100%">
                    <label for="sta-reply" style="font-weight: 600; font-size: 14px">Reply to STA</label>
                    <textarea id="sta-reply" wire:model="replyBody" class="sta-input" maxlength="5000" rows="3" required></textarea>
                    @error('replyBody')<span role="alert" style="font-size: 13px; color: var(--sta-fg-red-800, #991B1B)">{{ $message }}</span>@enderror
                    <div><button type="submit" class="sta-btn sta-btn-primary" wire:loading.attr="disabled" wire:target="reply">Send reply</button></div>
                </form>
            </x-slot:footer>
        @endif
    </x-sta.card>
@endif
