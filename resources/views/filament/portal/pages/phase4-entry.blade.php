<x-filament-panels::page>
    @include('filament.portal.partials.phase-top', ['pdfKey' => 'short_list', 'pdfLabel' => 'View Short List PDF'])

    @if (! $this->longListSubmitted())
        <div class="sta-banner sta-banner-warn" role="status">Submit the Long List first.</div>
    @else
        @php
            [$slotAthletes, $slotOfficials] = $this->longListSlots();
            $people = $this->longListPeople();
            $over = $this->overLimits();
            $sports = $this->visibleSports();
        @endphp

        <div class="sta-banner sta-banner-info" role="note">Everyone on your Long List is shown per sport. Tick the people for the Short List — the counts update as you tick. Going over a maximum is allowed only with a justification.</div>

        <div class="sta-split">
            <div class="sta-main">
                @foreach ($sports as $gs)
                    @php
                        $events = $gs->gameEvents->filter(fn ($ge) => ! empty($slotAthletes[$ge->id]));
                        $ticked = $this->tickedCounts($gs);
                        $sportOver = collect($over)->contains('sport', $gs->sport->name);
                    @endphp
                    @continue($events->isEmpty() && empty($slotOfficials[$gs->id]))
                    <x-sta.card :heading="$gs->sport->name"
                        :description="'Ticked: '.$ticked['male'].' male, '.$ticked['female'].' female, '.$ticked['officials'].' team officials'"
                        wire:key="sport-{{ $gs->id }}">
                        <x-slot:actions>
                            <x-sta.pill :status="$sportOver ? 'warn' : 'ok'">{{ $sportOver ? 'Over a maximum' : 'Within maximums' }}</x-sta.pill>
                        </x-slot:actions>

                        @if ($events->isNotEmpty())
                            <h3 class="sta-subhead">Athletes</h3>
                            <div class="sta-scroll">
                                <table class="sta-table">
                                    <thead><tr><th scope="col">On Short List</th><th scope="col">Gender</th><th scope="col">Event</th><th scope="col">Passport</th></tr></thead>
                                    <tbody>
                                        @foreach ($events as $ge)
                                            @foreach ($slotAthletes[$ge->id] as $id)
                                                @php $p = $people->get((int) $id); @endphp
                                                @continue(! $p)
                                                <tr wire:key="a-{{ $ge->id }}-{{ $id }}">
                                                    <td>
                                                        <label class="sta-tick">
                                                            <input type="checkbox" value="{{ $id }}" wire:model.live="athletes.{{ $ge->id }}" @disabled($readOnlyReason)>
                                                            <x-sta.person-avatar :name="$p->fullName()" />
                                                            <span class="sta-who-text"><span class="sta-who-name">{{ $p->fullName() }}</span><span class="sta-who-sub">{{ $p->code }}</span></span>
                                                        </label>
                                                    </td>
                                                    <td>{{ $p->gender?->label() }}</td>
                                                    <td class="sta-entered">{{ $ge->event->name }} ({{ $ge->event->gender->label() }})</td>
                                                    <td>
                                                        @if ($p->passport_scan_path)
                                                            <a href="{{ \App\Filament\Shared\AttachmentActions::url($p->passport_scan_path) }}" target="_blank" aria-label="View passport scan of {{ $p->fullName() }}">View</a>
                                                        @else
                                                            <span class="sta-muted">Missing</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            @error('athletes.'.$ge->id)<tr><td colspan="4"><p class="sta-error" role="alert">{{ $message }}</p></td></tr>@enderror
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if (! empty($slotOfficials[$gs->id]))
                            <h3 class="sta-subhead sta-subhead-rule">Team officials</h3>
                            <div class="sta-scroll">
                                <table class="sta-table">
                                    <thead><tr><th scope="col">On Short List</th><th scope="col">Function</th><th scope="col">Passport</th></tr></thead>
                                    <tbody>
                                        @foreach ($slotOfficials[$gs->id] as $id)
                                            @php $p = $people->get((int) $id); @endphp
                                            @continue(! $p)
                                            <tr wire:key="o-{{ $gs->id }}-{{ $id }}">
                                                <td>
                                                    <label class="sta-tick">
                                                        <input type="checkbox" value="{{ $id }}" wire:model.live="officials.{{ $gs->id }}" @disabled($readOnlyReason)>
                                                        <span class="sta-who-text"><span class="sta-who-name">{{ $p->fullName() }}</span><span class="sta-who-sub">{{ $p->code }}</span></span>
                                                    </label>
                                                </td>
                                                <td>{{ $p->function }}</td>
                                                <td>
                                                    @if ($p->passport_scan_path)
                                                        <a href="{{ \App\Filament\Shared\AttachmentActions::url($p->passport_scan_path) }}" target="_blank" aria-label="View passport scan of {{ $p->fullName() }}">View</a>
                                                    @else
                                                        <span class="sta-muted">Missing</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        @error('officials.'.$gs->id)<tr><td colspan="3"><p class="sta-error" role="alert">{{ $message }}</p></td></tr>@enderror
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-sta.card>
                @endforeach

                <x-sta.card heading="People on the Short List">
                    @include('filament.shared.long-list-people', ['rows' => $this->listRows()])
                </x-sta.card>
            </div>

            <aside class="sta-aside" aria-label="Short List maximums">
                <x-sta.card heading="Ticked vs maximum" description="Updates as you tick">
                    <div class="sta-card-body sta-stack" aria-live="polite">
                        @foreach ($sports as $gs)
                            @php $ticked = $this->tickedCounts($gs); @endphp
                            <p class="sta-meter-label">{{ $gs->sport->name }}</p>
                            <x-sta.meter label="Male" :value="$ticked['male']" :max="$gs->p4_max_men" style="--meter: #6D28D9" />
                            <x-sta.meter label="Female" :value="$ticked['female']" :max="$gs->p4_max_women" style="--meter: #6D28D9" />
                            <x-sta.meter label="Team officials" :value="$ticked['officials']" :max="$gs->p4_max_officials" style="--meter: #6D28D9" />
                        @endforeach
                    </div>
                </x-sta.card>

                <x-sta.card heading="Justification needed" :band="$over ? 'violet' : null" :class="$over ? 'sta-justify' : ''">
                    <div class="sta-card-body sta-stack">
                        @if ($over)
                            <ul class="sta-warn-list">
                                @foreach ($over as $o)<li>{{ \App\Services\Phases\ShortListChecks::describe($o) }}</li>@endforeach
                            </ul>
                        @else
                            <p class="sta-muted" role="status">Within all maximums — no justification needed.</p>
                        @endif
                        <label class="sta-field" for="justification">Why do you need more than the maximum? (required to submit when over, at most 2000 characters)
                            <textarea id="justification" wire:model="justification" rows="6" maxlength="2000" class="sta-input" @disabled($readOnlyReason)></textarea>
                        </label>
                        @error('justification')<p class="sta-error">{{ $message }}</p>@enderror
                        <span class="sta-muted">STA sees this on the Short List PDF and decides.</span>
                    </div>
                </x-sta.card>
            </aside>
        </div>

        @unless ($readOnlyReason)
            <div><x-filament::button color="gray" wire:click="save">Save as draft</x-filament::button></div>
        @endunless
    @endif

    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
