{{-- "General dashboard" tab of the admin home and the portal home (approved design). Registry figures count ACTIVE people only;
     the portal is scoped to its own association. People cards need People access (STA), game cards Submissions access (STA).
     No medical data is read or shown. Charts are inline SVG (donuts: stroke-dasharray arcs on a circle of circumference 100). --}}
@use(App\Enums\StaArea)
@use(App\Filament\Shared\AttachmentActions)
@use(App\Models\GamePhase)
@use(App\Support\GeneralDashboard)
@use(App\Support\Sta)
@php
    $portal = filament()->getCurrentPanel()?->getId() === 'portal';
    $d = $this->general();
    $showPeople = $portal || StaArea::People->can();
    $showGames = $portal || StaArea::Submissions->can();
    $people = $showPeople ? $d->people() : null;
    $registryUrl = $portal ? \App\Filament\Portal\Resources\People\PersonResource::getUrl() : ($showPeople ? \App\Filament\Resources\People\PersonResource::getUrl() : null);
    $card = 'background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px; padding: 20px; display: flex; flex-direction: column; gap: 14px; min-width: 0; box-sizing: border-box';
    $head = 'margin: 0; font-family: \'Space Grotesk\', sans-serif; font-size: 17px; font-weight: 600';
    $muted = 'font-size: 13px; color: var(--sta-fg-muted, #475569)';
    $select = 'min-height: 44px; padding: 0 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; font: 14px Inter, sans-serif; background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); max-width: 100%';
    $pct = fn (int $n, int $of) => $of ? (int) round($n / $of * 100) : 0;
    // A donut of [label, count, colour] segments with its legend.
    $donut = function (string $title, array $segments) use ($muted) {
        $total = array_sum(array_column($segments, 1));
        $arcs = '';
        $at = 0;
        foreach ($segments as [$label, $n, $colour]) {
            if ($total && $n) {
                $len = $n / $total * 100;
                $arcs .= '<circle cx="21" cy="21" r="15.9155" fill="none" stroke-width="6" style="stroke: '.$colour.'" stroke-dasharray="'.round($len, 3).' '.round(100 - $len, 3).'" stroke-dashoffset="'.round(25 - $at, 3).'"/>';
                $at += $len;
            }
        }
        $label = e($title.': '.collect($segments)->map(fn ($s) => $s[0].' '.$s[1])->implode(', '));
        $legend = collect($segments)->map(fn ($s) => '<li style="display: flex; align-items: center; gap: 8px; font-size: 13px"><span aria-hidden="true" style="width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; background: '.$s[2].'"></span><span style="flex: 1">'.e($s[0]).'</span><strong style="font-weight: 600">'.number_format($s[1]).'</strong></li>')->implode('');

        return '<div style="display: flex; flex-wrap: wrap; align-items: center; gap: 16px"><svg role="img" aria-label="'.$label.'" width="120" height="120" viewBox="0 0 42 42" style="flex-shrink: 0">'
            .'<circle cx="21" cy="21" r="15.9155" fill="none" stroke-width="6" style="stroke: var(--sta-bg-line, #E2E8F0)"/>'.$arcs
            .'<text x="21" y="23.5" text-anchor="middle" style="font: 700 8px \'Space Grotesk\', sans-serif; fill: var(--sta-fg-text, #0F172A)">'.number_format($total).'</text></svg>'
            .'<ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; flex: 1 1 120px; min-width: 0">'.$legend.'</ul></div>';
    };
