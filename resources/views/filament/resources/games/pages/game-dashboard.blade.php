{{-- Canvas Main.dc.html (game dashboard): phase cards, the focus phase KPIs and "Who has submitted". --}}
@use(App\Filament\Resources\Games\GameResource)
@use(App\Models\GamePhase)
@use(App\Support\Sta)
<x-filament-panels::page>
    @php
        $progress = $this->progress();
        $phases = $this->tablePhases();
        $focus = $this->focusPhase();
        $counts = $focus ? $progress->counts($focus->phase) : null;
        $rows = $this->rows();
        $tally = $this->tally();
        $canManage = $this->canManage();
        $canRemind = $this->canRemind() && $focus?->status === \App\Enums\PhaseStatus::Open;
        $pdfNames = [1 => 'Entry by Sport', 2 => 'Entry by Event', 3 => 'Long List', 4 => 'Short List'];
        $link = 'display: inline-flex; align-items: center; min-height: 44px; font-size: 13px; font-weight: 600';
        $linkBtn = $link.'; padding: 0; border: 0; background: transparent; color: #1F3A5F; cursor: pointer; font-family: Inter, sans-serif';
        $th = 'padding: 12px 12px; font-weight: 600';
        $footerScope = ['all' => '', 'pending' => ' pending', 'submitted' => ' submitted'][$show] ?? '';
    @endphp

    <section aria-label="Phases" class="sta-grid-phase">
        @foreach ($progress->game->phases->sortBy('phase') as $p)
            @php [$label, $pill] = Sta::phaseStatus($p); @endphp
            <x-sta.phase-card :phase="$p->phase" :name="GamePhase::NAMES[$p->phase]" :status="$pill" :status-label="$label"
                :meta="$this->phaseMeta($p)" :active="$focus?->is($p) && $p->status === \App\Enums\PhaseStatus::Open" />
        @endforeach
    </section>

    @if ($counts)
        <section aria-label="Phase {{ $focus->phase }} progress" class="sta-grid-kpi">
            <x-sta.kpi label="Submitted to STA" :value="$counts['submitted']" :hint="'of '.$counts['total'].' associations'" tone="submitted" />
            <x-sta.kpi label="Awaiting association admin" :value="$counts['awaiting']" hint="editor submitted, not approved" tone="awaiting" />
            <x-sta.kpi label="In draft" :value="$counts['draft']" hint="started, not submitted" tone="draft" />
            <x-sta.kpi label="Not started" :value="$counts['no_response']" hint="no entries yet" tone="none" />
            <x-sta.kpi label="Flags to review" :value="$this->flagCount()" hint="differences and warnings" tone="warn" />
        </section>
    @endif

    <section aria-label="Submission status by association" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; display: flex; flex-direction: column">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 20px; border-bottom: 1px solid #E2E8F0">
            <div style="display: flex; flex-direction: column; gap: 2px">
                <h2 style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 19px; font-weight: 600">Who has submitted</h2>
                @if ($description = $this->tableDescription())
                    <span style="font-size: 13px; color: #475569">{{ $description }}</span>
                @endif
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center">
                <div role="group" aria-label="Filter" class="sta-seg">
                    @foreach (['all' => 'All', 'pending' => 'Pending', 'submitted' => 'Submitted'] as $key => $label)
                        <button type="button" wire:click="$set('show', '{{ $key }}')" aria-pressed="{{ $show === $key ? 'true' : 'false' }}">{{ $label }} {{ $tally[$key] }}</button>
                    @endforeach
                </div>
                <label style="display: flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 12px; border: 1px solid #CBD5E1; border-radius: 10px; background: #FFFFFF; box-sizing: border-box">
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <span class="sta-sr-only">Search associations</span>
                    <input type="search" placeholder="Search associations" wire:model.live.debounce.300ms="search" style="border: 0; outline: none; box-shadow: none; padding: 0; font: 14px Inter, sans-serif; width: 180px; background: transparent">
                </label>
            </div>
        </div>
        <div style="overflow-x: auto">
            <table style="width: 100%; min-width: 760px; border-collapse: collapse">
                <thead>
                    <tr style="text-align: left; font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 0.05em">
                        <th scope="col" style="padding: 12px 20px; font-weight: 600">Association</th>
                        @foreach ($phases as $p)
                            <th scope="col" style="{{ $th }}"><x-sta.phase-chip :phase="$p->phase" /></th>
                        @endforeach
                        <th scope="col" style="{{ $th }}">Flags</th>
                        <th scope="col" style="{{ $th }}">Last activity</th>
                        <th scope="col" style="padding: 12px 20px; font-weight: 600; text-align: right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $a)
                        @php
                            $flags = $focus ? $progress->flags($a, $focus->phase) : null;
                            $last = $progress->lastActivity($a);
                            $code = $a->code ?? Sta::initials($a->name);
                            $focusSub = $focus ? $progress->submission($a, $focus->phase) : null;
                            $focusStatus = $focus ? $progress->status($a, $focus->phase) : null;
                            $remindable = $canRemind && ! in_array($focusStatus, ['submitted', 'awaiting'], true) && ! $progress->started($a, $focus->phase);
                        @endphp
                        <tr wire:key="row-{{ $a->id }}" style="border-top: 1px solid #EEF2F6">
                            <td style="padding: 14px 20px">
                                <div style="display: flex; align-items: center; gap: 12px">
                                    @if ($a->logo_path)
                                        <span style="display: inline-flex; width: 54px; height: 34px; flex-shrink: 0; box-sizing: border-box; padding: 3px; border-radius: 8px; background: #FFFFFF; border: 1px solid #E2E8F0; align-items: center; justify-content: center"><img src="{{ \App\Filament\Shared\AttachmentActions::url($a->logo_path) }}" alt="{{ $a->name }} logo" loading="lazy" style="width: 100%; height: 100%; object-fit: contain" /></span>
                                    @else
                                        <div aria-hidden="true" style="width: 34px; height: 34px; border-radius: 50%; background: {{ Sta::avatarColour($code) }}; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; flex-shrink: 0">{{ $code }}</div>
                                    @endif
                                    <div style="display: flex; flex-direction: column">
                                        <span style="font-weight: 600">{{ $a->name }}</span>
                                        <span style="font-size: 12px; color: #475569">{{ $this->sportsOf($a) ?: $code }}</span>
                                    </div>
                                </div>
                            </td>
                            @foreach ($phases as $p)
                                @php
                                    $c = $this->cell($a, $p->phase);
                                    $s = $c['submission'];
                                    $pdfs = $c['status'] === 'submitted' ? $this->pdfLinks($s) : [];
                                @endphp
                                <td wire:key="cell-{{ $a->id }}-{{ $p->phase }}" style="padding: 14px 12px">
                                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0 8px">
                                        <x-sta.pill :status="Sta::cellPill($c['status'])" :title="$c['status'] === 'submitted' ? $s->reference_no : null">{{ $c['label'] }}</x-sta.pill>
                                        @foreach ($pdfs as $pdf)
                                            <a href="{{ $pdf['url'] }}" target="_blank" rel="noopener" aria-label="View {{ $pdfNames[$p->phase] }} PDF{{ count($pdfs) > 1 ? ' ('.$pdf['name'].')' : '' }}" style="{{ $link }}">View PDF</a>
                                        @endforeach
                                        @if ($s && ! $focus?->is($p))
                                            <a href="{{ GameResource::getUrl('submission', ['record' => $record, 'submission' => $s]) }}" aria-label="View {{ $a->name }} Phase {{ $p->phase }}" style="{{ $link }}">View</a>
                                        @endif
                                        @if ($canManage)
                                            @if ($c['status'] !== 'submitted')
                                                <button type="button" wire:click="mountAction('extend', { association: {{ $a->id }}, phase: {{ $p->phase }} })" aria-label="Extend {{ $a->name }} Phase {{ $p->phase }}" style="{{ $linkBtn }}">Extend</button>
                                            @endif
                                            @if (in_array($c['status'], ['submitted', 'awaiting'], true))
                                                <button type="button" wire:click="mountAction('reopen', { association: {{ $a->id }}, phase: {{ $p->phase }} })" aria-label="Re-open {{ $a->name }} Phase {{ $p->phase }}" style="{{ $linkBtn }}; color: #991B1B">Re-open</button>
                                            @endif
                                        @endif
                                    </div>
                                    @if ($s?->deadline_override)
                                        <span style="font-size: 12px; color: #475569">Deadline {{ $s->deadline_override->timezone('Asia/Bahrain')->format('j M Y H:i') }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td style="padding: 14px 12px; font-size: 13px; color: {{ $flags ? '#9A3412' : '#475569' }}">{{ $flags ?? 'None' }}</td>
                            <td style="padding: 14px 12px; font-size: 13px; color: #475569">{{ $last ? Sta::when($last) : '—' }}</td>
                            <td style="padding: 14px 20px; text-align: right">
                                @if ($remindable)
                                    <button type="button" wire:click="mountAction('remindOne', { association: {{ $a->id }} })" aria-label="Remind {{ $a->name }}" style="{{ $linkBtn }}; font-size: 14px">Remind</button>
                                @elseif ($focusSub)
                                    <a href="{{ GameResource::getUrl('submission', ['record' => $record, 'submission' => $focusSub]) }}" aria-label="Open {{ $a->name }} Phase {{ $focus->phase }}" style="display: inline-flex; align-items: center; min-height: 44px; font-weight: 600">Open</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr style="border-top: 1px solid #EEF2F6"><td colspan="{{ $phases->count() + 4 }}" style="padding: 24px 20px; color: #475569">No associations match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding: 14px 20px; border-top: 1px solid #E2E8F0; font-size: 13px; color: #475569">Showing {{ $rows->count() }} of {{ $tally[$show] ?? $tally['all'] }}{{ $footerScope }} associations</div>
    </section>
</x-filament-panels::page>
