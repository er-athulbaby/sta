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
           href="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.private'))->temporaryUrl($paths['long_list'], now()->addMinutes(5)) }}">Download Long List PDF</a>
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
        @include('filament.shared.long-list-people', ['rows' => $this->listRows(), 'showTravel' => ! $readOnlyReason])
    </x-filament::section>

    @unless ($readOnlyReason)
        <div><x-filament::button wire:click="save">Save</x-filament::button></div>
    @endunless
    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
