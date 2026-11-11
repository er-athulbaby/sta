<x-filament-panels::page>
    {{-- Canvas LongListEvents.dc.html (header band via getHeader()): one card per sport with an athlete picker per event and the
         team officials picker, then "People on your Long List". The Long List screen itself is phase3-entry (LongList.dc.html). --}}
    @include('filament.portal.partials.phase-top', ['pdfKey' => null, 'pdfLabel' => null])

    @php
        $p2 = $this->phase2Numbers();
        $people = $this->chipPeople();
        $sports = $this->visibleSports();
        $athleteOptions = $readOnlyReason ? collect() : $this->candidates(\App\Enums\PersonType::Athlete);
        $officialOptions = $readOnlyReason ? collect() : $this->candidates(\App\Enums\PersonType::Official);
        $canAdd = property_exists($this, 'panel') && ! $readOnlyReason;
        // Counts use only the people that get a chip (ids of other associations or deleted people are never counted).
        $shown = fn ($ids) => array_values(array_filter(array_map('intval', (array) $ids), fn ($id) => $people->has($id)));
        $Male = \App\Enums\PersonGender::Male;
        $Female = \App\Enums\PersonGender::Female;
        $outline = 'min-height: 44px; padding: 0 14px; border-radius: 10px; border: 1px solid #1F3A5F; background: #FFFFFF; color: #1F3A5F; font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; gap: 6px; cursor: pointer';
        $plus = '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>';
        $th = 'padding: 12px; font-weight: 600';
        $thEdge = 'padding: 12px 20px; font-weight: 600';
        $headRow = 'text-align: left; font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 0.05em';
        $missingStyle = 'color: #9A3412; font-weight: 600';
    @endphp

    @forelse ($sports as $gs)
        @php
            $sportAthletes = collect($gs->gameEvents)->flatMap(fn ($ge) => $shown($athletes[$ge->id] ?? []))->unique()->count();
            $chosenOfficials = $shown($officials[$gs->id] ?? []);
        @endphp
        <section id="sport-{{ $gs->id }}" aria-labelledby="sport-title-{{ $gs->id }}" wire:key="sport-{{ $gs->id }}" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; display: flex; flex-direction: column">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid #E2E8F0">
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px">
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1F3A5F" stroke-width="2" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>
                    <h2 id="sport-title-{{ $gs->id }}" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 700">{{ $gs->sport->name }}</h2>
                    <span style="font-size: 13px; color: #475569">{{ $gs->gameEvents->count() }} {{ Str::plural('event', $gs->gameEvents->count()) }} · {{ $sportAthletes }} {{ Str::plural('athlete', $sportAthletes) }} · {{ count($chosenOfficials) }} {{ Str::plural('team official', count($chosenOfficials)) }}</span>
                </div>
                @if ($canAdd)
                    <div style="display: flex; flex-wrap: wrap; gap: 10px">
                        <button type="button" wire:click="openPanel('athlete', {{ $gs->id }})" style="{{ $outline }}">{!! $plus !!}Add athlete</button>
                        <button type="button" wire:click="openPanel('official', {{ $gs->id }})" style="{{ $outline }}">{!! $plus !!}Add team official</button>
                    </div>
                @endif
            </div>
            <p style="margin: 0; padding: 12px 20px 0; font-size: 13px; color: #475569">Pick athletes from your registry for each event. Each picker offers only <strong>active</strong> athletes whose gender matches the event (Mixed events accept Male and Female). One athlete can be in several events.@if ($canAdd) <strong>Add athlete</strong> opens the person form in a side panel; the new person appears in these pickers straight away.@endif</p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(440px, 100%), 1fr)); gap: 16px; padding: 16px 20px 20px">
                @foreach ($gs->gameEvents as $ge)
                    @php
                        $chosen = $shown($athletes[$ge->id] ?? []);
                        $n = count($chosen);
                        $max = $p2 === null ? null : ($p2[$ge->id] ?? 0);
                        $err = $errors->first('athletes.'.$ge->id);
                        $evGender = $ge->event->gender;
                        $evLabel = \App\Support\Sta::eventLabel($ge->event);
                        $filter = match ($evGender) {
                            \App\Enums\EventGender::Mixed => 'Active <strong>Male</strong> and <strong>Female</strong> athletes',
                            default => 'Active <strong>'.e($evGender->label()).'</strong> athletes only',
                        };
                        $footer = match ($evGender) {
                            \App\Enums\EventGender::Mixed => 'Inactive athletes are not listed for this event.',
                            \App\Enums\EventGender::Men => 'Female and inactive athletes are not listed for this event.',
                            default => 'Male and inactive athletes are not listed for this event.',
                        };
                    @endphp
                    <div wire:key="event-{{ $ge->id }}" style="display: flex; flex-direction: column; gap: 8px; padding: 14px 16px; border-radius: 12px; border: 1px solid #E2E8F0; background: #FFFFFF">
                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px">
                            @if ($readOnlyReason)<span style="font-weight: 700; font-size: 15px">{{ $evLabel }}</span>@else<label for="event-{{ $ge->id }}" style="font-weight: 700; font-size: 15px">{{ $evLabel }}</label>@endif
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px">
                                <span style="font-size: 13px; color: #334155">Long List <strong>{{ $n }}</strong>@if ($max !== null) / Phase 2: <strong>{{ $max }}</strong>@endif</span>
                                @if ($max !== null && $n > $max)
                                    <span style="display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 999px; background: #FFEDD5; color: #9A3412; font-size: 12px; font-weight: 700"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 21V4"/><path d="M4 4h12l-2 4 2 4H4"/></svg>Over the Phase 2 number</span>
                                @endif
                            </div>
                        </div>
                        @include('filament.portal.partials.person-picker', [
                            'id' => 'event-'.$ge->id, 'placeholder' => 'Search athletes…', 'arg' => $ge->id,
                            'add' => 'addAthleteChip', 'remove' => 'removeAthleteChip', 'chosen' => $chosen, 'error' => $err,
                            'chips' => array_map(fn ($id) => ['person' => $people[$id], 'sub' => $people[$id]->code,
                                'removeLabel' => 'Remove '.$people[$id]->fullName().' from '.$evLabel,
                                'bad' => $err && ! \App\Services\Phases\LongListChecks::genderMatches($people[$id]->gender, $evGender)], $chosen),
                            'options' => $athleteOptions->filter(fn ($p) => \App\Services\Phases\LongListChecks::genderMatches($p->gender, $evGender))
                                ->map(fn ($p) => ['person' => $p, 'sub' => $p->gender?->label().' · '.$p->dob?->format('Y')])->values()->all(),
                            'listLabel' => $evLabel.' athletes', 'filterHtml' => $filter, 'footer' => $footer,
                        ])
                    </div>
                @endforeach
            </div>

            <div wire:key="officials-{{ $gs->id }}" style="margin: 0 20px 20px; padding: 14px 16px; border-radius: 12px; border: 1px solid #E2E8F0; background: #F8FAFC; display: flex; flex-direction: column; gap: 8px">
                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px">
                    @if ($readOnlyReason)<span style="font-weight: 700; font-size: 15px">Team officials</span>@else<label for="officials-{{ $gs->id }}" style="font-weight: 700; font-size: 15px">Team officials</label>@endif
                    <span style="font-size: 13px; color: #475569">For the whole sport, not one event · active officials only</span>
                </div>
                @include('filament.portal.partials.person-picker', [
                    'id' => 'officials-'.$gs->id, 'placeholder' => 'Search officials by name or function', 'arg' => $gs->id,
                    'add' => 'addOfficialChip', 'remove' => 'removeOfficialChip', 'chosen' => $chosenOfficials,
                    'error' => $errors->first('officials.'.$gs->id),
                    'chips' => array_map(fn ($id) => ['person' => $people[$id], 'sub' => $people[$id]->function, 'bad' => false,
                        'removeLabel' => 'Remove '.$people[$id]->fullName().' from '.$gs->sport->name.' team officials'], $chosenOfficials),
                    'options' => $officialOptions->map(fn ($p) => ['person' => $p, 'sub' => (string) $p->function])->values()->all(),
                    'listLabel' => $gs->sport->name.' team officials', 'filterHtml' => 'Active <strong>team officials</strong> only',
                    'footer' => 'Inactive team officials are not listed.',
                ])
            </div>
        </section>
    @empty
        <p class="sta-muted">No sports to enter for your association in this game.</p>
    @endforelse

    @php
        $rows = $this->listRows();
        $events = $sports->flatMap->gameEvents->keyBy('id');
        // Per person: "Sport · events" (athletes) or the sports (officials); an event whose save error is this person's gender is red, "— not saved".
        $placement = [];
        foreach ($athletes as $geId => $ids) {
            $ge = $events->get((int) $geId);
            if (! $ge) {
                continue;
            }
            $err = $errors->has('athletes.'.$ge->id);
            foreach ($shown($ids) as $id) {
                $bad = $err && ! \App\Services\Phases\LongListChecks::genderMatches($people[$id]->gender, $ge->event->gender);
                $placement[$id][$ge->game_sport_id][] = [\App\Support\Sta::eventLabel($ge->event), $bad];
            }
        }
        foreach ($officials as $gsId => $ids) {
            foreach ($shown($ids) as $id) {
                $placement[$id][(int) $gsId] ??= [];
            }
        }
        $sportNames = $sports->mapWithKeys(fn ($gs) => [$gs->id => $gs->sport->name]);
    @endphp
    <section aria-labelledby="people-title" style="display: flex; flex-direction: column; gap: 12px">
        <h2 id="people-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 19px; font-weight: 600">People on your Long List</h2>
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; overflow-x: auto">
            @if ($rows->isEmpty())
                <p style="margin: 0; padding: 16px 20px; color: #475569">Nobody on the list yet.</p>
            @else
                <table style="width: 100%; min-width: 820px; border-collapse: collapse">
                    <thead>
                        <tr style="{{ $headRow }}">
                            <th scope="col" style="{{ $thEdge }}">Person</th>
                            <th scope="col" style="{{ $th }}">Gender</th>
                            <th scope="col" style="{{ $th }}">Role</th>
                            <th scope="col" style="{{ $thEdge }}">Sport · events</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $p = $row['person'];
                                $athlete = $p->type === \App\Enums\PersonType::Athlete;
                                $parts = collect($placement[$p->id] ?? [])->map(fn ($evs, $gsId) => [
                                    'text' => ($sportNames[$gsId] ?? '').($evs ? ' · '.collect($evs)->map(fn ($e) => $e[0].($e[1] ? ' — not saved' : ''))->implode(', ') : ''),
                                    'bad' => collect($evs)->contains(fn ($e) => $e[1]),
                                ]);
                                $bad = $parts->contains('bad', true);
                            @endphp
                            <tr wire:key="people-{{ $p->id }}" style="border-top: 1px solid #EEF2F6">
                                <td style="padding: 10px 20px">
                                    <div style="display: flex; align-items: center; gap: 10px">
                                        <div role="img" aria-label="Photo of {{ $p->fullName() }}" class="sta-avatar">{{ \App\Support\Sta::initials($p->fullName()) }}</div>
                                        <div style="display: flex; flex-direction: column">
                                            <span style="font-weight: 600">{{ $p->fullName() }}</span>
                                            <span style="font-size: 12px; color: #475569">{{ $p->code }}</span>
                                        </div>
                                        @unless ($row['on_list'])
                                            <span style="display: inline-flex; align-items: center; height: 22px; padding: 0 8px; border-radius: 999px; background: #DCFCE7; color: #166534; font-size: 11px; font-weight: 700">Just added</span>
                                        @endunless
                                    </div>
                                </td>
                                <td style="padding: 10px 12px">{{ $p->gender?->label() }}</td>
                                <td style="padding: 10px 12px; color: #334155">{{ $athlete ? 'Athlete' : 'Team official'.(filled($p->function) ? ' · '.$p->function : '') }}</td>
                                <td style="padding: 10px 20px; font-weight: 600; color: {{ $bad ? '#B91C1C' : '#1F3A5F' }}">{{ $parts->pluck('text')->implode('; ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </section>

    @include('filament.portal.partials.person-panel')
    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
