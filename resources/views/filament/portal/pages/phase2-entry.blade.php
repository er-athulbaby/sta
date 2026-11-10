<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Deadline: {{ $this->submission->gamePhase()->effectiveDeadline($this->submission)?->format('j M Y H:i') ?? 'none' }}
    </p>

    @if ($readOnlyReason)
        <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">{{ $readOnlyReason }}</div>
    @endif

    @php
        $paths = $this->submission->pdf_paths ?? [];
        $submitted = $this->submission->status === \App\Enums\SubmissionStatus::Submitted;
        $men = \App\Enums\EventGender::Men;
        $women = \App\Enums\EventGender::Women;
        $mixed = \App\Enums\EventGender::Mixed;
        $inputClass = 'w-24 rounded border-gray-300 text-sm dark:bg-white/5';
        $msg = fn (string $key) => $errors->first($key) ?: ($liveErrors[$key] ?? null);
    @endphp

    @forelse ($this->visibleSports() as $gs)
        <x-filament::section :heading="$gs->sport->name">
            @if ($gs->entry_policy)
                <p class="text-sm">{{ $gs->entry_policy }}</p>
            @endif
            @if ($gs->dob_from || $gs->dob_to)
                <p class="text-xs text-gray-500">Born {{ $gs->dob_from ? 'from '.$gs->dob_from->format('j M Y') : '' }} {{ $gs->dob_to ? 'to '.$gs->dob_to->format('j M Y') : '' }}</p>
            @endif

            <fieldset class="mt-3" wire:key="participants-{{ $gs->id }}">
                <legend class="text-sm font-medium">Total number of participants</legend>
                <p class="text-xs text-gray-500">Count each athlete once, even if they enter several events.</p>
                <div class="mt-1 flex flex-wrap items-start gap-4">
                    @foreach (['male' => ['Male', $gs->p2_max_men], 'female' => ['Female', $gs->p2_max_women]] as $field => [$label, $max])
                        <div>
                            <label class="text-sm" for="totals-{{ $gs->id }}-{{ $field }}">{{ $label }}@if ($max !== null) <span class="text-xs text-gray-500">(up to {{ $max }})</span>@endif</label>
                            <input id="totals-{{ $gs->id }}-{{ $field }}" type="number" min="0" max="999" class="block {{ $inputClass }}"
                                wire:model.live.debounce.400ms="totals.{{ $gs->id }}.{{ $field }}" @disabled($readOnlyReason)>
                            @if ($m = $msg("totals.{$gs->id}.{$field}"))<p class="text-xs text-danger-600">{{ $m }}</p>@endif
                        </div>
                    @endforeach
                    @if ($gs->p2_max_total !== null)
                        <p class="pt-6 text-xs text-gray-500">Male + female: up to {{ $gs->p2_max_total }}</p>
                    @endif
                </div>
                @foreach (['male', 'female', 'total'] as $k)
                    @if ($m = $msg("sport.{$gs->id}.{$k}"))<p class="mt-1 text-xs text-danger-600">{{ $m }}</p>@endif
                @endforeach
            </fieldset>

            <table class="mt-3 w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500"><th class="py-1">Event</th><th class="py-1">Male</th><th class="py-1">Female</th></tr>
                </thead>
                <tbody>
                    @foreach ($gs->gameEvents as $ge)
                        @php
                            $g = $ge->event->gender;
                            $eMsg = $msg('counts.'.$ge->id);
                        @endphp
                        <tr class="border-t border-gray-200 align-top dark:border-white/10">
                            <td class="py-2">{{ $ge->event->name }}
                                @if ($ge->p2_max_athletes !== null)
                                    <span class="block text-xs text-gray-500">Up to {{ $ge->p2_max_athletes }} {{ \Illuminate\Support\Str::plural('athlete', $ge->p2_max_athletes) }}</span>
                                @endif
                            </td>
                            @if ($g === $mixed)
                                <td colspan="2" class="py-2">
                                    <input type="number" min="0" aria-label="{{ $ge->event->name }} (Mixed)" class="{{ $inputClass }}"
                                        wire:model.live.debounce.400ms="counts.{{ $ge->id }}" @disabled($readOnlyReason)>
                                    <span class="text-xs text-gray-500">Mixed</span>
                                    @if ($eMsg)<p class="text-xs text-danger-600">{{ $eMsg }}</p>@endif
                                </td>
                            @else
                                @foreach ([$men, $women] as $col)
                                    @if ($g === $col)
                                        <td class="py-2">
                                            <input type="number" min="0" aria-label="{{ $ge->event->name }}" class="{{ $inputClass }}"
                                                wire:model.live.debounce.400ms="counts.{{ $ge->id }}" @disabled($readOnlyReason)>
                                            @if ($eMsg)<p class="text-xs text-danger-600">{{ $eMsg }}</p>@endif
                                        </td>
                                    @else
                                        <td class="bg-gray-100 py-2 dark:bg-white/5"></td>
                                    @endif
                                @endforeach
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <fieldset class="mt-4" wire:key="medals-{{ $gs->id }}">
                <legend class="text-sm font-medium">Expected medals <span class="text-xs font-normal text-gray-500">(optional)</span></legend>
                <div class="mt-1 flex flex-wrap items-start gap-4">
                    @foreach (['gold' => 'Gold', 'silver' => 'Silver', 'bronze' => 'Bronze'] as $field => $label)
                        <div>
                            <label class="text-sm" for="totals-{{ $gs->id }}-{{ $field }}">{{ $label }}</label>
                            <input id="totals-{{ $gs->id }}-{{ $field }}" type="number" min="0" max="99" class="block {{ $inputClass }}"
                                wire:model.live.debounce.400ms="totals.{{ $gs->id }}.{{ $field }}" @disabled($readOnlyReason)>
                            @if ($m = $msg("totals.{$gs->id}.{$field}"))<p class="text-xs text-danger-600">{{ $m }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </fieldset>

            @if ($submitted && isset($paths[$gs->id]))
                <a class="mt-3 inline-block text-sm text-primary-600 underline" href="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.private'))->temporaryUrl($paths[$gs->id], now()->addMinutes(5)) }}">Download Entry by Event PDF</a>
            @endif
        </x-filament::section>
    @empty
        <p class="text-sm text-gray-500">No sports to enter for your association in this game.</p>
    @endforelse

    @unless ($readOnlyReason)
        <div><x-filament::button wire:click="save">Save</x-filament::button></div>
    @endunless
    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
