<x-filament-panels::page>
    {{-- Canvas: LongList.dc.html (header band via getHeader(), Phase 2 comparison, tabs, List / Grid, Team officials, side panel)
         and LongListGrid.dc.html (Grid view). Assigning athletes to events is phase3-events (LongListEvents.dc.html). --}}
    @include('filament.portal.partials.phase-top', ['pdfKey' => 'long_list', 'pdfLabel' => 'View Long List PDF'])

    @php
        $p2 = $this->phase2Numbers();
        $people = $this->chipPeople();
        $sports = $this->visibleSports();
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

    @if ($p2 !== null)
        @php
            $compare = $sports->flatMap->gameEvents->map(fn ($ge) => ['name' => \App\Support\Sta::eventLabel($ge->event), 'n' => count($shown($athletes[$ge->id] ?? [])), 'p2' => $p2[$ge->id] ?? 0]);
            $differ = $compare->filter(fn ($c) => $c['n'] > $c['p2'])->count();
        @endphp
        <section aria-labelledby="compare-title" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; padding: 16px 20px; display: flex; flex-direction: column; gap: 12px">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px">
                <h2 id="compare-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 17px; font-weight: 600">Compared with your Phase 2 numbers</h2>
                @if ($differ)
                    <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #9A3412; font-weight: 600">
                        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3 2 21h20z"/><path d="M12 10v4"/><path d="M12 17h.01"/></svg>
                        {{ $differ }} {{ $differ === 1 ? 'event differs' : 'events differ' }} · allowed, STA will see it
                    </span>
                @endif
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 8px">
                @foreach ($compare as $c)
                    <span style="display: inline-flex; align-items: center; height: 32px; padding: 0 12px; border-radius: 8px; font-size: 13px; {{ $c['n'] > $c['p2'] ? 'background: #FFEDD5; color: #9A3412; font-weight: 600' : 'background: #F1F5F9; color: #334155; font-weight: 500' }}">{{ $c['name'] }} · {{ $c['n'] }} of {{ $c['p2'] }}</span>
                @endforeach
            </div>
        </section>
    @endif

    @php
        $rows = $this->listRows();
        $isAthlete = fn ($r) => $r['person']->type === \App\Enums\PersonType::Athlete;
        $athleteRows = $rows->filter($isAthlete)->values();
        $officialRows = $rows->reject($isAthlete)->values();
        $flagged = fn ($r) => $r['missing'] || $r['warnings'];
        $male = $athleteRows->filter(fn ($r) => $r['person']->gender === $Male)->count();
        $female = $athleteRows->filter(fn ($r) => $r['person']->gender === $Female)->count();
        $items = $this->gameModel->clothingItems;
        $athleteItems = $items->filter(fn ($i) => $i->applies_to->includes(\App\Enums\PersonType::Athlete))->values();
        $officialItems = $items->filter(fn ($i) => $i->applies_to->includes(\App\Enums\PersonType::Official))->values();
        // Events of each athlete with their sport, for the EVENT links to the "Assign athletes to events" screen (the sport is named when there are several).
        $oneSport = $sports->count() === 1;
        $eventLinks = [];
        foreach ($sports as $gs) {
            foreach ($gs->gameEvents as $ge) {
                foreach ($shown($athletes[$ge->id] ?? []) as $id) {
                    $eventLinks[$id][] = ['label' => ($oneSport ? '' : $gs->sport->name.' · ').\App\Support\Sta::eventLabel($ge->event), 'url' => $this->eventsUrl($gs->id)];
                }
            }
        }
        $eventsOf = fn ($r) => $eventLinks[$r['person']->id] ?? [];
        $athFlagged = $athleteRows->filter($flagged)->count();
        $offFlagged = $officialRows->filter($flagged)->count();
        $sizeOf = fn ($r, $item) => $r['person']->sizes->firstWhere('clothing_item_id', $item->id)?->size;
        $startDay = $this->gameModel->start_date?->format('j M');
        $showTravel = ! $readOnlyReason;
        $url = fn ($path) => \App\Filament\Shared\AttachmentActions::url($path);
        $warnText = fn ($r) => implode(' ', $r['warnings']);
        $incomplete = '<span style="display: inline-flex; align-items: center; height: 24px; padding: 0 8px; border-radius: 999px; background: #FFEDD5; color: #9A3412; font-weight: 700; font-size: 12px">Incomplete</span>';
    @endphp
    {{-- Tabs and the List / Grid choice are browser-only; the view is remembered per user (localStorage), never sent to the server. --}}
    <div style="display: flex; flex-direction: column; gap: 20px"
        x-data="{ tab: 'athletes', view: (() => { try { return localStorage.getItem('longListView.{{ auth()->id() }}') || 'list' } catch (e) { return 'list' } })() }"
        x-init="$watch('view', v => { try { localStorage.setItem('longListView.{{ auth()->id() }}', v) } catch (e) {} })">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; border-bottom: 1px solid #E2E8F0">
            <div role="tablist" aria-label="List sections" style="display: flex; flex-wrap: wrap; gap: 4px">
                @foreach (['athletes' => 'Athletes ('.$athleteRows->count().')', 'officials' => 'Team officials ('.$officialRows->count().')', 'warnings' => 'Warnings ('.$rows->filter($flagged)->count().')'] as $key => $label)
                    <button type="button" role="tab" id="ll-tab-{{ $key }}" x-on:click="tab = '{{ $key }}'" x-bind:aria-selected="tab === '{{ $key }}'"
                        x-bind:style="tab === '{{ $key }}' ? { borderBottomColor: '#1F3A5F', color: '#0F172A' } : { borderBottomColor: 'transparent', color: '#475569' }"
                        style="min-height: 44px; padding: 0 16px; border: 0; border-bottom: 2px solid transparent; background: transparent; color: #475569; font: 600 14px Inter, sans-serif; cursor: pointer">{{ $label }}</button>
                @endforeach
            </div>
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding-bottom: 6px">
                <span style="font-size: 13px; color: #334155"><strong>{{ $male }}</strong> male · <strong>{{ $female }}</strong> female · <strong>{{ $officialRows->count() }}</strong> team officials</span>
                @if ($canAdd)
                    <button type="button" wire:click="openPanel('athlete')" style="{{ $outline }}">{!! $plus !!}Add athlete</button>
                    <button type="button" wire:click="openPanel('official')" style="{{ $outline }}">{!! $plus !!}Add team official</button>
                @endif
                <div class="sta-seg" role="group" aria-label="View">
                    <button type="button" x-on:click="view = 'list'" x-bind:aria-pressed="view === 'list'" style="padding: 0 12px"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>List</button>
                    <button type="button" x-on:click="view = 'grid'" x-bind:aria-pressed="view === 'grid'" style="padding: 0 12px"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Grid</button>
                </div>
            </div>
        </div>

        {{-- x-show on wrappers only: Alpine drops an inline display value when it shows an element again. --}}
        @if ($athFlagged + $offFlagged === 0)
            <p x-show="tab === 'warnings'" style="margin: 0; color: #475569">No warnings.</p>
        @endif
        <div x-show="tab === 'athletes' || (tab === 'warnings' && {{ $athFlagged ? 'true' : 'false' }})"><div style="display: flex; flex-direction: column; gap: 20px">
            <section x-show="view === 'list'" aria-label="Athletes" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; overflow-x: auto">
                @if ($athleteRows->isEmpty())
                    <p style="margin: 0; padding: 16px 20px; color: #475569">No athletes on the list yet.</p>
                @else
                    <table style="width: 100%; min-width: 980px; border-collapse: collapse">
                        <thead>
                            <tr style="{{ $headRow }}">
                                <th scope="col" style="{{ $thEdge }}">Athlete</th>
                                <th scope="col" style="{{ $th }}">Gender</th>
                                <th scope="col" style="{{ $th }}">Born · age</th>
                                <th scope="col" style="{{ $th }}">Event</th>
                                @foreach ($athleteItems as $item)<th scope="col" style="{{ $th }}">{{ $item->name }}</th>@endforeach
                                <th scope="col" style="{{ $th }}">Scans</th>
                                <th scope="col" style="{{ $thEdge }}">Warning</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($athleteRows as $row)
                                @php $p = $row['person']; $d = $row['detail']; @endphp
                                <tr wire:key="person-{{ $p->id }}" @unless ($flagged($row)) x-show="tab !== 'warnings'" @endunless style="border-top: 1px solid #EEF2F6; vertical-align: middle">
                                    <td style="padding: 10px 20px">
                                        <div style="display: flex; align-items: center; gap: 10px">
                                            <div role="img" aria-label="Photo of {{ $p->fullName() }}" class="sta-avatar">{{ \App\Support\Sta::initials($p->fullName()) }}</div>
                                            <div style="display: flex; flex-direction: column">
                                                <span style="font-weight: 600">{{ $p->fullName() }}</span>
                                                <span style="font-size: 12px; color: #475569">{{ $p->code }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 10px 12px">{{ $p->gender?->label() }}</td>
                                    <td style="padding: 10px 12px; color: #334155">{{ $p->dob?->format('d M Y') }} · {{ $row['age'] }}</td>
                                    <td style="padding: 10px 12px; font-weight: 600; color: #1F3A5F">@foreach ($eventsOf($row) as $e)<a href="{{ $e['url'] }}" style="color: #1F3A5F; text-decoration: none">{{ $e['label'] }}</a>@if (! $loop->last), @endif @endforeach</td>
                                    @foreach ($athleteItems as $item)
                                        @php $size = $sizeOf($row, $item); @endphp
                                        <td style="padding: 10px 12px; font-weight: 700; color: {{ filled($size) ? '#0F172A' : '#9A3412' }}">{{ filled($size) ? $size : 'Missing' }}</td>
                                    @endforeach
                                    <td style="padding: 10px 12px">
                                        <div style="display: flex; flex-direction: column; font-size: 13px">
                                            @if ($p->passport_scan_path)<span>Passport · <a href="{{ $url($p->passport_scan_path) }}" target="_blank" aria-label="View passport scan of {{ $p->fullName() }}" style="font-weight: 600">View</a></span>@else<span style="{{ $missingStyle }}">Passport missing</span>@endif
                                            @if ($p->cpr_front_path)<span>CPR · <a href="{{ $url($p->cpr_front_path) }}" target="_blank" aria-label="View CPR scan of {{ $p->fullName() }}" style="font-weight: 600">View</a></span>@else<span style="{{ $missingStyle }}">CPR missing</span>@endif
                                            @include('filament.portal.partials.travel-cell')
                                        </div>
                                    </td>
                                    <td style="padding: 10px 20px; font-size: 13px; color: #9A3412; max-width: 240px; line-height: 1.4">
                                        @if ($row['missing'])<div>{!! $incomplete !!} Missing: {{ implode(', ', $row['missing']) }}</div>@endif
                                        {{ $warnText($row) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            {{-- Rendered only in Grid view so List view does not fetch every photo on each Livewire render. --}}
            <template x-if="view === 'grid'">
                <section aria-label="Athletes" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr)); gap: 16px">
                    @forelse ($athleteRows as $row)
                        @php $p = $row['person']; $d = $row['detail']; @endphp
                        <div wire:key="card-{{ $p->id }}" @unless ($flagged($row)) x-show="tab !== 'warnings'" @endunless><article style="height: 100%; box-sizing: border-box; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; padding: 18px; display: flex; flex-direction: column; gap: 14px">
                            <div style="display: flex; gap: 14px; align-items: center">
                                @if ($p->photo_path)
                                    <img loading="lazy" src="{{ $url($p->photo_path) }}" alt="Photo of {{ $p->fullName() }}" class="sta-avatar sta-avatar-lg" style="object-fit: cover">
                                @else
                                    <div role="img" aria-label="Photo of {{ $p->fullName() }}" class="sta-avatar sta-avatar-lg">{{ \App\Support\Sta::initials($p->fullName()) }}</div>
                                @endif
                                <div style="display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1">
                                    <span style="font-weight: 600; font-size: 15px">{{ $p->fullName() }}</span>
                                    <span style="font-size: 13px; color: #475569">{{ $p->code }} · {{ $p->dob?->format('d M Y') }} · {{ $row['age'] }}@if ($startDay) on {{ $startDay }}@endif</span>
                                </div>
                                <span style="display: inline-flex; align-items: center; height: 28px; padding: 0 8px; border-radius: 8px; background: #F1F5F9; color: #334155; font-size: 12px; font-weight: 700">{{ $p->gender?->label() }}</span>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 6px">
                                <span style="font-size: 12px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.05em">Event</span>
                                <div style="display: flex; flex-wrap: wrap; gap: 6px">
                                    @foreach ($eventsOf($row) as $e)
                                        <a href="{{ $e['url'] }}" style="display: inline-flex; align-items: center; height: 30px; padding: 0 12px; border-radius: 8px; background: #E8EEF6; color: #1F3A5F; font-weight: 600; font-size: 13px; text-decoration: none">{{ $e['label'] }}</a>
                                    @endforeach
                                </div>
                            </div>
                            @if ($athleteItems->isNotEmpty())
                                <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px">
                                    @foreach ($athleteItems as $item)
                                        @php $size = $sizeOf($row, $item); @endphp
                                        <div style="display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; border-radius: 10px; {{ filled($size) ? 'background: #F8FAFC; box-shadow: inset 0 0 0 1px #E2E8F0' : 'background: #FFF7ED; box-shadow: inset 0 0 0 1px #FED7AA' }}">
                                            <span style="font-size: 11px; color: #475569">{{ $item->name }}</span>
                                            <span style="font-weight: 700; font-size: 14px; color: {{ filled($size) ? '#0F172A' : '#9A3412' }}">{{ filled($size) ? $size : 'Missing' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; font-size: 13px">
                                @if ($p->passport_scan_path)<span>Passport scan · <a href="{{ $url($p->passport_scan_path) }}" target="_blank" aria-label="View passport scan of {{ $p->fullName() }}" style="font-weight: 600">View</a></span>@else<span style="{{ $missingStyle }}">Passport scan missing</span>@endif
                                @if ($p->cpr_front_path)<span>CPR scan · <a href="{{ $url($p->cpr_front_path) }}" target="_blank" aria-label="View CPR scan of {{ $p->fullName() }}" style="font-weight: 600">View</a></span>@else<span style="{{ $missingStyle }}">CPR scan missing</span>@endif
                                @include('filament.portal.partials.travel-cell')
                            </div>
                            @if ($flagged($row))
                                <div style="display: flex; gap: 8px; align-items: flex-start; padding: 10px 12px; border-radius: 10px; background: #FFF7ED; color: #9A3412; font-size: 13px; line-height: 1.45">
                                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="flex-shrink: 0; margin-top: 2px"><path d="M12 3 2 21h20z"/><path d="M12 10v4"/><path d="M12 17h.01"/></svg>
                                    <span>@if ($row['missing'])Incomplete — missing: {{ implode(', ', $row['missing']) }}. @endif{{ $warnText($row) }}</span>
                                </div>
                            @endif
                        </article></div>
                    @empty
                        <p style="margin: 0; color: #475569">No athletes on the list yet.</p>
                    @endforelse
                </section>
            </template>
        </div></div>

        <div x-show="tab !== 'warnings' || {{ $offFlagged ? 'true' : 'false' }}"><section aria-labelledby="officials-title" style="display: flex; flex-direction: column; gap: 12px">
            <h2 id="officials-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 19px; font-weight: 600">Team officials</h2>
            <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; overflow-x: auto">
                @if ($officialRows->isEmpty())
                    <p style="margin: 0; padding: 16px 20px; color: #475569">No team officials on the list yet.</p>
                @else
                    <table style="width: 100%; min-width: 720px; border-collapse: collapse">
                        <thead>
                            <tr style="{{ $headRow }}">
                                <th scope="col" style="{{ $thEdge }}">Name</th>
                                <th scope="col" style="{{ $th }}">Function</th>
                                <th scope="col" style="{{ $th }}">Mobile</th>
                                <th scope="col" style="{{ $th }}">Email</th>
                                @foreach ($officialItems as $item)<th scope="col" style="{{ $th }}">{{ $item->name }}</th>@endforeach
                                <th scope="col" style="{{ $thEdge }}">Passport</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($officialRows as $row)
                                @php $p = $row['person']; $d = $row['detail']; @endphp
                                <tr wire:key="person-{{ $p->id }}" @unless ($flagged($row)) x-show="tab !== 'warnings'" @endunless style="border-top: 1px solid #EEF2F6">
                                    <td style="padding: 14px 20px">
                                        <span style="font-weight: 600">{{ $p->fullName() }}</span>
                                        @if ($flagged($row))
                                            <div style="font-size: 12px; color: #9A3412; max-width: 260px; line-height: 1.4">@if ($row['missing']){!! $incomplete !!} Missing: {{ implode(', ', $row['missing']) }}. @endif{{ $warnText($row) }}</div>
                                        @endif
                                    </td>
                                    <td style="padding: 14px 12px">{{ $p->function }}</td>
                                    <td style="padding: 14px 12px; color: #334155">{{ filled($p->phone) ? trim($p->phone_cc.' '.$p->phone) : '' }}</td>
                                    <td style="padding: 14px 12px; color: #334155">{{ $p->email }}</td>
                                    @foreach ($officialItems as $item)
                                        @php $size = $sizeOf($row, $item); @endphp
                                        <td style="padding: 14px 12px; font-weight: 600; color: {{ filled($size) ? '#0F172A' : '#9A3412' }}">{{ filled($size) ? $size : 'Missing' }}</td>
                                    @endforeach
                                    <td style="padding: 14px 20px">
                                        <div style="display: flex; flex-direction: column; font-size: 13px">
                                            @if ($p->passport_scan_path)<a href="{{ $url($p->passport_scan_path) }}" target="_blank" aria-label="View passport scan of {{ $p->fullName() }}" style="font-weight: 600">View</a>@else<span style="{{ $missingStyle }}">Missing</span>@endif
                                            @include('filament.portal.partials.travel-cell')
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section></div>
    </div>

    @include('filament.portal.partials.person-panel')
    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
