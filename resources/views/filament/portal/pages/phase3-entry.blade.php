<x-filament-panels::page>
    @include('filament.portal.partials.phase-top', ['pdfKey' => 'long_list', 'pdfLabel' => 'View Long List PDF'])

    @php
        $p2 = $this->phase2Numbers();
        $athleteOptions = $this->candidates(\App\Enums\PersonType::Athlete);
        $officialOptions = $this->candidates(\App\Enums\PersonType::Official);
        $people = $this->chipPeople();
        $sports = $this->visibleSports();
    @endphp

    @if ($p2 !== null)
        @php
            $compare = $sports->flatMap->gameEvents->map(fn ($ge) => ['name' => $ge->event->name, 'n' => count((array) ($athletes[$ge->id] ?? [])), 'p2' => $p2[$ge->id] ?? 0]);
            $differ = $compare->filter(fn ($c) => $c['n'] > $c['p2'])->count();
        @endphp
        <x-sta.card heading="Compared with your Phase 2 numbers">
            <x-slot:actions>
                @if ($differ)<span class="sta-pill sta-pill-warn">{{ $differ }} {{ $differ === 1 ? 'event differs' : 'events differ' }} · allowed, STA will see it</span>@endif
            </x-slot:actions>
            <div class="sta-card-body sta-chips">
                @foreach ($compare as $c)
                    <span @class(['sta-compare', 'is-over' => $c['n'] > $c['p2']])>{{ $c['name'] }} · {{ $c['n'] }} of {{ $c['p2'] }}</span>
                @endforeach
            </div>
        </x-sta.card>
    @endif

    @if ($url = $this->registryUrl())
        <p class="sta-muted"><a href="{{ $url }}" target="_blank">Open registry</a> to edit people; people you add with Add athlete or Add team official appear in these lists straight away.</p>
    @endif

    @forelse ($sports as $gs)
        @php
            $sportAthletes = $gs->gameEvents->flatMap(fn ($ge) => (array) ($athletes[$ge->id] ?? []))->unique()->count();
            $sportOfficials = count((array) ($officials[$gs->id] ?? []));
        @endphp
        <x-sta.card :heading="$gs->sport->name" :description="$gs->gameEvents->count().' events · '.$sportAthletes.' athletes · '.$sportOfficials.' team officials'" wire:key="sport-{{ $gs->id }}">
            <p class="sta-card-body sta-muted" style="padding-bottom: 0">Pick athletes from your registry for each event. Each list offers only active athletes whose gender matches the event (Mixed events accept Male and Female). One athlete can be in several events.</p>
            <div class="sta-card-body sta-event-grid">
                @foreach ($gs->gameEvents as $ge)
                    @php
                        $chosen = array_map('intval', (array) ($athletes[$ge->id] ?? []));
                        $n = count($chosen);
                        $max = $p2 === null ? null : ($p2[$ge->id] ?? 0);
                        $over = $max !== null && $n > $max;
                        $err = $errors->first('athletes.'.$ge->id);
                    @endphp
                    <div class="sta-event-box" wire:key="event-{{ $ge->id }}">
                        <div class="sta-row-between">
                            <label class="sta-event-name" for="event-{{ $ge->id }}">{{ $ge->event->name }} ({{ $ge->event->gender->label() }})</label>
                            <span class="sta-muted">Long List {{ $n }}{{ $max !== null ? " / Phase 2: $max" : '' }}</span>
                            @if ($over)<span class="sta-pill sta-pill-warn">Over the Phase 2 number</span>@endif
                        </div>
                        <div @class(['sta-token-field', 'is-error' => $err])>
                            @foreach ($chosen as $id)
                                @php $p = $people->get($id); @endphp @continue(! $p)
                                <span @class(['sta-chip-person', 'is-bad' => $err]) wire:key="chip-{{ $ge->id }}-{{ $id }}">
                                    <x-sta.person-avatar :name="$p?->fullName() ?? '?'" size="sm" />
                                    <span class="sta-who-name">{{ $p?->fullName() }}</span>
                                    <span class="sta-who-sub">{{ $p?->code }}</span>
                                    @unless ($readOnlyReason)
                                        <button type="button" wire:click="removeAthleteChip({{ $ge->id }}, {{ $id }})" aria-label="Remove {{ $p?->fullName() }} from {{ $ge->event->name }}">×</button>
                                    @endunless
                                </span>
                            @endforeach
                            @unless ($readOnlyReason)
                                <select id="event-{{ $ge->id }}" aria-label="Add an athlete to {{ $ge->event->name }}" wire:change="addAthleteChip({{ $ge->id }}, $event.target.value)">
                                    <option value="">Search athletes…</option>
                                    @foreach ($athleteOptions->filter(fn ($p) => \App\Services\Phases\LongListChecks::genderMatches($p->gender, $ge->event->gender))->reject(fn ($p) => in_array($p->id, $chosen, true)) as $p)
                                        <option value="{{ $p->id }}">{{ $p->fullName() }} ({{ $p->code }})</option>
                                    @endforeach
                                </select>
                            @endunless
                        </div>
                        @if ($err)<p class="sta-error" role="alert">{{ $err }}</p>@endif
                    </div>
                @endforeach

                @php
                    $chosenOfficials = array_map('intval', (array) ($officials[$gs->id] ?? []));
                    $oErr = $errors->first('officials.'.$gs->id);
                @endphp
                <div class="sta-event-box sta-event-box-officials" wire:key="officials-{{ $gs->id }}">
                    <div class="sta-row-between">
                        <label class="sta-event-name" for="officials-{{ $gs->id }}">Team officials</label>
                        <span class="sta-muted">For the whole sport, not one event · active officials only</span>
                    </div>
                    <div @class(['sta-token-field', 'is-error' => $oErr])>
                        @foreach ($chosenOfficials as $id)
                            @php $p = $people->get($id); @endphp @continue(! $p)
                            <span class="sta-chip-person" wire:key="ochip-{{ $gs->id }}-{{ $id }}">
                                <x-sta.person-avatar :name="$p?->fullName() ?? '?'" size="sm" />
                                <span class="sta-who-name">{{ $p?->fullName() }}</span>
                                <span class="sta-who-sub">{{ $p?->function }}</span>
                                @unless ($readOnlyReason)
                                    <button type="button" wire:click="removeOfficialChip({{ $gs->id }}, {{ $id }})" aria-label="Remove {{ $p?->fullName() }} from {{ $gs->sport->name }} team officials">×</button>
                                @endunless
                            </span>
                        @endforeach
                        @unless ($readOnlyReason)
                            <select id="officials-{{ $gs->id }}" aria-label="Add a team official to {{ $gs->sport->name }}" wire:change="addOfficialChip({{ $gs->id }}, $event.target.value)">
                                <option value="">Search officials…</option>
                                @foreach ($officialOptions->reject(fn ($p) => in_array($p->id, $chosenOfficials, true)) as $p)
                                    <option value="{{ $p->id }}">{{ $p->fullName() }} – {{ $p->function }} ({{ $p->code }})</option>
                                @endforeach
                            </select>
                        @endunless
                    </div>
                    @if ($oErr)<p class="sta-error" role="alert">{{ $oErr }}</p>@endif
                </div>
            </div>
        </x-sta.card>
    @empty
        <p class="sta-muted">No sports to enter for your association in this game.</p>
    @endforelse

    @php
        $rows = $this->listRows();
        $isAthlete = fn ($r) => $r['person']->type === \App\Enums\PersonType::Athlete;
        $male = $rows->filter(fn ($r) => $isAthlete($r) && $r['person']->gender === \App\Enums\PersonGender::Male)->count();
        $female = $rows->filter(fn ($r) => $isAthlete($r) && $r['person']->gender === \App\Enums\PersonGender::Female)->count();
        $offs = $rows->reject($isAthlete)->count();
    @endphp
    <section aria-labelledby="ll-people" class="sta-stack">
        {{-- View choice is per user, browser-only (localStorage), never sent to the server. --}}
        <div class="sta-stack" x-data="{ view: (() => { try { return localStorage.getItem('longListView.{{ auth()->id() }}') || 'list' } catch (e) { return 'list' } })() }"
             x-init="$watch('view', v => { try { localStorage.setItem('longListView.{{ auth()->id() }}', v) } catch (e) {} })">
            <div class="sta-row-between">
                <h2 id="ll-people" class="sta-card-title">People on your Long List</h2>
                <div class="sta-row-between">
                    <span class="sta-muted">{{ $male }} male · {{ $female }} female · {{ $offs }} team officials</span>
                    <div class="sta-seg" role="group" aria-label="Long List view">
                        <button type="button" x-on:click="view = 'list'" x-bind:aria-pressed="view === 'list'">List</button>
                        <button type="button" x-on:click="view = 'grid'" x-bind:aria-pressed="view === 'grid'">Grid</button>
                    </div>
                </div>
            </div>
            <div x-show="view === 'list'" class="sta-card">
                @include('filament.shared.long-list-people', ['rows' => $rows, 'showTravel' => ! $readOnlyReason])
            </div>
            {{-- Rendered only in Grid view so List view does not fetch every photo on each Livewire render. --}}
            <template x-if="view === 'grid'"><div class="sta-grid-cards">
                @forelse ($rows as $row)
                    @php $p = $row['person']; @endphp
                    <article wire:key="card-{{ $p->id }}" class="sta-card sta-person-card">
                        <div class="sta-who">
                            @if ($p->photo_path)
                                <img loading="lazy" src="{{ \App\Filament\Shared\AttachmentActions::url($p->photo_path) }}" alt="Photo of {{ $p->fullName() }}" class="sta-avatar sta-avatar-lg" style="object-fit: cover">
                            @else
                                <x-sta.person-avatar :name="$p->fullName()" size="lg" />
                            @endif
                            <div class="sta-who-text" style="flex: 1">
                                <span class="sta-who-name">{{ $p->fullName() }}</span>
                                <span class="sta-who-sub">{{ $p->code }} · {{ $p->dob?->format('j M Y') }} · {{ $row['age'] }}</span>
                            </div>
                            <span class="sta-tag">{{ $p->gender->label() }}</span>
                        </div>
                        <div class="sta-chips">@foreach ($row['roles'] as $role)<span class="sta-tag">{{ $role }}</span>@endforeach</div>
                        <div><x-sta.pill :status="$row['missing'] ? 'warn' : 'ok'">{{ $row['missing'] ? 'Incomplete' : 'Complete' }}</x-sta.pill></div>
                        @if ($row['warnings'])
                            <ul class="sta-banner sta-banner-warn sta-warn-list">@foreach ($row['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul>
                        @endif
                    </article>
                @empty
                    <p class="sta-muted">Nobody on the list yet.</p>
                @endforelse
            </div></template>
        </div>
    </section>

    @unless ($readOnlyReason)
        <div><x-filament::button color="gray" wire:click="save">Save as draft</x-filament::button></div>
    @endunless
    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
