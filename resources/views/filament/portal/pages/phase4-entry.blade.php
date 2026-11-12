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
            $sportOver = fn ($s) => $isOver($s['ticked']['male'], $s['gs']->p4_max_men) || $isOver($s['ticked']['female'], $s['gs']->p4_max_women)
                || $isOver($s['ticked']['officials'], $s['gs']->p4_max_officials);
            $headRow = 'text-align: left; font-size: 12px; color: var(--sta-fg-muted, #475569); text-transform: uppercase; letter-spacing: 0.05em';
            $subhead = 'margin: 0; font-size: 13px; font-weight: 700; color: var(--sta-fg-muted, #475569); text-transform: uppercase; letter-spacing: 0.05em';
            $view = 'display: inline-flex; align-items: center; min-height: 44px; font-weight: 600';
            $pill = 'display: inline-flex; align-items: center; height: 28px; padding: 0 12px; border-radius: 999px; font-size: 12px; font-weight: 600; ';
            $url = fn ($path) => \App\Filament\Shared\AttachmentActions::url($path);
        @endphp

        <div role="note" style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 12px; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); color: var(--sta-fg-strong, #334155)">
            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" style="stroke: var(--sta-fg-violet-800, #5B21B6)"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v5h1"/></svg>
            Everyone on your Long List is shown per sport. Tick the people for the Short List — the counts update as you tick. Going over a maximum is allowed only with a justification.
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start">
            <div style="flex: 999 1 560px; min-width: 0; display: flex; flex-direction: column; gap: 20px">
                @foreach ($sports as $s)
                    @php $gs = $s['gs']; $t = $s['ticked']; @endphp
                    <section aria-labelledby="sport-title-{{ $gs->id }}" wire:key="sport-{{ $gs->id }}" style="background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px">
                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
                            <div style="display: flex; flex-direction: column; gap: 2px">
                                <h2 id="sport-title-{{ $gs->id }}" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 600">{{ $gs->sport->name }}</h2>
                                <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">Long List: {{ $s['athletes'] }} {{ Str::plural('athlete', $s['athletes']) }}, {{ count($s['officials']) }} {{ Str::plural('team official', count($s['officials'])) }} · <strong style="color: var(--sta-fg-text, #0F172A)">Ticked: {{ $t['male'] }} male, {{ $t['female'] }} female, {{ $t['officials'] }} team officials</strong></span>
                            </div>
                            <span style="{{ $pill }}{{ $sportOver($s) ? 'background: var(--sta-bg-orange-100, #FFEDD5); color: var(--sta-fg-orange-800, #9A3412)' : 'background: var(--sta-bg-green-100, #DCFCE7); color: var(--sta-fg-green-800, #166534)' }}">{{ $sportOver($s) ? 'Over a maximum' : 'Within maximums' }}</span>
                        </div>

                        @if ($s['events']->isNotEmpty())
                            <h3 style="{{ $subhead }}; padding: 14px 20px 6px">Athletes</h3>
                            <div style="overflow-x: auto">
                                <table style="width: 100%; min-width: 620px; border-collapse: collapse">
                                    <thead>
                                        <tr style="{{ $headRow }}">
                                            <th scope="col" style="padding: 8px 20px; font-weight: 600">On Short List</th>
                                            <th scope="col" style="padding: 8px 12px; font-weight: 600">Gender</th>
                                            <th scope="col" style="padding: 8px 12px; font-weight: 600">Event</th>
                                            <th scope="col" style="padding: 8px 20px; font-weight: 600">Passport</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($s['events'] as $ge)
                                            @php $ticked = array_map('intval', (array) ($athletes[$ge->id] ?? [])); @endphp
                                            @foreach ($ids($slotAthletes[$ge->id]) as $id)
                                                @php $p = $people[$id]; $on = in_array($id, $ticked, true); @endphp
                                                <tr wire:key="a-{{ $ge->id }}-{{ $id }}" style="border-top: 1px solid var(--sta-bd-row, #EEF2F6); background: {{ $on ? 'var(--sta-bg-surface, #FFFFFF)' : 'var(--sta-bg-surface-alt, #F8FAFC)' }}">
                                                    <td style="padding: 4px 20px">
                                                        <label style="display: flex; align-items: center; gap: 12px; min-height: 52px; cursor: pointer">
                                                            <input type="checkbox" value="{{ $id }}" wire:model.live="athletes.{{ $ge->id }}" @disabled($readOnlyReason) style="width: 22px; height: 22px; accent-color: var(--sta-bd-violet-700, #6D28D9); flex-shrink: 0">
                                                            <span aria-hidden="true" style="width: 34px; height: 34px; border-radius: 50%; background: var(--sta-bg-avatar, #2B4C7E); color: var(--sta-fg-on-brand, #FFFFFF); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; flex-shrink: 0">{{ \App\Support\Sta::initials($p->fullName()) }}</span>
                                                            <span style="display: flex; flex-direction: column"><span style="font-weight: 600; color: {{ $on ? 'var(--sta-fg-text, #0F172A)' : 'var(--sta-fg-muted, #475569)' }}">{{ $p->fullName() }}</span><span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ $p->code }}</span></span>
                                                        </label>
                                                    </td>
                                                    <td style="padding: 4px 12px">{{ $p->gender?->label() }}</td>
                                                    <td style="padding: 4px 12px; font-weight: 600; color: var(--sta-fg-brand, #1F3A5F)">{{ \App\Support\Sta::eventLabel($ge->event) }}</td>
                                                    <td style="padding: 4px 20px">
                                                        @if ($p->passport_scan_path)
                                                            <a href="{{ $url($p->passport_scan_path) }}" target="_blank" aria-label="View passport scan of {{ $p->fullName() }}" style="{{ $view }}">View</a>
                                                        @else
                                                            <span style="color: var(--sta-fg-orange-800, #9A3412); font-weight: 600">Missing</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            @error('athletes.'.$ge->id)<tr><td colspan="4" style="padding: 4px 20px 10px"><p role="alert" style="margin: 0; font-size: 13px; color: var(--sta-fg-red-700, #B91C1C); font-weight: 600">{{ $message }}</p></td></tr>@enderror
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if ($s['officials'] !== [])
                            @php $ticked = array_map('intval', (array) ($officials[$gs->id] ?? [])); @endphp
                            <h3 style="{{ $subhead }}; padding: 18px 20px 6px; border-top: 1px solid var(--sta-bd-line, #E2E8F0)">Team officials</h3>
                            <div style="overflow-x: auto">
                                <table style="width: 100%; min-width: 620px; border-collapse: collapse">
                                    <thead>
                                        <tr style="{{ $headRow }}">
                                            <th scope="col" style="padding: 8px 20px; font-weight: 600">On Short List</th>
                                            <th scope="col" style="padding: 8px 12px; font-weight: 600">Function</th>
                                            <th scope="col" style="padding: 8px 20px; font-weight: 600">Passport</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($s['officials'] as $id)
                                            @php $p = $people[$id]; $on = in_array($id, $ticked, true); @endphp
                                            <tr wire:key="o-{{ $gs->id }}-{{ $id }}" style="border-top: 1px solid var(--sta-bd-row, #EEF2F6); background: {{ $on ? 'var(--sta-bg-surface, #FFFFFF)' : 'var(--sta-bg-surface-alt, #F8FAFC)' }}">
                                                <td style="padding: 4px 20px">
                                                    <label style="display: flex; align-items: center; gap: 12px; min-height: 52px; cursor: pointer">
                                                        <input type="checkbox" value="{{ $id }}" wire:model.live="officials.{{ $gs->id }}" @disabled($readOnlyReason) style="width: 22px; height: 22px; accent-color: var(--sta-bd-violet-700, #6D28D9); flex-shrink: 0">
                                                        <span style="font-weight: 600; color: {{ $on ? 'var(--sta-fg-text, #0F172A)' : 'var(--sta-fg-muted, #475569)' }}">{{ $p->fullName() }}</span>
                                                    </label>
                                                </td>
                                                <td style="padding: 4px 12px">{{ $p->function }}</td>
                                                <td style="padding: 4px 20px">
                                                    @if ($p->passport_scan_path)
                                                        <a href="{{ $url($p->passport_scan_path) }}" target="_blank" aria-label="View passport scan of {{ $p->fullName() }}" style="{{ $view }}">View</a>
                                                    @else
                                                        <span style="color: var(--sta-fg-orange-800, #9A3412); font-weight: 600">Missing</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        @error('officials.'.$gs->id)<tr><td colspan="3" style="padding: 4px 20px 10px"><p role="alert" style="margin: 0; font-size: 13px; color: var(--sta-fg-red-700, #B91C1C); font-weight: 600">{{ $message }}</p></td></tr>@enderror
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>

            <aside aria-label="Short List maximums" style="flex: 1 1 320px; min-width: 0; display: flex; flex-direction: column; gap: 16px">
                @foreach ($sports as $s)
                    @php $gs = $s['gs']; @endphp
                    <section aria-labelledby="count-title-{{ $gs->id }}" aria-live="polite" wire:key="meters-{{ $gs->id }}" style="background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px; padding: 18px 20px; display: flex; flex-direction: column; gap: 14px">
                        <div style="display: flex; flex-direction: column; gap: 2px">
                            <h2 id="count-title-{{ $gs->id }}" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 17px; font-weight: 600">{{ $gs->sport->name }} · ticked vs maximum</h2>
                            <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">Updates as you tick</span>
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
