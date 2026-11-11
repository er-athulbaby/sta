<x-filament-panels::page>
    {{-- No canvas screen: built in the Phase2.dc.html structure (header band, deadline banner, main card + aside with the submit block). --}}
    @include('filament.portal.partials.phase-top', ['pdfKey' => 'entry_by_sport', 'pdfLabel' => 'View Entry by Sport PDF', 'canvas' => true])

    <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start">
        <section aria-labelledby="phase1-title" style="flex: 999 1 520px; min-width: 0; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px">
            <div style="display: flex; flex-direction: column; gap: 2px; padding: 16px 20px; border-bottom: 1px solid #E2E8F0">
                <h2 id="phase1-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 600">Sports you were invited to</h2>
                <span style="font-size: 13px; color: #475569">Tell STA whether you plan to take part. Numbers per event come in Phase 2 (Entry by Number).</span>
            </div>
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
        </section>

        <aside aria-label="Submit" style="flex: 1 1 300px; display: flex; flex-direction: column; gap: 16px">
            @include('filament.portal.partials.submit-block', ['pdfName' => 'Entry by Sport'])
        </aside>
    </div>

    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
