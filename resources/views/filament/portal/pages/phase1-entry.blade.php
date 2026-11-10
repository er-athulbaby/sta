<x-filament-panels::page>
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Deadline: {{ $this->submission->gamePhase()->effectiveDeadline($this->submission)?->format('j M Y H:i') ?? 'none' }}
    </p>

    @if ($readOnlyReason)
        <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">{{ $readOnlyReason }}</div>
    @endif

    @php $paths = $this->submission->pdf_paths ?? []; @endphp
    @if ($this->submission->status === \App\Enums\SubmissionStatus::Submitted && isset($paths['entry_by_sport']))
        <a class="text-sm text-primary-600 underline" target="_blank"
           href="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.private'))->temporaryUrl($paths['entry_by_sport'], now()->addMinutes(5)) }}">Download Entry by Sport PDF</a>
    @endif

    <x-filament::section>
        @forelse ($this->sports as $gs)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 py-3 last:border-0 dark:border-white/10">
                <span class="font-medium">{{ $gs->sport->name }}</span>
                <div class="flex items-center gap-4" role="radiogroup" aria-label="{{ $gs->sport->name }}">
                    @foreach (['yes' => 'Yes', 'no' => 'No', 'maybe' => 'Maybe'] as $value => $label)
                        <label class="flex items-center gap-1 text-sm">
                            <input type="radio" name="answer-{{ $gs->id }}" wire:model="answers.{{ $gs->id }}" value="{{ $value }}" @disabled($readOnlyReason)>
                            {{ $label }}
                        </label>
                    @endforeach
                    @unless (isset($answers[$gs->id]))
                        <span class="text-xs text-gray-500">No response</span>
                    @endunless
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">No sports for your association in this game.</p>
        @endforelse
    </x-filament::section>

    @unless ($readOnlyReason)
        <div><x-filament::button wire:click="save">Save</x-filament::button></div>
    @endunless
    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
