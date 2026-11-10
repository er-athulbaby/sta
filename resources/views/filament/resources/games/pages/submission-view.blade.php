<x-filament-panels::page>
    @php
        $s = $this->submission;
        $deadline = $s->gamePhase()->effectiveDeadline($s);
        $showMedical = \App\Models\Person::canViewMedical(auth()->user());
        $links = \App\Filament\Resources\Games\Pages\GameDashboard::pdfLinks($s);
    @endphp

    <x-filament::section>
        <dl class="grid gap-3 text-sm sm:grid-cols-3">
            <div><dt class="text-gray-500">Association</dt><dd>{{ $s->association->name }} ({{ $s->association->code }})</dd></div>
            <div><dt class="text-gray-500">Phase</dt><dd>Phase {{ $s->phase }} · {{ \App\Models\GamePhase::NAMES[$s->phase] }}</dd></div>
            <div><dt class="text-gray-500">Status</dt><dd>{{ $s->status->label() }}</dd></div>
            <div><dt class="text-gray-500">Reference</dt><dd>{{ $s->reference_no ?? '–' }}</dd></div>
            <div><dt class="text-gray-500">Deadline</dt><dd>{{ $deadline?->format('j M Y H:i') ?? 'none' }}</dd></div>
            <div><dt class="text-gray-500">PDFs</dt><dd>
                @forelse ($links as $link)
                    <a href="{{ $link['url'] }}" target="_blank" class="block text-primary-600 underline">{{ $link['name'] }}</a>
                @empty
                    –
                @endforelse
            </dd></div>
        </dl>
    </x-filament::section>

    @if ($warnings = $this->shortListWarnings())
        <x-filament::section heading="Short List needs attention">
            @foreach ($warnings as $w)
                <p class="text-sm text-danger-600">{{ $w }}</p>
            @endforeach
        </x-filament::section>
    @endif

    @if ($s->phase === 1)
        <x-filament::section heading="Entry by Sport">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-1">Sport</th><th class="py-1">Answer</th></tr></thead>
                <tbody>
                    @foreach ($this->phase1Rows() as $r)
                        <tr class="border-t border-gray-200 dark:border-white/10"><td class="py-1">{{ $r['sport'] }}</td><td class="py-1">{{ $r['answer'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @elseif ($s->phase === 2)
        <x-filament::section heading="Entry by Number">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-1">Sport</th><th class="py-1">Event</th><th class="py-1">Athletes</th></tr></thead>
                <tbody>
                    @foreach ($this->phase2Rows() as $r)
                        <tr class="border-t border-gray-200 dark:border-white/10"><td class="py-1">{{ $r['sport'] }}</td><td class="py-1">{{ $r['event'] }}</td><td class="py-1">{{ $r['count'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
        <x-filament::section heading="Participants and expected medals">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-1">Sport</th><th class="py-1">Male</th><th class="py-1">Female</th><th class="py-1">Gold</th><th class="py-1">Silver</th><th class="py-1">Bronze</th></tr></thead>
                <tbody>
                    @foreach ($this->phase2SportTotals() as $r)
                        <tr class="border-t border-gray-200 dark:border-white/10"><td class="py-1">{{ $r['sport'] }}</td>@foreach (['male', 'female', 'gold', 'silver', 'bronze'] as $f)<td class="py-1">{{ $r[$f] ?? '–' }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @elseif ($s->phase === 3)
        <x-filament::section heading="Long List">
            @include('filament.shared.long-list-people', ['rows' => $this->longListRows(), 'showDetails' => true, 'showMedical' => $showMedical])
        </x-filament::section>
    @elseif ($s->phase === 4)
        <x-filament::section heading="Short List">
            @if ($lines = $this->overLimitLines())
                <ul class="mb-2 list-disc pl-5 text-sm text-warning-600">
                    @foreach ($lines as $line)<li>Over the limit: {{ $line }}</li>@endforeach
                </ul>
            @endif
            @if (filled($s->justification))
                <p class="mb-3 whitespace-pre-line text-sm"><span class="text-gray-500">Justification:</span> {{ $s->justification }}</p>
            @endif
            @include('filament.shared.long-list-people', ['rows' => $this->longListRows()])
        </x-filament::section>
    @endif

    @include('filament.partials.comments', ['comments' => $s->commentsNewestFirst()])

    <x-filament::section heading="Audit trail">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500"><th class="py-1">When</th><th>Who</th><th>What</th><th>Changed</th></tr></thead>
            <tbody>
                @forelse ($this->auditTrail() as $a)
                    <tr class="border-t border-gray-200 align-top dark:border-white/10">
                        <td class="py-1">{{ $a->created_at->format('j M Y H:i') }}</td>
                        <td class="py-1">{{ $a->causer?->name ?? 'System' }}</td>
                        <td class="py-1">{{ class_basename($a->subject_type) }} {{ $a->event }}</td>
                        <td class="py-1 text-xs">{{ implode(', ', array_keys($a->properties->get('attributes', $a->properties->get('old', [])))) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-2 text-gray-500">No changes recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
