{{-- Canvas AdminDashboard.dc.html (admin home): Active games, Needs your attention, Registry health, Associations, Recent activity.
     A band the user may not see is left out. --}}
@use(App\Enums\StaPermission)
@use(App\Filament\Resources\Associations\AssociationResource)
@use(App\Filament\Resources\Games\GameResource)
@use(App\Filament\Resources\People\PersonResource)
@use(App\Models\GamePhase)
@use(App\Support\Sta)
@php
    $games = $this->activeGames();
    $showGames = $this->allowed(StaPermission::ViewSubmissions);
    $showAssocs = $this->allowed(StaPermission::ManageAssociations);
    $showPeople = $this->allowed(StaPermission::ManagePeople);
    $attention = ($showGames || $showAssocs) ? $this->attention() : null;
    $registry = $this->registry();
    $phaseColours = [1 => ['#E0F2FE', '#075985', '#0369A1'], 2 => ['#CCFBF1', '#115E59', '#0F766E'], 3 => ['#FEF3C7', '#92400E', '#B45309'], 4 => ['#EDE9FE', '#5B21B6', '#6D28D9']];
    $chip = fn (int $p, string $label) => '<span style="display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: '.$phaseColours[$p][0].'; color: '.$phaseColours[$p][1].'; white-space: nowrap"><span aria-hidden="true" style="width: 8px; height: 8px; border-radius: 50%; background: '.$phaseColours[$p][2].'; flex-shrink: 0"></span>'.e($label).'</span>';
    $pill = fn (string $bg, string $fg, ?string $ring = null) => 'display: inline-flex; align-items: center; height: 24px; padding: 0 9px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; background: '.$bg.'; color: '.$fg.($ring ? '; box-shadow: inset 0 0 0 1px '.$ring : '');
    $adminPills = ['active' => $pill('#DCFCE7', '#166534'), 'pending' => $pill('#FEF3C7', '#92400E'), 'none' => $pill('#FFFFFF', '#9A3412', '#FDBA74')];
    $icons = [
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'mail' => '<path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/>',
        'flag' => '<path d="M5 21V4"/><path d="M5 4h11l-2 4 2 4H5"/>',
        'invite' => '<circle cx="9" cy="8" r="4"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M19 8v6"/><path d="M16 11h6"/>',
    ];
    $bandHead = 'margin: 0; font-family: \'Space Grotesk\', sans-serif; font-size: 19px; font-weight: 600; display: flex; align-items: center; gap: 10px';
    $bandIcon = 'width: 32px; height: 32px; border-radius: 9px; color: #FFFFFF; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0';
    $linkStyle = 'display: inline-flex; align-items: center; min-height: 44px; font-weight: 600';
