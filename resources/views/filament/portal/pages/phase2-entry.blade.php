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
    @endphp

    @forelse ($this->visibleSports() as $gs)
        @php
            $sum = ['men' => 0, 'women' => 0, 'total' => 0];
            foreach ($gs->gameEvents as $ge) {
                $n = max(0, (int) ($counts[$ge->id] ?? 0));
                $sum['total'] += $n;
                if ($ge->event->gender === $men) { $sum['men'] += $n; }
                if ($ge->event->gender === $women) { $sum['women'] += $n; }
            }
        @endphp
        <x-filament::section :heading="$gs->sport->name">
            @if ($gs->entry_policy)
                <p class="text-sm">{{ $gs->entry_policy }}</p>
            @endif
            @if ($gs->dob_from || $gs->dob_to)
                <p class="text-xs text-gray-500">Born {{ $gs->dob_from ? 'from '.$gs->dob_from->format('j M Y') : '' }} {{ $gs->dob_to ? 'to '.$gs->dob_to->format('j M Y') : '' }}</p>
            @endif

            <table class="mt-3 w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500"><th class="py-1">Event</th><th class="py-1">Male</th><th class="py-1">Female</th></tr>
                </thead>
                <tbody>
                    @foreach ($gs->gameEvents as $ge)
                        @php
                            $g = $ge->event->gender;
                            $msg = $errors->first('counts.'.$ge->id) ?: ($liveErrors['counts.'.$ge->id] ?? null);
                        @endphp
                        <tr class="border-t border-gray-200 align-top dark:border-white/10">
                            <td class="py-2">{{ $ge->event->name }}
                                @if ($ge->p2_max_athletes !== null)<span class="text-xs text-gray-500">(max {{ $ge->p2_max_athletes }})</span>@endif
                            </td>
                            @if ($g === $mixed)
                                <td colspan="2" class="py-2">
                                    <input type="number" min="0" aria-label="{{ $ge->event->name }} (Mixed)" class="w-24 rounded border-gray-300 text-sm dark:bg-white/5"
                                        wire:model.live.debounce.400ms="counts.{{ $ge->id }}" @disabled($readOnlyReason)>
                                    <span class="text-xs text-gray-500">Mixed</span>
                                    @if ($msg)<p class="text-xs text-danger-600">{{ $msg }}</p>@endif
                                </td>
                            @else
                                @foreach ([$men, $women] as $col)
                                    @if ($g === $col)
                                        <td class="py-2">
                                            <input type="number" min="0" aria-label="{{ $ge->event->name }}" class="w-24 rounded border-gray-300 text-sm dark:bg-white/5"
                                                wire:model.live.debounce.400ms="counts.{{ $ge->id }}" @disabled($readOnlyReason)>
                                            @if ($msg)<p class="text-xs text-danger-600">{{ $msg }}</p>@endif
                                        </td>
                                    @else
                                        <td class="bg-gray-100 py-2 dark:bg-white/5"></td>
                                    @endif
                                @endforeach
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-300 font-medium dark:border-white/20">
                        <td class="py-2">Total</td>
                        <td>{{ $sum['men'] }}@if ($gs->p2_max_men !== null) / {{ $gs->p2_max_men }}@endif</td>
                        <td>{{ $sum['women'] }}@if ($gs->p2_max_women !== null) / {{ $gs->p2_max_women }}@endif</td>
                    </tr>
                    <tr class="text-gray-600 dark:text-gray-400">
                        <td class="py-1">All events</td>
                        <td colspan="2">{{ $sum['total'] }}@if ($gs->p2_max_total !== null) / {{ $gs->p2_max_total }}@endif</td>
                    </tr>
                </tfoot>
            </table>

            @foreach (['men', 'women', 'total'] as $k)
                @php($sMsg = $errors->first("sport.{$gs->id}.{$k}") ?: ($liveErrors["sport.{$gs->id}.{$k}"] ?? null))
                @if ($sMsg)<p class="mt-1 text-xs text-danger-600">{{ $sMsg }}</p>@endif
            @endforeach

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
