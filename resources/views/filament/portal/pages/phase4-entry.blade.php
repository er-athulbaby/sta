<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Deadline: {{ $this->submission->gamePhase()->effectiveDeadline($this->submission)?->format('j M Y H:i') ?? 'none' }}
    </p>

    @if ($readOnlyReason)
        <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">{{ $readOnlyReason }}</div>
    @endif

    @php $paths = $this->submission->pdf_paths ?? []; @endphp
    @if ($this->submission->status === \App\Enums\SubmissionStatus::Submitted && isset($paths['short_list']))
        <a class="text-sm text-primary-600 underline" target="_blank"
           href="{{ \App\Filament\Shared\AttachmentActions::url($paths['short_list']) }}">View Short List PDF</a>
    @endif

    @if (! $this->longListSubmitted())
        <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">Submit the Long List first.</div>
    @else
        @php
            [$slotAthletes, $slotOfficials] = $this->longListSlots();
            $people = $this->longListPeople();
            $over = $this->overLimits();
        @endphp

        @if ($over)
            <div class="rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                <p class="font-medium">Over the Short List limit — a justification is required before submitting:</p>
                <ul class="list-disc pl-5">
                    @foreach ($over as $o)<li>{{ \App\Services\Phases\ShortListChecks::describe($o) }}</li>@endforeach
                </ul>
            </div>
        @endif

        @foreach ($this->visibleSports() as $gs)
            @php $events = $gs->gameEvents->filter(fn ($ge) => ! empty($slotAthletes[$ge->id])); @endphp
            @continue($events->isEmpty() && empty($slotOfficials[$gs->id]))
            <x-filament::section :heading="$gs->sport->name"
                :description="'Limits: male '.($gs->p4_max_men ?? '–').' · female '.($gs->p4_max_women ?? '–').' · team officials '.($gs->p4_max_officials ?? '–')">
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($events as $ge)
                        <fieldset wire:key="event-{{ $ge->id }}">
                            <legend class="text-sm font-medium">{{ $ge->event->name }} ({{ $ge->event->gender->label() }})</legend>
                            @foreach ($slotAthletes[$ge->id] as $id)
                                @php $p = $people->get((int) $id); @endphp
                                @continue(! $p)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" value="{{ $id }}" wire:model.live="athletes.{{ $ge->id }}" @disabled($readOnlyReason)>
                                    {{ $p->fullName() }} <span class="text-xs text-gray-500">{{ $p->code }}</span>
                                </label>
                            @endforeach
                            @error('athletes.'.$ge->id)<p class="text-xs text-danger-600">{{ $message }}</p>@enderror
                        </fieldset>
                    @endforeach
                    @if (! empty($slotOfficials[$gs->id]))
                        <fieldset wire:key="officials-{{ $gs->id }}">
                            <legend class="text-sm font-medium">Team officials</legend>
                            @foreach ($slotOfficials[$gs->id] as $id)
                                @php $p = $people->get((int) $id); @endphp
                                @continue(! $p)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" value="{{ $id }}" wire:model.live="officials.{{ $gs->id }}" @disabled($readOnlyReason)>
                                    {{ $p->fullName() }} – {{ $p->function }} <span class="text-xs text-gray-500">{{ $p->code }}</span>
                                </label>
                            @endforeach
                            @error('officials.'.$gs->id)<p class="text-xs text-danger-600">{{ $message }}</p>@enderror
                        </fieldset>
                    @endif
                </div>
            </x-filament::section>
        @endforeach

        <x-filament::section heading="Justification">
            <p class="text-xs text-gray-500">Required before submitting when the Short List is over a limit (at most 2000 characters).</p>
            <textarea wire:model="justification" rows="4" maxlength="2000" class="w-full rounded border-gray-300 text-sm dark:bg-white/5" @disabled($readOnlyReason)></textarea>
            @error('justification')<p class="text-xs text-danger-600">{{ $message }}</p>@enderror
        </x-filament::section>

        <x-filament::section heading="People on the Short List">
            @include('filament.shared.long-list-people', ['rows' => $this->listRows()])
        </x-filament::section>

        @unless ($readOnlyReason)
            <div><x-filament::button wire:click="save">Save as draft</x-filament::button></div>
        @endunless
    @endif

    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