@endphp
<div style="display: flex; flex-direction: column; gap: 20px">

    {{-- Filters --}}
    <div style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 16px">
        @unless ($portal)
            <label class="sta-field">Association
                <select wire:model.live="genAssociation" style="{{ $select }}">
                    <option value="">All associations</option>
                    @foreach (\App\Models\Association::query()->orderBy('name')->pluck('name', 'id') as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        @endunless
        <label class="sta-field">Type
            <select wire:model.live="genType" style="{{ $select }}">
                <option value="all">Athletes and officials</option>
                <option value="athlete">Athletes</option>
                <option value="official">Team officials</option>
            </select>
        </label>
        <span style="{{ $muted }}; padding-bottom: 12px">Counts include active people only.</span>
    </div>

    @if ($people)
        @php
            $cluster = $d->cluster();
            $more = max(0, $people['total'] - $cluster->count());
            // Bubble collage slots: [size, left, top] in a 250 × 210 box; the 9th slot is the "+N" bubble.
            $slots = [[78, 80, 58], [52, 22, 14], [60, 150, 8], [60, 162, 100], [76, 0, 92], [62, 92, 142], [54, 90, 0], [46, 204, 54]];
        @endphp
        <div style="display: flex; flex-wrap: wrap; gap: 20px">
            <section aria-labelledby="gen-registry" style="{{ $card }}; flex: 2 1 480px; flex-direction: row; flex-wrap: wrap; align-items: center; gap: 24px">
                <div style="position: relative; width: 250px; height: 210px; flex-shrink: 0; max-width: 100%">
                    @foreach ($cluster as $i => $p)
                        @php [$size, $left, $top] = $slots[$i]; $name = $p->fullName(); @endphp
                        <span title="{{ $name }}" style="position: absolute; left: {{ $left }}px; top: {{ $top }}px; width: {{ $size }}px; height: {{ $size }}px; border-radius: 50%; border: 3px solid var(--sta-bg-surface, #FFFFFF); box-sizing: border-box; overflow: hidden; display: flex; align-items: center; justify-content: center; background: {{ Sta::avatarColour($name) }}; color: var(--sta-fg-on-brand, #FFFFFF); font: 700 {{ (int) round($size / 3) }}px 'Space Grotesk', sans-serif">
                            <span @if (! $p->photo_path) role="img" aria-label="{{ $name }}" @else aria-hidden="true" @endif>{{ Sta::initials($name) }}</span>
                            @if ($p->photo_path)
                                {{-- A missing file falls back to the initials underneath. --}}
                                <img src="{{ AttachmentActions::url($p->photo_path) }}" alt="Photo of {{ $name }}" loading="lazy" onerror="this.remove()" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover">
                            @endif
                        </span>
                    @endforeach
                    @if ($more)
                        <a href="{{ $registryUrl }}" aria-label="{{ $more }} more {{ str('person')->plural($more) }} in the registry" style="position: absolute; left: 178px; top: 160px; width: 46px; height: 46px; border-radius: 50%; border: 3px solid var(--sta-bg-surface, #FFFFFF); box-sizing: border-box; background: var(--sta-bg-selected, #E8EEF6); color: var(--sta-fg-brand, #1F3A5F); display: flex; align-items: center; justify-content: center; font: 700 13px Inter, sans-serif; text-decoration: none">+{{ $more }}</a>
                    @endif
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px; min-width: 0">
                    <h2 id="gen-registry" style="margin: 0; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--sta-fg-muted, #475569)">Registry</h2>
                    <span style="font-family: 'Space Grotesk', sans-serif; font-size: 44px; font-weight: 700; line-height: 1">{{ number_format($people['total']) }}</span>
                    <span style="font-weight: 600">{{ $people['total'] === 1 ? 'person' : 'people' }}</span>
                    <span style="{{ $muted }}">{{ number_format($people['athletes']) }} {{ str('athlete')->plural($people['athletes']) }} · {{ number_format($people['officials']) }} {{ str('official')->plural($people['officials']) }}</span>
                    <span style="{{ $muted }}">{{ number_format($people['complete']) }} complete · {{ $pct($people['complete'], $people['total']) }}%</span>
                </div>
            </section>

            <section aria-labelledby="gen-gender" style="{{ $card }}; flex: 1 1 260px">
                <h2 id="gen-gender" style="{{ $head }}">Gender</h2>
                {!! $donut('Gender', [['Male', $people['male'], 'var(--sta-bg-blue-600, #2563EB)'], ['Female', $people['female'], 'var(--sta-bg-rose-600, #E11D48)']]) !!}
            </section>

            <section aria-labelledby="gen-ages" style="{{ $card }}; flex: 1 1 260px">
                <h2 id="gen-ages" style="{{ $head }}">Age groups</h2>
                @php
                    $ramp = ['var(--sta-bd-blue-200, #BFDBFE)', 'var(--sta-bg-blue-500, #3B82F6)', 'var(--sta-bg-blue-600, #2563EB)', 'var(--sta-bg-avatar, #2B4C7E)', 'var(--sta-bg-brand, #1F3A5F)'];
                    $ages = collect(GeneralDashboard::AGE_GROUPS)->values()->map(fn ($label, $i) => [$label === 'Under 18' ? '<18' : $label, array_values($people['ages'])[$i], $ramp[$i]])->all();
                    if ($people['unknown']) {
                        $ages[] = ['Unknown', $people['unknown'], 'var(--sta-bg-faint, #94A3B8)'];
                    }
                @endphp
                {!! $donut('Age groups', $ages) !!}
            </section>
        </div>

        {{-- KPI row --}}
        @php $expiringUrl = $portal ? $registryUrl : \App\Filament\Resources\People\PersonResource::getUrl('index', ['filters' => ['expiring' => ['isActive' => true]]]); @endphp
        <div class="sta-grid-kpi">
            <x-sta.kpi label="Athletes" :value="number_format($people['athletes'])" />
            <x-sta.kpi label="Team officials" :value="number_format($people['officials'])" />
            <x-sta.kpi label="Complete profiles" :value="$pct($people['complete'], $people['total']).'%'" :hint="$people['complete'].' of '.$people['total']" />
            <a href="{{ $expiringUrl }}" class="sta-kpi" style="text-decoration: none; color: inherit">
                <span class="sta-kpi-label">Passports expiring within 6 months</span>
                <span class="sta-kpi-value" style="{{ $people['expiring'] ? 'color: var(--sta-fg-orange-800, #9A3412)' : '' }}">{{ number_format($people['expiring']) }}</span>
                <span class="sta-kpi-hint">{{ $portal ? 'Open the registry' : 'Open the filtered list' }}</span>
            </a>
        </div>
    @endif

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(340px, 100%), 1fr)); gap: 20px; align-items: start">
        @if ($people)
            @php $nat = $d->nationalities(); $natMax = max(1, $nat['top']->max() ?? 1); @endphp
            <section aria-labelledby="gen-nat" style="{{ $card }}">
                <h2 id="gen-nat" style="{{ $head }}">Nationalities</h2>
                <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px">
                    @forelse ($nat['top'] as $country => $n)
                        <li style="display: grid; grid-template-columns: minmax(0, 1fr) 48px; gap: 4px 10px; font-size: 13px">
                            <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ $country }}</span>
                            <strong style="text-align: right; font-weight: 600">{{ $n }}</strong>
                            <span aria-hidden="true" style="grid-column: 1 / -1; height: 6px; border-radius: 999px; background: var(--sta-bg-subtle, #F1F5F9); overflow: hidden"><span style="display: block; height: 100%; width: {{ round($n / $natMax * 100) }}%; background: var(--sta-bg-teal-700, #0F766E)"></span></span>
                        </li>
                    @empty
                        <li style="{{ $muted }}">No nationalities recorded.</li>
                    @endforelse
                </ul>
                @if ($nat['other'])
                    <span style="{{ $muted }}">Other ({{ $nat['other'] }})</span>
                @endif
            </section>
        @endif

        @if ($showGames)
            @php
                $games = $d->games();
                $game = $d->game();
                $bySport = $d->athletesBySport();
                $sportMax = max(1, $bySport->max() ?? 1);
            @endphp
            <section aria-labelledby="gen-sport" style="{{ $card }}">
                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px">
                    <h2 id="gen-sport" style="{{ $head }}">Athletes by sport</h2>
                    @if ($games->isNotEmpty())
                        <label style="display: block; max-width: 100%"><span class="sta-sr-only">Game</span>
                            <select wire:model.live="genGame" style="{{ $select }}">
                                @foreach ($games as $g)
                                    <option value="{{ $g->id }}" @selected($g->id === $game?->id)>{{ $g->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                </div>
                @if (! $game)
                    <p style="margin: 0; {{ $muted }}">No active game.</p>
                @else
                    <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px">
                        @forelse ($bySport as $sport => $n)
                            <li style="display: grid; grid-template-columns: minmax(80px, 30%) minmax(0, 1fr) 40px; align-items: center; gap: 10px; font-size: 13px">
                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ $sport }}</span>
                                <span aria-hidden="true" style="height: 14px; border-radius: 4px; background: var(--sta-bg-subtle, #F1F5F9); overflow: hidden"><span style="display: block; height: 100%; width: {{ round($n / $sportMax * 100) }}%; background: var(--sta-bg-brand, #1F3A5F)"></span></span>
                                <strong style="text-align: right; font-weight: 600">{{ $n }}</strong>
                            </li>
                        @empty
                            <li style="{{ $muted }}">No athletes on the Long List yet.</li>
                        @endforelse
                    </ul>
                    <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">Distinct athletes entered per sport on the Phase 3 Long List (draft, awaiting or submitted).</span>
                @endif
            </section>

            <section aria-labelledby="gen-progress" style="{{ $card }}">
                <h2 id="gen-progress" style="{{ $head }}">{{ $game ? $game->name : 'Active game' }}</h2>
                @if ($portal)
                    {{-- The association's own status in each open phase of the chosen game. --}}
                    @php $rows = $game ? ($d->entries()->firstWhere('game.id', $game->id)['rows'] ?? collect())->where('status', 'Open') : collect(); @endphp
                    @forelse ($rows as $row)
                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px">
                            <x-sta.phase-chip :phase="$row['phase']" :label="'Phase '.$row['phase'].' · '.$row['name']" />
                            <x-sta.pill :status="$row['pill']">{{ $row['submission'] }}</x-sta.pill>
                            <span style="{{ $muted }}">{{ $row['meta'] }}</span>
                            @if ($row['url'])<a href="{{ $row['url'] }}" style="display: inline-flex; align-items: center; min-height: 44px; font-weight: 600">Open</a>@endif
                        </div>
                    @empty
                        <p style="margin: 0; {{ $muted }}">No phase is open.</p>
                    @endforelse
                    @if ($game && ($phase = $rows->last()))
                        @php $remarks = $d->pendingRemarks($game, $phase['phase']); @endphp
                        <span style="{{ $muted }}">{{ $remarks }} {{ str('remark')->plural($remarks) }} from STA awaiting your reply</span>
                    @endif
                @elseif ($progress = $d->progress())
                    @php $phase = $progress['phase']; $c = $progress['counts']; @endphp
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px">
                        <x-sta.phase-chip :phase="$phase->phase" :label="'Phase '.$phase->phase.' · '.GamePhase::NAMES[$phase->phase]" />
                        @if ($phase->deadline)
                            <span style="{{ $muted }}">Deadline {{ Sta::date($phase->deadline->timezone('Asia/Bahrain')) }} · <strong style="color: var(--sta-fg-orange-800, #9A3412)">{{ Sta::daysLeft($phase->deadline) }}</strong></span>
                        @endif
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 10px">
                        @foreach (['Submitted' => $c['submitted'], 'Awaiting admin' => $c['awaiting'], 'Draft' => $c['draft'], 'Not started' => $c['no_response']] as $label => $n)
                            <div style="padding: 10px 12px; border-radius: 10px; background: var(--sta-bg-surface-alt, #F8FAFC); display: flex; flex-direction: column; gap: 2px">
                                <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ $label }}</span>
                                <span style="font-family: 'Space Grotesk', sans-serif; font-size: 22px; font-weight: 700">{{ $n }}</span>
                            </div>
                        @endforeach
                    </div>
                    <span style="{{ $muted }}">{{ $progress['remarks'] }} pending {{ str('remark')->plural($progress['remarks']) }}@if ($progress['flagged'] !== null) · {{ $progress['flagged'] }} {{ str('association')->plural($progress['flagged']) }} flagged ({{ $phase->phase === 3 ? 'differs from Phase 2' : 'over a maximum' }})@endif</span>
                    <a href="{{ \App\Filament\Resources\Games\GameResource::getUrl('dashboard', ['record' => $game]) }}" style="display: inline-flex; align-items: center; min-height: 44px; font-weight: 600">Open game dashboard</a>
                @else
                    <p style="margin: 0; {{ $muted }}">{{ $game ? 'No phase is open.' : 'No active game.' }}</p>
                @endif
            </section>
        @endif

        @if ($portal)
            <section aria-labelledby="gen-entries" style="{{ $card }}">
                <h2 id="gen-entries" style="{{ $head }}">Your entries</h2>
                @forelse ($d->entries() as $item)
                    @php $current = $item['rows']->where('status', 'Open')->last() ?? $item['rows']->whereNotNull('url')->last(); @endphp
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; padding-top: 10px; border-top: 1px solid var(--sta-bd-row, #EEF2F6)">
                        <span style="font-weight: 600; flex: 1 1 160px">{{ $item['game']->name }}</span>
                        @if ($current)
                            <x-sta.phase-chip :phase="$current['phase']" :label="'Phase '.$current['phase'].' · '.$current['name']" />
                            <x-sta.pill :status="$current['pill']">{{ $current['submission'] }}</x-sta.pill>
                        @else
                            <span style="{{ $muted }}">Not opened</span>
                        @endif
                    </div>
                @empty
                    <p style="margin: 0; {{ $muted }}">No active games.</p>
                @endforelse
            </section>
        @elseif ($people)
            @php $assocMax = max(1, $people['perAssociation']->max('total') ?? 1); @endphp
            <section aria-labelledby="gen-assoc" style="{{ $card }}">
                <h2 id="gen-assoc" style="{{ $head }}">Registry by association</h2>
                <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px">
                    @forelse ($people['perAssociation'] as $a)
                        @php $incomplete = $a['total'] - $a['complete']; @endphp
                        <li style="display: grid; grid-template-columns: 52px minmax(0, 1fr) 72px; align-items: center; gap: 10px; font-size: 13px">
                            <span title="{{ $a['name'] }}" style="font-weight: 700; color: var(--sta-fg-brand, #1F3A5F)">{{ $a['code'] }}</span>
                            <span role="img" aria-label="{{ $a['code'] }}: {{ $a['complete'] }} complete, {{ $incomplete }} incomplete" style="height: 14px; border-radius: 4px; background: var(--sta-bg-subtle, #F1F5F9); overflow: hidden">
                                <span style="width: {{ round($a['total'] / $assocMax * 100) }}%; height: 100%; display: flex">
                                    <span style="flex: {{ $a['complete'] }} 1 0; background: var(--sta-bg-brand, #1F3A5F)"></span>
                                    <span style="flex: {{ $incomplete }} 1 0; background: var(--sta-bg-orange-300, #FDBA74)"></span>
                                </span>
                            </span>
                            <span style="text-align: right; color: var(--sta-fg-strong, #334155)">{{ $a['complete'] }} / {{ $a['total'] }}</span>
                        </li>
                    @empty
                        <li style="{{ $muted }}">No people yet.</li>
                    @endforelse
                </ul>
            </section>
        @endif

        @if ($people)
            @php $expiring = $d->expiring(); @endphp
            <section aria-labelledby="gen-expiring" style="{{ $card }}">
                <h2 id="gen-expiring" style="{{ $head }}">Passports expiring soon</h2>
                <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column">
                    @forelse ($expiring as $p)
                        <li style="display: flex; flex-wrap: wrap; align-items: center; gap: 4px 12px; min-height: 44px; padding: 6px 0{{ $loop->first ? '' : '; border-top: 1px solid var(--sta-bd-row, #EEF2F6)' }}">
                            <span style="flex: 1 1 160px; min-width: 0; font-weight: 600"><x-sta.person-link :person="$p" /></span>
                            @unless ($portal)<span style="font-size: 12px; font-weight: 700; color: var(--sta-fg-brand, #1F3A5F)">{{ $p->association?->code }}</span>@endunless
                            <span style="font-weight: 600; color: {{ $p->passport_expiry->lt(now()->addMonths(2)) ? 'var(--sta-fg-red-700, #B91C1C)' : 'var(--sta-fg-amber-800, #92400E)' }}">{{ Sta::date($p->passport_expiry) }}</span>
                        </li>
                    @empty
                        <li style="{{ $muted }}">No passport expires within 6 months.</li>
                    @endforelse
                </ul>
            </section>
        @endif
    </div>
</div>
