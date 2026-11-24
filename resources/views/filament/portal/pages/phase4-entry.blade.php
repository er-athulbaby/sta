<x-filament-panels::page>
    {{-- Canvas: ShortList.dc.html (header band via getHeader()). --}}
    @include('filament.portal.partials.phase-top', ['pdfKey' => 'short_list', 'pdfLabel' => 'View Short List PDF'])

    @if (! $this->longListSubmitted())
        <div class="sta-banner sta-banner-warn" role="status">Submit the Long List first.</div>
    @else
        @php
            [$slotAthletes, $slotOfficials] = $this->longListSlots();
            $people = $this->longListPeople();
            $over = $this->overLimits();
            $fns = \App\Services\Phases\LongListChecks::officialFunctions($this->submission);
            $kinds = ['men' => 'Male', 'women' => 'Female', 'officials' => 'Team officials'];
            $overText = collect($over)->groupBy('sport')
                ->map(fn ($items, $sport) => $sport.' — '.$items->map(fn ($o) => $kinds[$o['kind']].' '.$o['count'].' / max '.$o['max'])->implode(' · '))
                ->implode('; ');
            // People of the Long List per sport (only those that still exist in this association) and the ticked ids.
            $ids = fn ($list) => array_values(array_filter(array_map('intval', (array) $list), fn ($id) => $people->has($id)));
            $sports = $this->visibleSports()->map(function ($gs) use ($slotAthletes, $slotOfficials, $ids) {
                $events = $gs->gameEvents->filter(fn ($ge) => $ids($slotAthletes[$ge->id] ?? []) !== []);
                $officialIds = $ids($slotOfficials[$gs->id] ?? []);

                return $events->isEmpty() && $officialIds === [] ? null : ['gs' => $gs, 'events' => $events, 'officials' => $officialIds,
                    'athletes' => $events->flatMap(fn ($ge) => $ids($slotAthletes[$ge->id]))->unique()->count(), 'ticked' => $this->tickedCounts($gs)];
            })->filter();
            $isOver = fn (int $v, $max) => $max !== null && $v > $max;
            // The pill uses ShortListChecks' over list (same counts as submit and the PDF), matched by game sport id.
            $sportOver = fn ($s) => collect($over)->contains('game_sport_id', $s['gs']->id);
            $subhead = 'margin: 0; font-size: 13px; font-weight: 700; color: var(--sta-fg-muted, #475569); text-transform: uppercase; letter-spacing: 0.05em';
            $pill = 'display: inline-flex; align-items: center; height: 28px; padding: 0 12px; border-radius: 999px; font-size: 12px; font-weight: 600; ';
        @endphp

        <div role="note" style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 12px; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); color: var(--sta-fg-strong, #334155)">
            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" style="stroke: var(--sta-fg-violet-800, #5B21B6)"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v5h1"/></svg>
            Everyone on your Long List is shown per sport. Move people to the Short List column by dragging a card or with Add → / ← Remove — the counts update as you go. Going over a maximum is allowed only with a justification.
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start">
            {{-- Two columns per sport: the Long List people not picked, and the Short List. One card per Short List slot (athletes per
                 event, so an athlete in two events has two cards; team officials per sport). Drag a card across, or use its button. --}}
            <div style="flex: 999 1 560px; min-width: 0; display: flex; flex-direction: column; gap: 20px">
                @foreach ($sports as $s)
                    @php
                        $gs = $s['gs']; $t = $s['ticked'];
                        $tickedOfficials = array_map('intval', (array) ($officials[$gs->id] ?? []));
                        $groups = [
                            ['key' => 'a', 'kind' => 'athletes', 'title' => 'Athletes',
                                'entries' => $s['events']->flatMap(function ($ge) use ($athletes, $slotAthletes, $ids, $people) {
                                    $ticked = array_map('intval', (array) ($athletes[$ge->id] ?? []));

                                    return collect($ids($slotAthletes[$ge->id]))->map(fn ($id) => ['p' => $people[$id], 'id' => $id, 'slot' => $ge->id,
                                        'on' => in_array($id, $ticked, true), 'detail' => \App\Support\Sta::eventLabel($ge->event)]);
                                })],
                            ['key' => 'o', 'kind' => 'officials', 'title' => 'Team officials',
                                'entries' => collect($s['officials'])->map(fn ($id) => ['p' => $people[$id], 'id' => $id, 'slot' => $gs->id,
                                    'on' => in_array($id, $tickedOfficials, true), 'detail' => $fns[$gs->id][$id] ?? $people[$id]->function])],
                        ];
                        $errorKeys = [...$s['events']->map(fn ($ge) => 'athletes.'.$ge->id)->values()->all(), 'officials.'.$gs->id];
                        $meters = [['Men', $t['male'], $gs->p4_max_men], ['Women', $t['female'], $gs->p4_max_women], ['Team officials', $t['officials'], $gs->p4_max_officials]];
                    @endphp
                    <section aria-labelledby="sport-title-{{ $gs->id }}" wire:key="sport-{{ $gs->id }}" style="background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px"
                        @unless ($readOnlyReason) x-data="{ drop(e, method) { const d = (e.dataTransfer.getData('text/plain') || '').split('|'); if (d.length === 3) this.$wire.call(method, d[0], +d[1], +d[2]) } }" @endunless>
                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
                            <div style="display: flex; flex-direction: column; gap: 2px">
                                <h2 id="sport-title-{{ $gs->id }}" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 600">{{ $gs->sport->name }}</h2>
                                <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">Long List: {{ $s['athletes'] }} {{ Str::plural('athlete', $s['athletes']) }}, {{ count($s['officials']) }} {{ Str::plural('team official', count($s['officials'])) }} · <strong style="color: var(--sta-fg-text, #0F172A)">Short List: {{ $t['male'] }} male, {{ $t['female'] }} female, {{ $t['officials'] }} team officials</strong></span>
                            </div>
                            <span style="{{ $pill }}{{ $sportOver($s) ? 'background: var(--sta-bg-orange-100, #FFEDD5); color: var(--sta-fg-orange-800, #9A3412)' : 'background: var(--sta-bg-green-100, #DCFCE7); color: var(--sta-fg-green-800, #166534)' }}">{{ $sportOver($s) ? 'Over a maximum' : 'Within maximums' }}</span>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr)); gap: 16px; padding: 16px 20px">
                            @foreach ([false, true] as $onList)
                                @php
                                    $count = collect($groups)->sum(fn ($g) => $g['entries']->where('on', $onList)->count());
                                    $method = $onList ? 'removeFromShortList' : 'addToShortList';
                                    $dropMethod = $onList ? 'addToShortList' : 'removeFromShortList';
                                    $colTitle = $onList ? 'Short List' : 'Long List — not selected';
                                @endphp
                                <div role="region" aria-label="{{ $gs->sport->name }} {{ $colTitle }}" data-column="{{ $onList ? 'short' : 'long' }}"
                                    @unless ($readOnlyReason) x-on:dragover.prevent x-on:drop.prevent="drop($event, '{{ $dropMethod }}')" @endunless
                                    style="display: flex; flex-direction: column; gap: 8px; min-width: 0; padding: 12px; border-radius: 12px; background: {{ $onList ? 'var(--sta-bg-violet-50, #F5F3FF)' : 'var(--sta-bg-surface-alt, #F8FAFC)' }}; border: {{ $readOnlyReason ? '1px solid' : '2px dashed' }} {{ $onList ? 'var(--sta-bd-violet-400, #A78BFA)' : 'var(--sta-bd-line-strong, #CBD5E1)' }}">
                                    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 6px">
                                        <h3 style="{{ $subhead }}">{{ $colTitle }} ({{ $count }})</h3>
                                        @if ($onList)
                                            <span style="display: flex; flex-wrap: wrap; gap: 6px; font-size: 12px; font-weight: 700">
                                                @foreach ($meters as [$label, $v, $max])
                                                    <span style="padding: 2px 8px; border-radius: 999px; {{ $isOver($v, $max) ? 'background: var(--sta-bg-orange-100, #FFEDD5); color: var(--sta-fg-orange-800, #9A3412)' : 'background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-strong, #334155)' }}">{{ $label }} {{ $v }}{{ $max === null ? '' : ' / '.$max }}</span>
                                                @endforeach
                                            </span>
                                        @endif
                                    </div>
                                    @foreach ($groups as $g)
                                        @php $cards = $g['entries']->where('on', $onList); @endphp
                                        @continue($cards->isEmpty())
                                        <h4 style="margin: 4px 0 0; font-size: 12px; font-weight: 700; color: var(--sta-fg-muted, #475569)">{{ $g['title'] }}</h4>
                                        @foreach ($cards as $e)
                                            @php
                                                $p = $e['p'];
                                                $movable = ! $readOnlyReason && ($onList || $p->is_active);
                                                $what = $p->fullName().(filled($e['detail']) ? ' ('.$e['detail'].')' : '');
                                            @endphp
                                            <div wire:key="card-{{ $g['key'] }}-{{ $e['slot'] }}-{{ $e['id'] }}" data-card
                                                @if ($movable) draggable="true" x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $g['kind'] }}|{{ $e['slot'] }}|{{ $e['id'] }}')" @endif
                                                style="display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 10px; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0){{ $movable ? '; cursor: grab' : '' }}">
                                                <span aria-hidden="true" class="sta-avatar">{{ \App\Support\Sta::initials($p->fullName()) }}</span>
                                                <span style="display: flex; flex-direction: column; min-width: 0; flex: 1">
                                                    <x-sta.person-link :person="$p" style="font-weight: 600; color: var(--sta-fg-text, #0F172A)" />
                                                    <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ $p->code }}@if ($p->gender) · {{ $p->gender->label() }}@endif</span>
                                                    @if (filled($e['detail']))<span style="font-size: 12px; font-weight: 600; color: var(--sta-fg-brand, #1F3A5F)">{{ $e['detail'] }}</span>@endif
                                                </span>
                                                @unless ($p->is_active)<span style="font-size: 12px; font-weight: 700; color: var(--sta-fg-red-700, #B91C1C)">Inactive</span>@endunless
                                                @if ($movable)
                                                    <button type="button" wire:click="{{ $method }}('{{ $g['kind'] }}', {{ $e['slot'] }}, {{ $e['id'] }})" aria-label="{{ $onList ? 'Remove '.$what.' from' : 'Add '.$what.' to' }} the Short List"
                                                        class="sta-btn {{ $onList ? '' : 'sta-btn-outline' }}" style="padding: 0 10px; font-size: 13px; white-space: nowrap; flex-shrink: 0">{{ $onList ? '← Remove' : 'Add →' }}</button>
                                                @endif
                                            </div>
                                        @endforeach
                                    @endforeach
                                    @if ($count === 0)
                                        <p style="margin: 0; padding: 12px 0; font-size: 13px; color: var(--sta-fg-muted, #475569); text-align: center">{{ $onList ? ($readOnlyReason ? 'Nobody on the Short List.' : 'Drag people here or use Add →.') : 'Everyone is on the Short List.' }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @foreach ($errorKeys as $key)
                            @error($key)<p role="alert" style="margin: 0; padding: 0 20px 12px; font-size: 13px; color: var(--sta-fg-red-700, #B91C1C); font-weight: 600">{{ $message }}</p>@enderror
                        @endforeach
                    </section>
                @endforeach
            </div>

            <aside aria-label="Short List maximums" style="flex: 1 1 320px; min-width: 0; display: flex; flex-direction: column; gap: 16px">
                @foreach ($sports as $s)
                    @php $gs = $s['gs']; @endphp
                    <section aria-labelledby="count-title-{{ $gs->id }}" aria-live="polite" wire:key="meters-{{ $gs->id }}" style="background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px; padding: 18px 20px; display: flex; flex-direction: column; gap: 14px">
                        <div style="display: flex; flex-direction: column; gap: 2px">
                            <h2 id="count-title-{{ $gs->id }}" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 17px; font-weight: 600">{{ $gs->sport->name }} · Short List vs maximum</h2>
                            <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">Updates as you move people</span>
                        </div>
                        @foreach ([['Male', $s['ticked']['male'], $gs->p4_max_men], ['Female', $s['ticked']['female'], $gs->p4_max_women], ['Team officials', $s['ticked']['officials'], $gs->p4_max_officials]] as [$label, $v, $max])
                            @php
                                $bad = $isOver($v, $max);
                                $pct = $max === null ? 0 : ($max > 0 ? min(100, (int) round($v / $max * 100)) : ($v > 0 ? 100 : 0));
                            @endphp
                            <div style="display: flex; flex-direction: column; gap: 6px">
                                <div style="display: flex; justify-content: space-between; align-items: baseline; font-size: 14px">
                                    <span style="font-weight: 600">{{ $label }}</span>
                                    <span style="font-family: 'Space Grotesk', sans-serif; font-weight: 700; color: {{ $bad ? 'var(--sta-fg-orange-800, #9A3412)' : 'var(--sta-fg-text, #0F172A)' }}">{{ $v }} / {{ $max === null ? 'no max' : 'max '.$max }}</span>
                                </div>
                                <div role="progressbar" aria-label="{{ $gs->sport->name }} {{ $label }}" aria-valuemin="0" aria-valuemax="{{ $max ?? 0 }}" aria-valuenow="{{ $v }}" aria-valuetext="{{ $v }} / {{ $max === null ? 'no max' : 'max '.$max }}" style="height: 8px; border-radius: 999px; background: var(--sta-bg-line, #E2E8F0); overflow: hidden">
                                    <div style="height: 8px; border-radius: 999px; background: {{ $bad ? 'var(--sta-bg-orange-700, #C2410C)' : 'var(--sta-bg-violet-700, #6D28D9)' }}; width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </section>
                @endforeach

                {{-- Over a maximum: the canvas violet "Justification needed" card. Within: the canvas green status only, plus a plain
                     "Justification" card when one is saved (so it can be cleared) or has an error. One section keeps Livewire's markers balanced. --}}
                @unless ($over)
                    <div role="status" style="padding: 12px 16px; border-radius: 12px; background: var(--sta-bg-green-100, #DCFCE7); color: var(--sta-fg-green-800, #166534); font-weight: 600">Within all maximums — no justification needed.</div>
                @endunless
                @if ($over || filled($this->submission->justification) || $errors->has('justification'))
                <section aria-labelledby="just-title" style="border-radius: 14px; padding: 16px 18px; display: flex; flex-direction: column; gap: 10px; {{ $over ? 'background: var(--sta-bg-violet-50, #F5F3FF); border: 2px solid var(--sta-bd-violet-700, #6D28D9)' : 'background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0)' }}">
                    <div style="display: flex; flex-direction: column; gap: 4px">
                        <h2 id="just-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 17px; font-weight: 600">{{ $over ? 'Justification needed' : 'Justification' }}</h2>
                        @if ($over)<span style="font-size: 13px; font-weight: 600; color: var(--sta-fg-violet-800, #5B21B6)">Over the maximum: {{ $overText }}</span>@endif
                    </div>
                    <label for="justification" style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600">{{ $over ? 'Why do you need more than the maximum? (required to submit)' : 'Why do you need more than the maximum?' }}
                        <textarea id="justification" wire:model="justification" rows="6" maxlength="2000" @disabled($readOnlyReason) style="padding: 10px 12px; border: 1px solid {{ $over ? 'var(--sta-bd-violet-400, #A78BFA)' : 'var(--sta-bd-line-strong, #CBD5E1)' }}; border-radius: 10px; font: 14px/1.5 Inter, sans-serif; resize: vertical; background: var(--sta-bg-surface, #FFFFFF)"></textarea>
                    </label>
                    @error('justification')<p role="alert" style="margin: 0; font-size: 13px; color: var(--sta-fg-red-700, #B91C1C); font-weight: 600">{{ $message }}</p>@enderror
                    <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">STA sees this on the Short List PDF and decides.</span>
                </section>
                @endif
            </aside>
        </div>
    @endif

    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
