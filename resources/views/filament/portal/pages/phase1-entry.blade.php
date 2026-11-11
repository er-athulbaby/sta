<x-filament-panels::page>
    @include('filament.portal.partials.phase-top', ['pdfKey' => 'entry_by_sport', 'pdfLabel' => 'View Entry by Sport PDF'])

    <x-sta.card heading="Sports you were invited to" description="Tell STA whether you plan to take part. Numbers per event come in Phase 2 (Entry by Number).">
        <ul class="sta-list">
            @forelse ($this->sports as $gs)
                <li class="sta-list-item" wire:key="sport-{{ $gs->id }}">
                    <span class="sta-who-name" style="flex: 1 1 160px">{{ $gs->sport->name }}</span>
                    <div class="sta-seg" role="radiogroup" aria-label="{{ $gs->sport->name }}">
                        @foreach (['yes' => 'Yes', 'no' => 'No', 'maybe' => 'Maybe'] as $value => $label)
                            <label>
                                <input type="radio" name="answer-{{ $gs->id }}" wire:model="answers.{{ $gs->id }}" value="{{ $value }}" @disabled($readOnlyReason)>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @unless (isset($answers[$gs->id]))
                        <x-sta.pill status="none">No response</x-sta.pill>
                    @endunless
                    <label class="sta-field" style="flex: 2 1 240px">
                        <span class="sta-sr-only">Note for {{ $gs->sport->name }}</span>
                        <input type="text" maxlength="255" class="sta-input" placeholder="Note (optional)" wire:model="notes.{{ $gs->id }}" @disabled($readOnlyReason)>
                    </label>
                    @error('notes.'.$gs->id)<p class="sta-error" role="alert">{{ $message }}</p>@enderror
                </li>
            @empty
                <li class="sta-list-item sta-muted">No sports for your association in this game.</li>
            @endforelse
        </ul>
    </x-sta.card>

    @unless ($readOnlyReason)
        <div><x-filament::button color="gray" wire:click="save">Save as draft</x-filament::button></div>
    @endunless
    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
