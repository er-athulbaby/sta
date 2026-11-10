<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Deadline: {{ $this->submission->gamePhase()->effectiveDeadline($this->submission)?->format('j M Y H:i') ?? 'none' }}
    </p>

    @if ($readOnlyReason)
        <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">{{ $readOnlyReason }}</div>
    @endif

    @php
        $p2 = $this->phase2Numbers();
        $athleteOptions = $this->candidates(\App\Enums\PersonType::Athlete);
        $officialOptions = $this->candidates(\App\Enums\PersonType::Official);
        $selectClass = 'w-full rounded border-gray-300 text-sm dark:bg-white/5';
    @endphp

    @php $paths = $this->submission->pdf_paths ?? []; @endphp
    @if ($this->submission->status === \App\Enums\SubmissionStatus::Submitted && isset($paths['long_list']))
        <a class="text-sm text-primary-600 underline" target="_blank"
           href="{{ \App\Filament\Shared\AttachmentActions::url($paths['long_list']) }}">View Long List PDF</a>
    @endif

    @if ($url = $this->registryUrl())
        <p class="text-sm"><a href="{{ $url }}" target="_blank" class="text-primary-600 underline">Open registry</a>
            <span class="text-gray-500">to edit people; new people added with the buttons above appear in the lists straight away.</span></p>
    @endif

    @forelse ($this->visibleSports() as $gs)
        <x-filament::section :heading="$gs->sport->name">
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($gs->gameEvents as $ge)
                    @php
                        $n = count((array) ($athletes[$ge->id] ?? []));
                        $max = $p2 === null ? null : ($p2[$ge->id] ?? 0);
                        $over = $max !== null && $n > $max;
                    @endphp
                    <div wire:key="event-{{ $ge->id }}">
                        <label class="text-sm font-medium" for="event-{{ $ge->id }}">{{ $ge->event->name }} ({{ $ge->event->gender->label() }})</label>
                        <p @class(['text-xs', 'font-medium text-warning-600' => $over, 'text-gray-500' => ! $over])>Long List {{ $n }}{{ $max !== null ? " / Phase 2: $max" : "" }}{{ $over ? " · Over the Phase 2 number" : "" }}</p>
                        <select id="event-{{ $ge->id }}" multiple size="6" class="{{ $selectClass }}" wire:model.live="athletes.{{ $ge->id }}" @disabled($readOnlyReason)>
                            @foreach ($athleteOptions->filter(fn ($p) => \App\Services\Phases\LongListChecks::genderMatches($p->gender, $ge->event->gender)) as $p)
                                <option value="{{ $p->id }}">{{ $p->fullName() }} ({{ $p->code }})</option>
                            @endforeach
                        </select>
                        @error('athletes.'.$ge->id)<p class="text-xs text-danger-600">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div wire:key="officials-{{ $gs->id }}">
                    <label class="text-sm font-medium" for="officials-{{ $gs->id }}">Team officials</label>
                    <select id="officials-{{ $gs->id }}" multiple size="6" class="{{ $selectClass }}" wire:model.live="officials.{{ $gs->id }}" @disabled($readOnlyReason)>
                        @foreach ($officialOptions as $p)
                            <option value="{{ $p->id }}">{{ $p->fullName() }} – {{ $p->function }} ({{ $p->code }})</option>
                        @endforeach
                    </select>
                    @error('officials.'.$gs->id)<p class="text-xs text-danger-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-filament::section>
    @empty
        <p class="text-sm text-gray-500">No sports to enter for your association in this game.</p>
    @endforelse

    <x-filament::section heading="People on the Long List">
        @php $rows = $this->listRows(); @endphp
        {{-- View choice is per user, browser-only (localStorage), never sent to the server. --}}
        <div x-data="{ view: (() => { try { return localStorage.getItem('longListView.{{ auth()->id() }}') || 'list' } catch (e) { return 'list' } })() }"
             x-init="$watch('view', v => { try { localStorage.setItem('longListView.{{ auth()->id() }}', v) } catch (e) {} })">
            <div class="mb-3 flex gap-2" role="group" aria-label="Long List view">
                <x-filament::button size="xs" color="gray" x-on:click="view = 'list'" x-bind:aria-pressed="view === 'list'">List</x-filament::button>
                <x-filament::button size="xs" color="gray" x-on:click="view = 'grid'" x-bind:aria-pressed="view === 'grid'">Grid</x-filament::button>
            </div>
            <div x-show="view === 'list'">
                @include('filament.shared.long-list-people', ['rows' => $rows, 'showTravel' => ! $readOnlyReason])
            </div>
            <div x-show="view === 'grid'" x-cloak class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($rows as $row)
                    @php $p = $row['person']; @endphp
                    <div wire:key="card-{{ $p->id }}" class="flex gap-3 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        @if ($p->photo_path)
                            <img src="{{ \App\Filament\Shared\AttachmentActions::url($p->photo_path) }}" alt="" class="h-16 w-12 rounded object-cover">
                        @else
                            <div class="h-16 w-12 rounded bg-gray-100 dark:bg-white/5"></div>
                        @endif
                        <div class="min-w-0 text-sm">
                            <div class="font-medium">{{ $p->fullName() }}</div>
                            <div class="text-xs text-gray-500">{{ $p->code }}</div>
                            <div class="text-xs">{{ implode(', ', $row['roles']) }}</div>
                            <x-filament::badge :color="$row['missing'] ? 'danger' : 'success'" size="sm">{{ $row['missing'] ? 'Incomplete' : 'Complete' }}</x-filament::badge>
                            <ul class="text-xs text-warning-600">@foreach ($row['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Nobody on the list yet.</p>
                @endforelse
            </div>
        </div>
    </x-filament::section>

    @unless ($readOnlyReason)
        <div><x-filament::button wire:click="save">Save as draft</x-filament::button></div>
    @endunless
    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
