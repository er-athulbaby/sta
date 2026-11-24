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
            // The pill uses ShortListChecks' over list (same counts as submit and the PDF), matched by game sport id.
            $sportOver = fn ($s) => collect($over)->contains('game_sport_id', $s['gs']->id);
            $headRow = 'text-align: left; font-size: 12px; color: var(--sta-fg-muted, #475569); text-transform: uppercase; letter-spacing: 0.05em';
            $subhead = 'margin: 0; font-size: 13px; font-weight: 700; color: var(--sta-fg-muted, #475569); text-transform: uppercase; letter-spacing: 0.05em';
            $view = 'display: inline-flex; align-items: center; min-height: 44px; font-weight: 600';
            $pill = 'display: inline-flex; align-items: center; height: 28px; padding: 0 12px; border-radius: 999px; font-size: 12px; font-weight: 600; ';
        @endphp

        <div role="note" style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 12px; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); color: var(--sta-fg-strong, #334155)">
            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" style="stroke: var(--sta-fg-violet-800, #5B21B6)"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v5h1"/></svg>
            Everyone on your Long List is shown per sport. Tick the people for the Short List — the counts update as you tick. Going over a maximum is allowed only with a justification.
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start">
            {{-- List / Grid: the same browser-only choice as the Long List (partials.view-state). Athletes and team officials share one design. --}}
            <div style="flex: 999 1 560px; min-width: 0; display: flex; flex-direction: column; gap: 20px" x-data="{ @include('filament.portal.partials.view-state') }">
                <div style="display: flex; justify-content: flex-end">@include('filament.portal.partials.view-toggle')</div>
                @foreach ($sports as $s)
                    @php
                        $gs = $s['gs']; $t = $s['ticked'];
                        $tickedOfficials = array_map('intval', (array) ($officials[$gs->id] ?? []));
                        // One entry per checkbox: athletes per event (a person can be in several events), team officials per sport.
                        $groups = [
                            ['key' => 'a', 'title' => 'Athletes', 'errors' => $s['events']->map(fn ($ge) => 'athletes.'.$ge->id)->all(),
                                'entries' => $s['events']->flatMap(function ($ge) use ($athletes, $slotAthletes, $ids, $people) {
                                    $ticked = array_map('intval', (array) ($athletes[$ge->id] ?? []));

                                    return collect($ids($slotAthletes[$ge->id]))->map(fn ($id) => ['p' => $people[$id], 'id' => $id, 'slot' => $ge->id,
                                        'on' => in_array($id, $ticked, true), 'model' => 'athletes.'.$ge->id, 'detail' => \App\Support\Sta::eventLabel($ge->event)]);
                                })],
                            ['key' => 'o', 'title' => 'Team officials', 'errors' => ['officials.'.$gs->id],
                                'entries' => collect($s['officials'])->map(fn ($id) => ['p' => $people[$id], 'id' => $id, 'slot' => $gs->id,
                                    'on' => in_array($id, $tickedOfficials, true), 'model' => 'officials.'.$gs->id, 'detail' => $people[$id]->function])],
                        ];
                    @endphp
                    <section aria-labelledby="sport-title-{{ $gs->id }}" wire:key="sport-{{ $gs->id }}" style="background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px">
                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
                            <div style="display: flex; flex-direction: column; gap: 2px">
                                <h2 id="sport-title-{{ $gs->id }}" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 600">{{ $gs->sport->name }}</h2>
                                <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">Long List: {{ $s['athletes'] }} {{ Str::plural('athlete', $s['athletes']) }}, {{ count($s['officials']) }} {{ Str::plural('team official', count($s['officials'])) }} · <strong style="color: var(--sta-fg-text, #0F172A)">Ticked: {{ $t['male'] }} male, {{ $t['female'] }} female, {{ $t['officials'] }} team officials</strong></span>
                            </div>
                            <span style="{{ $pill }}{{ $sportOver($s) ? 'background: var(--sta-bg-orange-100, #FFEDD5); color: var(--sta-fg-orange-800, #9A3412)' : 'background: var(--sta-bg-green-100, #DCFCE7); color: var(--sta-fg-green-800, #166534)' }}">{{ $sportOver($s) ? 'Over a maximum' : 'Within maximums' }}</span>
                        </div>

                        @foreach ($groups as $g)
                            @continue($g['entries']->isEmpty())
                            @php $isA = $g['key'] === 'a'; @endphp
                            <h3 style="{{ $subhead }}; padding: {{ $isA ? '14px' : '18px' }} 20px 6px{{ $isA ? '' : '; border-top: 1px solid var(--sta-bd-line, #E2E8F0)' }}">{{ $g['title'] }}</h3>
                            <div x-show="view === 'list'" data-view="list" style="overflow-x: auto">
                                <table style="width: 100%; min-width: 620px; border-collapse: collapse">
                                    <thead>
                                        <tr style="{{ $headRow }}">
                                            <th scope="col" style="padding: 8px 20px; font-weight: 600">On Short List</th>
                                            @if ($isA)
                                                <th scope="col" style="padding: 8px 12px; font-weight: 600">Gender</th>
                                                <th scope="col" style="padding: 8px 12px; font-weight: 600">Event</th>
                                            @else
                                                <th scope="col" style="padding: 8px 12px; font-weight: 600">Function</th>
                                                <th scope="col" style="padding: 8px 12px; font-weight: 600">Mobile</th>
                                            @endif
                                            <th scope="col" style="padding: 8px 20px; font-weight: 600">Passport</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($g['entries'] as $e)
                                            @php $p = $e['p']; @endphp
                                            <tr wire:key="{{ $g['key'] }}-{{ $e['slot'] }}-{{ $e['id'] }}" style="border-top: 1px solid var(--sta-bd-row, #EEF2F6); background: {{ $e['on'] ? 'var(--sta-bg-surface, #FFFFFF)' : 'var(--sta-bg-surface-alt, #F8FAFC)' }}">
                                                <td style="padding: 4px 20px">
                                                    <label style="display: flex; align-items: center; gap: 12px; min-height: 52px; cursor: pointer">
                                                        <input type="checkbox" value="{{ $e['id'] }}" wire:model.live="{{ $e['model'] }}" @disabled($readOnlyReason) style="width: 22px; height: 22px; accent-color: var(--sta-bd-violet-700, #6D28D9); flex-shrink: 0">
                                                        <span aria-hidden="true" class="sta-avatar">{{ \App\Support\Sta::initials($p->fullName()) }}</span>
                                                        <span style="display: flex; flex-direction: column"><x-sta.person-link :person="$p" style="font-weight: 600; color: {{ $e['on'] ? 'var(--sta-fg-text, #0F172A)' : 'var(--sta-fg-muted, #475569)' }}" /><span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ $p->code }}</span></span>
                                                    </label>
                                                </td>
                                                @if ($isA)
                                                    <td style="padding: 4px 12px">{{ $p->gender?->label() }}</td>
                                                    <td style="padding: 4px 12px; font-weight: 600; color: var(--sta-fg-brand, #1F3A5F)">{{ $e['detail'] }}</td>
                                                @else
                                                    <td style="padding: 4px 12px; font-weight: 600; color: var(--sta-fg-brand, #1F3A5F)">{{ $e['detail'] }}</td>
                                                    <td style="padding: 4px 12px; color: var(--sta-fg-strong, #334155); white-space: nowrap">{{ filled($p->phone) ? trim($p->phone_cc.' '.$p->phone) : '' }}</td>
                                                @endif
                                                <td style="padding: 4px 20px">
                                                    @if ($p->passport_scan_path)
                                                        <x-sta.passport-links :person="$p" :link-style="$view" />
                                                    @else
                                                        <span style="color: var(--sta-fg-orange-800, #9A3412); font-weight: 600">Missing</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            {{-- Grid: the same checkboxes as cards with the photo; rendered only in Grid view so List view fetches no photos. --}}
                            <template x-if="view === 'grid'">
                                <div data-view="grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(260px, 100%), 1fr)); gap: 12px; padding: 4px 20px 16px">
                                    @foreach ($g['entries'] as $e)
                                        @php $p = $e['p']; @endphp
                                        <label wire:key="card-{{ $g['key'] }}-{{ $e['slot'] }}-{{ $e['id'] }}" style="display: flex; flex-direction: column; gap: 10px; padding: 14px; border-radius: 12px; cursor: pointer; border: {{ $e['on'] ? '2px solid var(--sta-bd-violet-700, #6D28D9)' : '1px solid var(--sta-bd-line, #E2E8F0)' }}; background: {{ $e['on'] ? 'var(--sta-bg-surface, #FFFFFF)' : 'var(--sta-bg-surface-alt, #F8FAFC)' }}">
                                            <span style="display: flex; gap: 12px; align-items: center">
                                                <input type="checkbox" value="{{ $e['id'] }}" wire:model.live="{{ $e['model'] }}" @disabled($readOnlyReason) aria-label="{{ $p->fullName() }} on the Short List" style="width: 22px; height: 22px; accent-color: var(--sta-bd-violet-700, #6D28D9); flex-shrink: 0">
                                                @if ($p->photo_path)
                                                    <img loading="lazy" src="{{ \App\Filament\Shared\AttachmentActions::url($p->photo_path) }}" alt="Photo of {{ $p->fullName() }}" class="sta-avatar sta-avatar-lg" style="object-fit: cover">
                                                @else
                                                    <span role="img" aria-label="Photo of {{ $p->fullName() }}" class="sta-avatar sta-avatar-lg">{{ \App\Support\Sta::initials($p->fullName()) }}</span>
                                                @endif
                                                <span style="display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1">
                                                    <x-sta.person-link :person="$p" style="font-weight: 600; font-size: 15px; color: {{ $e['on'] ? 'var(--sta-fg-text, #0F172A)' : 'var(--sta-fg-muted, #475569)' }}" />
                                                    <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">{{ $p->code }}@if (! $isA && filled($p->phone)) · {{ trim($p->phone_cc.' '.$p->phone) }}@endif</span>
                                                </span>
                                            </span>
                                            <span style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center">
                                                <span style="display: inline-flex; align-items: center; height: 28px; padding: 0 8px; border-radius: 8px; background: var(--sta-bg-subtle, #F1F5F9); color: var(--sta-fg-strong, #334155); font-size: 12px; font-weight: 700">{{ $p->gender?->label() }}</span>
                                                @if (filled($e['detail']))<span style="display: inline-flex; align-items: center; height: 28px; padding: 0 10px; border-radius: 8px; background: var(--sta-bg-selected, #E8EEF6); color: var(--sta-fg-brand, #1F3A5F); font-size: 13px; font-weight: 600">{{ $e['detail'] }}</span>@endif
                                            </span>
                                            <span style="font-size: 13px">
                                                @if ($p->passport_scan_path)<x-sta.passport-links :person="$p" label="Passport" />@else<span style="color: var(--sta-fg-orange-800, #9A3412); font-weight: 600">Passport missing</span>@endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </template>
                            @foreach ($g['errors'] as $key)
                                @error($key)<p role="alert" style="margin: 0; padding: 4px 20px 10px; font-size: 13px; color: var(--sta-fg-red-700, #B91C1C); font-weight: 600">{{ $message }}</p>@enderror
                            @endforeach
                        @endforeach
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