@endphp
<x-filament-panels::page>

    @if ($showGames)
        <section aria-labelledby="games-title" style="display: flex; flex-direction: column; gap: 12px">
            <div style="display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 12px; background: #EFF6FF"><span aria-hidden="true" style="{{ $bandIcon }}; background: #2563EB"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18"/><path d="M8 3v4"/><path d="M16 3v4"/></svg></span><h2 id="games-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 19px; font-weight: 600; color: #1E40AF">Active games</h2></div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(380px, 100%), 1fr)); gap: 16px">
                @forelse ($games as $g)
                    @php
                        $game = $g['game'];
                        $phase = $g['phase'];
                        $c = $g['counts'];
                        $pct = $c && $c['total'] ? (int) round($c['submitted'] / $c['total'] * 100) : 0;
                        $breakdown = $c ? collect([
                            $c['awaiting'] ? $c['awaiting'].' awaiting association admin' : null,
                            $c['draft'] ? $c['draft'].' in draft' : null,
                            $c['no_response'] ? $c['no_response'].' not started' : null,
                        ])->filter()->implode(' · ') : '';
                        $meta = collect([
                            $game->start_date ? $game->start_date->format('j M').' – '.$game->end_date?->format('j M Y') : null,
                            $game->associations->count().' '.str('association')->plural($game->associations->count()),
                            $game->game_sports_count.' '.str('sport')->plural($game->game_sports_count),
                        ])->filter()->implode(' · ');
                    @endphp
                    <a href="{{ GameResource::getUrl('dashboard', ['record' => $game]) }}" wire:key="game-card-{{ $game->id }}" style="background: #FFFFFF; border: 1px solid {{ $loop->first ? '#1F3A5F; box-shadow: 0 0 0 3px #DCE6F2' : '#E2E8F0' }}; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; gap: 14px; text-decoration: none; color: #0F172A">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px">
                            <div style="display: flex; flex-direction: column; gap: 4px; min-width: 0">
                                <span style="font-family: 'Space Grotesk', sans-serif; font-weight: 600; font-size: 18px">{{ $game->name }}</span>
                                <span style="font-size: 13px; color: #475569">{{ $meta }}</span>
                            </div>
                            @if ($phase)
                                {!! $chip($phase->phase, 'Phase '.$phase->phase.' · '.GamePhase::NAMES[$phase->phase]) !!}
                            @endif
                        </div>
                        @if (($phase && $phase->deadline) || $g['closed'])
                            <div style="display: flex; flex-wrap: wrap; gap: 8px 24px; align-items: baseline">
                                @if ($phase && $phase->deadline)
                                    <div style="display: flex; flex-direction: column; gap: 2px">
                                        <span style="font-size: 12px; color: #475569">Deadline</span>
                                        <span style="font-weight: 600">{{ $phase->deadline->timezone('Asia/Bahrain')->format('D j M Y') }}</span>
                                    </div>
                                    <div style="display: flex; flex-direction: column; gap: 2px">
                                        <span style="font-size: 12px; color: #475569">Time left</span>
                                        <span style="font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 20px; color: #9A3412">{{ str_replace(' left', '', Sta::daysLeft($phase->deadline)) }}</span>
                                    </div>
                                @endif
                                @if ($closed = $g['closed'])
                                    <div style="display: flex; flex-direction: column; gap: 2px">
                                        {!! $chip($closed->phase, 'Phase '.$closed->phase) !!}
                                        <span style="font-weight: 600">Closed{{ $closed->deadline ? ' '.$closed->deadline->timezone('Asia/Bahrain')->format('j M') : '' }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                        @if ($c)
                            <div style="display: flex; flex-direction: column; gap: 6px">
                                <div style="display: flex; justify-content: space-between; font-size: 13px">
                                    <span style="font-weight: 600">Associations submitted</span>
                                    <span style="color: #334155">{{ $c['submitted'] }} of {{ $c['total'] }}</span>
                                </div>
                                <div style="height: 8px; border-radius: 999px; background: #E2E8F0; overflow: hidden">
                                    <div style="width: {{ $pct }}%; height: 100%; background: #1F3A5F"></div>
                                </div>
                                @if ($breakdown !== '')
                                    <span style="font-size: 12px; color: #475569">{{ $breakdown }}</span>
                                @endif
                            </div>
                        @else
                            <span style="font-size: 13px; color: #475569">No phase is open.</span>
                        @endif
                        <span style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: #1F3A5F">Open game dashboard
                            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                        </span>
                    </a>
                @empty
                    <p style="margin: 0; color: #475569">No active games.</p>
                @endforelse
            </div>
        </section>
    @endif

    @if ($attention !== null || $registry)
        <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start">

            @if ($attention !== null)
                @php
                    $n = $games->count();
                    $scope = match (true) { ! $showGames => 'Across all associations', $n === 1 => 'Across the active game', $n === 2 => 'Across both active games', default => "Across all {$n} active games" };
                @endphp
                <section aria-labelledby="attn-title" style="flex: 999 1 520px; min-width: 0; background: #FFFDF5; border: 1px solid #FDE68A; border-radius: 14px; display: flex; flex-direction: column">
                    <div style="padding: 18px 20px; border-bottom: 1px solid #FDE68A; background: #FFFBEB; border-radius: 13px 13px 0 0; display: flex; flex-direction: column; gap: 2px">
                        <h2 id="attn-title" style="{{ $bandHead }}; color: #92400E"><span aria-hidden="true" style="{{ $bandIcon }}; background: #D97706"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 16v-5a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/></svg></span>Needs your attention</h2>
                        <span style="font-size: 13px; color: #475569">{{ $scope }} · next 7 days</span>
                    </div>
                    <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column">
                        @forelse ($attention as $item)
                            <li style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px 14px; padding: 14px 20px{{ $loop->first ? '' : '; border-top: 1px solid #EEF2F6' }}">
                                <div style="width: 36px; height: 36px; border-radius: 10px; background: {{ $item['tone'] === 'warn' ? '#FFEDD5' : '#F1F5F9' }}; color: {{ $item['tone'] === 'warn' ? '#9A3412' : '#334155' }}; display: flex; align-items: center; justify-content: center; flex-shrink: 0">
                                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$item['icon']] !!}</svg>
                                </div>
                                <div style="flex: 1 1 260px; min-width: 0; display: flex; flex-direction: column; gap: 2px">
                                    <span style="font-weight: 600">{{ $item['title'] }}</span>
                                    <span style="font-size: 13px; color: #475569">@if ($item['pill'])<span style="display: inline-flex; align-items: center; height: 22px; padding: 0 8px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #FFFFFF; color: #9A3412; box-shadow: inset 0 0 0 1px #FDBA74">{{ $item['pill'] }}</span> @endif{{ $item['text'] }}</span>
                                </div>
                                @if ($item['remind'])
                                    <button type="button" wire:click="mountAction('remind', { game: {{ $item['remind'] }} })" style="min-height: 44px; padding: 0 14px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 13px Inter, sans-serif; cursor: pointer">{{ $item['link'] }}</button>
                                @elseif ($item['url'])
                                    <a href="{{ $item['url'] }}" style="{{ $linkStyle }}">{{ $item['link'] }}</a>
                                @endif
                            </li>
                        @empty
                            <li style="padding: 14px 20px; color: #475569">Nothing needs your attention.</li>
                        @endforelse
                    </ul>
                </section>
            @endif

            @if ($registry)
                @php $max = max(1, $registry['top']->max('total') ?? 1); @endphp
                <section aria-labelledby="health-title" style="flex: 1 1 380px; min-width: 0; background: #F7FEFC; border: 1px solid #99F6E4; border-radius: 14px; display: flex; flex-direction: column">
                    <div style="padding: 18px 20px; border-bottom: 1px solid #99F6E4; background: #F0FDFA; border-radius: 13px 13px 0 0; display: flex; flex-direction: column; gap: 2px">
                        <h2 id="health-title" style="{{ $bandHead }}; color: #115E59"><span aria-hidden="true" style="{{ $bandIcon }}; background: #115E59"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3-7 4 14 3-7h4"/></svg></span>Registry health</h2>
                        <span style="font-size: 13px; color: #475569">Permanent registry of all associations</span>
                    </div>
                    <div style="padding: 16px 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(min(140px, 100%), 1fr)); gap: 14px">
                        @foreach ([['Athletes', $registry['athletes'], null], ['Team officials', $registry['officials'], null], ['Complete', $registry['complete'], '#1F3A5F'], ['Incomplete', $registry['incomplete'], '#9A3412']] as [$label, $value, $colour])
                            <div style="display: flex; flex-direction: column; gap: 2px">
                                <span style="font-size: 12px; color: #475569">{{ $label }}</span>
                                <span style="font-family: 'Space Grotesk', sans-serif; font-size: 24px; font-weight: 700{{ $colour ? '; color: '.$colour : '' }}">{{ number_format($value) }}</span>
                            </div>
                        @endforeach
                    </div>
                    @if ($registry['expiring'])
                        <div style="margin: 0 20px; display: flex; align-items: center; gap: 10px; min-height: 44px; padding: 0 12px; border-radius: 10px; background: #FFF7ED; color: #9A3412; font-size: 13px; font-weight: 600">
                            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><circle cx="12" cy="10" r="3"/><path d="M9 17h6"/></svg>
                            <a href="{{ PersonResource::getUrl() }}" style="color: #9A3412">{{ $registry['expiring'] }} {{ str('passport')->plural($registry['expiring']) }} {{ $registry['expiring'] === 1 ? 'expires' : 'expire' }} within 6 months</a>
                        </div>
                    @endif
                    <div style="padding: 16px 20px 6px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px">
                        <h3 style="margin: 0; font-size: 13px; font-weight: 600">By association</h3>
                        <div style="display: flex; gap: 14px; font-size: 12px; color: #334155">
                            <span style="display: inline-flex; align-items: center; gap: 6px"><span aria-hidden="true" style="width: 10px; height: 10px; border-radius: 3px; background: #1F3A5F"></span>Complete</span>
                            <span style="display: inline-flex; align-items: center; gap: 6px"><span aria-hidden="true" style="width: 10px; height: 10px; border-radius: 3px; background: #FDBA74"></span>Incomplete</span>
                        </div>
                    </div>
                    <ul style="list-style: none; margin: 0; padding: 4px 20px 16px; display: flex; flex-direction: column; gap: 10px">
                        @foreach ($registry['top'] as $h)
                            @php $incomplete = $h['total'] - $h['complete']; @endphp
                            <li style="display: grid; grid-template-columns: 44px minmax(0, 1fr) 72px; align-items: center; gap: 10px; font-size: 13px">
                                <span style="font-weight: 700; color: #1F3A5F">{{ $h['code'] }}</span>
                                <div role="img" aria-label="{{ $h['code'] }}: {{ $h['complete'] }} complete, {{ $incomplete }} incomplete" style="height: 14px; border-radius: 4px; background: #F1F5F9; overflow: hidden">
                                    <div style="width: {{ round($h['total'] / $max * 100) }}%; height: 100%; display: flex">
                                        <div style="flex: {{ $h['complete'] }} 1 0; background: #1F3A5F"></div>
                                        <div style="flex: {{ $incomplete }} 1 0; background: #FDBA74"></div>
                                    </div>
                                </div>
                                <span style="text-align: right; color: #334155">{{ $h['complete'] }} / {{ $h['total'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p style="margin: 0; padding: 12px 20px 16px; border-top: 1px solid #E2E8F0; font-size: 12px; color: #475569; line-height: 1.5">Incomplete = missing any Long List field: full name, photo, passport scan, issuing country or issue date, CPR number.</p>
                </section>
            @endif
        </div>
    @endif

    @if ($showAssocs)
        @php
            $rows = $this->associations();
            $totals = $this->associationTotals();
        @endphp
        <section aria-labelledby="assoc-title" style="background: #F7F8FF; border: 1px solid #C7D2FE; border-radius: 14px; display: flex; flex-direction: column">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 20px; border-bottom: 1px solid #C7D2FE; background: #EEF2FF; border-radius: 13px 13px 0 0">
                <div style="display: flex; flex-direction: column; gap: 2px">
                    <h2 id="assoc-title" style="{{ $bandHead }}; color: #3730A3"><span aria-hidden="true" style="{{ $bandIcon }}; background: #4338CA"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 14.2A5 5 0 0 1 21 19"/></svg></span>Associations</h2>
                    <span style="font-size: 13px; color: #475569">{{ $totals['total'] }} {{ str('association')->plural($totals['total']) }} · {{ $totals['active'] }} active · each has 1 admin and up to 2 editors</span>
                </div>
                <a href="{{ AssociationResource::getUrl() }}" style="min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; text-decoration: none">All associations</a>
            </div>
            <div style="overflow-x: auto">
                <table style="width: 100%; min-width: {{ $showPeople ? 900 : 640 }}px; border-collapse: collapse">
                    <thead>
                        <tr style="text-align: left; font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 0.05em">
                            <th scope="col" style="padding: 12px 20px; font-weight: 600">Association</th>
                            <th scope="col" style="padding: 12px; font-weight: 600">Code</th>
                            <th scope="col" style="padding: 12px; font-weight: 600">Admin</th>
                            <th scope="col" style="padding: 12px; font-weight: 600">Editors</th>
                            @if ($showPeople)
                                <th scope="col" style="padding: 12px; font-weight: 600">People</th>
                                <th scope="col" style="padding: 12px; font-weight: 600">Complete</th>
                                <th scope="col" style="padding: 12px; font-weight: 600">Last activity</th>
                            @endif
                            <th scope="col" style="padding: 12px 20px; font-weight: 600">Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            @php
                                $a = $r['association'];
                                $code = $a->code ?? Sta::initials($a->name);
                                $pct = $r['people'] ? $r['pct'] : null;
                                $low = $pct !== null && $pct < 80;
                            @endphp
                            <tr wire:key="home-assoc-{{ $a->id }}" style="border-top: 1px solid #EEF2F6">
                                <td style="padding: 8px 20px">
                                    <a href="{{ AssociationResource::getUrl('edit', ['record' => $a]) }}" style="display: flex; align-items: center; gap: 12px; min-height: 44px; text-decoration: none; color: #0F172A">
                                        @if ($a->logo_path)
                                            <span style="display: inline-flex; width: 54px; height: 34px; flex-shrink: 0; box-sizing: border-box; padding: 3px; border-radius: 8px; background: #FFFFFF; border: 1px solid #E2E8F0; align-items: center; justify-content: center"><img src="{{ \App\Filament\Shared\AttachmentActions::url($a->logo_path) }}" alt="{{ $a->name }} logo" loading="lazy" style="width: 100%; height: 100%; object-fit: contain" /></span>
                                        @else
                                            <span aria-hidden="true" style="width: 34px; height: 34px; border-radius: 50%; background: {{ Sta::avatarColour($code) }}; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; flex-shrink: 0">{{ $code }}</span>
                                        @endif
                                        <span style="font-weight: 600">{{ $a->name }}</span>
                                    </a>
                                </td>
                                <td style="padding: 8px 12px; color: #334155; font-weight: 600">{{ $a->code ?? '—' }}</td>
                                <td style="padding: 8px 12px"><span style="{{ $adminPills[$r['state']] }}">{{ $r['admin'] }}</span></td>
                                <td style="padding: 8px 12px; color: #334155">{{ $r['editors'] }} / 2</td>
                                @if ($showPeople)
                                    <td style="padding: 8px 12px; color: #334155">{{ $r['people'] }}</td>
                                    <td style="padding: 8px 12px">
                                        <div style="display: flex; align-items: center; gap: 8px">
                                            <div aria-hidden="true" style="width: 64px; height: 6px; border-radius: 999px; background: #E2E8F0; overflow: hidden">
                                                <div style="height: 100%; width: {{ $pct ?? 0 }}%; background: {{ $low ? '#EA580C' : '#1F3A5F' }}"></div>
                                            </div>
                                            <span style="font-weight: 600; color: {{ $pct === null ? '#475569' : ($low ? '#9A3412' : '#0F172A') }}">{{ $pct === null ? '—' : $pct.'%' }}</span>
                                        </div>
                                    </td>
                                    <td style="padding: 8px 12px; font-size: 13px; color: #475569">{{ $r['last'] ? Sta::when($r['last']) : '—' }}</td>
                                @endif
                                <td style="padding: 8px 20px">
                                    <div style="display: flex; align-items: center; gap: 8px">
                                        <button type="button" role="switch" aria-checked="{{ $a->is_active ? 'true' : 'false' }}" aria-label="Association {{ $code }} active" wire:click="toggleActive({{ $a->id }})" style="width: 52px; height: 44px; padding: 0; border: 0; background: transparent; display: flex; align-items: center; cursor: pointer">
                                            <span style="position: relative; width: 44px; height: 26px; border-radius: 999px; background: {{ $a->is_active ? '#1F3A5F' : '#94A3B8' }}"><span style="position: absolute; top: 3px; left: {{ $a->is_active ? '21px' : '3px' }}; width: 20px; height: 20px; border-radius: 50%; background: #FFFFFF"></span></span>
                                        </button>
                                        <span style="font-size: 13px; color: #334155">{{ $a->is_active ? 'Active' : 'Inactive' }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="padding: 14px 20px; border-top: 1px solid #E2E8F0; font-size: 13px; color: #475569">Showing {{ $rows->count() }} of {{ $totals['total'] }} · sorted by last activity</div>
        </section>
    @endif

    @if ($this->allowed(StaPermission::ViewAuditLog))
        <section aria-labelledby="log-title" style="background: #F8FAFC; border: 1px solid #CBD5E1; border-radius: 14px; display: flex; flex-direction: column">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 20px; border-bottom: 1px solid #CBD5E1; background: #F1F5F9; border-radius: 13px 13px 0 0">
                <div style="display: flex; flex-direction: column; gap: 2px">
                    <h2 id="log-title" style="{{ $bandHead }}; color: #334155"><span aria-hidden="true" style="{{ $bandIcon }}; background: #475569"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h12"/><path d="M8 12h12"/><path d="M8 18h12"/><path d="M4 6h.01"/><path d="M4 12h.01"/><path d="M4 18h.01"/></svg></span>Recent activity</h2>
                    <span style="font-size: 13px; color: #475569">From the audit log · medical changes are marked, values are never shown</span>
                </div>
                <a href="{{ $this->auditLogUrl() }}" style="{{ $linkStyle }}">Open audit log</a>
            </div>
            <ol style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column">
                @forelse ($this->recentActivity() as $e)
                    <li style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px 16px; min-height: 52px; padding: 10px 20px; box-sizing: border-box{{ $loop->first ? '' : '; border-top: 1px solid #EEF2F6' }}">
                        <span style="flex: 0 0 104px; font-size: 13px; color: #475569">{{ Sta::when($e->created_at) }}</span>
                        <div style="flex: 1 1 320px; min-width: 0; display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px">
                            <span><strong style="font-weight: 600">{{ $e->causer?->name ?? 'System' }}</strong>@if ($e->causer instanceof \App\Models\User) <span style="color: #475569">({{ $this::roleLabel($e->causer) }})</span>@endif {{ $this::activityText($e) }}</span>
                            @if ($e->properties->get('medical_changed'))
                                <span style="display: inline-flex; align-items: center; gap: 6px; height: 24px; padding: 0 9px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #F1F5F9; color: #334155">
                                    <svg aria-hidden="true" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                                    Medical data changed
                                </span>
                            @endif
                            @if ($e->properties->get('documents_changed'))
                                <span style="display: inline-flex; align-items: center; height: 24px; padding: 0 9px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #E0E7FF; color: #1E3A8A">Documents changed</span>
                            @endif
                        </div>
                    </li>
                @empty
                    <li style="padding: 14px 20px; color: #475569">No activity yet.</li>
                @endforelse
            </ol>
        </section>
    @endif
</x-filament-panels::page>
