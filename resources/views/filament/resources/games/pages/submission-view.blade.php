{{-- No canvas screen: built from the shared canvas vocabulary (sta cards, tables, pills, phase chip). --}}
<x-filament-panels::page>
    @php
        $s = $this->submission;
        $deadline = $s->gamePhase()->effectiveDeadline($s);
        $showMedical = \App\Models\Person::canViewMedical(auth()->user());
        $links = \App\Filament\Resources\Games\Pages\GameDashboard::pdfLinks($s);
    @endphp

    <x-sta.card>
        <dl class="sta-card-body grid gap-3 text-sm sm:grid-cols-3" style="margin: 0">
            <div><dt class="sta-muted">Association</dt><dd style="margin: 4px 0 0"><span class="sta-who"><x-sta.assoc-avatar :association="$s->association" size="sm" /> {{ $s->association->name }} ({{ $s->association->code }})</span></dd></div>
            <div><dt class="sta-muted">Phase</dt><dd style="margin: 4px 0 0"><x-sta.phase-chip :phase="$s->phase" :label="'Phase '.$s->phase.' · '.\App\Models\GamePhase::NAMES[$s->phase]" /></dd></div>
            <div><dt class="sta-muted">Status</dt><dd style="margin: 4px 0 0"><x-sta.pill :status="\App\Support\Sta::submissionPill($s->status)">{{ $s->status->label() }}</x-sta.pill></dd></div>
            <div><dt class="sta-muted">Reference</dt><dd style="margin: 4px 0 0; font-weight: 600">{{ $s->reference_no ?? '–' }}</dd></div>
            <div><dt class="sta-muted">Deadline</dt><dd style="margin: 4px 0 0; font-weight: 600">{{ $deadline?->format('j M Y H:i') ?? 'none' }}</dd></div>
            <div><dt class="sta-muted">PDFs</dt><dd style="margin: 4px 0 0">
                @forelse ($links as $link)
                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="block" style="font-weight: 600">{{ $link['name'] }}</a>
                @empty
                    –
                @endforelse
            </dd></div>
        </dl>
    </x-sta.card>

    @if ($warnings = $this->shortListWarnings())
        <div class="sta-banner sta-banner-warn" role="status">
            <strong>Short List needs attention</strong>
            @foreach ($warnings as $w)
                <span style="flex-basis: 100%">{{ $w }}</span>
            @endforeach
        </div>
    @endif

    @if ($s->phase === 1)
        <x-sta.card heading="Entry by Sport">
            <div class="sta-scroll">
                <table class="sta-table w-full text-sm">
                    <thead><tr><th class="py-1">Sport</th><th class="py-1">Answer</th><th class="py-1">Note</th></tr></thead>
                    <tbody>
                        @foreach ($this->phase1Rows() as $r)
                            <tr><td class="py-1">{{ $r['sport'] }}</td><td class="py-1">{{ $r['answer'] }}</td><td class="py-1">{{ $r['note'] ?? '' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-sta.card>
    @elseif ($s->phase === 2)
        <x-sta.card heading="Entry by Number">
            <div class="sta-scroll">
                <table class="sta-table w-full text-sm">
                    <thead><tr><th class="py-1">Sport</th><th class="py-1">Event</th><th class="py-1">Athletes</th></tr></thead>
                    <tbody>
                        @foreach ($this->phase2Rows() as $r)
                            <tr><td class="py-1">{{ $r['sport'] }}</td><td class="py-1">{{ $r['event'] }}</td><td class="py-1">{{ $r['count'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-sta.card>
        <x-sta.card heading="Participants and expected medals">
            <div class="sta-scroll">
                <table class="sta-table w-full text-sm">
                    <thead><tr><th class="py-1">Sport</th><th class="py-1">Male</th><th class="py-1">Female</th><th class="py-1">Gold</th><th class="py-1">Silver</th><th class="py-1">Bronze</th></tr></thead>
                    <tbody>
                        @foreach ($this->phase2SportTotals() as $r)
                            <tr><td class="py-1">{{ $r['sport'] }}</td>@foreach (['male', 'female', 'gold', 'silver', 'bronze'] as $f)<td class="py-1">{{ $r[$f] ?? '–' }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-sta.card>
    @elseif ($s->phase === 3)
        <x-sta.card heading="Long List">
            @include('filament.shared.long-list-people', ['rows' => $this->longListRows(), 'showDetails' => true, 'showMedical' => $showMedical])
        </x-sta.card>
    @elseif ($s->phase === 4)
        <x-sta.card heading="Short List">
            @if (($lines = $this->overLimitLines()) || filled($s->justification))
                <div class="sta-card-body sta-stack" style="border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
                    @if ($lines)
                        <ul class="sta-warn-list">
                            @foreach ($lines as $line)<li>Over the limit: {{ $line }}</li>@endforeach
                        </ul>
                    @endif
                    @if (filled($s->justification))
                        <p class="whitespace-pre-line" style="padding: 12px 14px; border-radius: 10px; background: var(--sta-bg-violet-50, #F5F3FF); border: 1px solid var(--sta-bd-violet-200, #DDD6FE)"><span style="font-weight: 600; color: var(--sta-fg-violet-800, #5B21B6)">Justification:</span> {{ $s->justification }}</p>
                    @endif
                </div>
            @endif
            @include('filament.shared.long-list-people', ['rows' => $this->longListRows()])
        </x-sta.card>
    @endif

    @include('filament.partials.comments', ['comments' => $s->commentsNewestFirst()])

    <x-sta.card heading="Audit trail">
        <div class="sta-scroll">
            <table class="sta-table w-full text-sm">
                <thead><tr><th class="py-1">When</th><th>Who</th><th>What</th><th>Changed</th></tr></thead>
                <tbody>
                    @forelse ($this->auditTrail() as $a)
                        <tr class="align-top">
                            <td class="py-1" style="white-space: nowrap; color: var(--sta-fg-muted, #475569)">{{ $a->created_at->format('j M Y H:i') }}</td>
                            <td class="py-1" style="font-weight: 600">{{ $a->causer?->name ?? 'System' }}</td>
                            <td class="py-1">{{ class_basename($a->subject_type) }} {{ $a->event }}</td>
                            <td class="py-1 text-xs">{{ implode(', ', array_keys($a->properties->get('attributes', $a->properties->get('old', [])))) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-2 sta-muted">No changes recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-sta.card>
</x-filament-panels::page>
