<x-filament-panels::page>
    {{-- Canvas: docs/design/canvas/project/Phase2.dc.html (header band is phase-header via getHeader()). --}}
    @include('filament.portal.partials.phase-top', ['pdfKey' => null, 'pdfLabel' => null, 'canvas' => true])

    @php
        $paths = $this->submission->pdf_paths ?? [];
        $submitted = $this->submission->status === \App\Enums\SubmissionStatus::Submitted;
        $maleEv = \App\Enums\EventGender::Men;
        $femaleEv = \App\Enums\EventGender::Women;
        $mixedEv = \App\Enums\EventGender::Mixed;
        $msg = fn (string $key) => $errors->first($key) ?: ($liveErrors[$key] ?? null);
        $int = fn ($v) => is_numeric($v) ? (int) $v : 0;
        $numInput = 'min-height: 44px; box-sizing: border-box; padding: 0 12px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; font: 600 16px Inter, sans-serif; background: var(--sta-bg-surface, #FFFFFF)';
        $stepBtn = 'width: 44px; height: 44px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); font: 600 18px Inter, sans-serif; cursor: pointer';
        $legendTitle = "font-family: 'Space Grotesk', sans-serif; font-size: 16px; font-weight: 600";
    @endphp

    @forelse ($this->visibleSports() as $gs)
        @php
            $pm = $int($totals[$gs->id]['male'] ?? null);
            $pf = $int($totals[$gs->id]['female'] ?? null);
            $entries = [
                'male' => $gs->gameEvents->filter(fn ($ge) => $ge->event->gender === $maleEv)->sum(fn ($ge) => $int($counts[$ge->id] ?? null)),
                'female' => $gs->gameEvents->filter(fn ($ge) => $ge->event->gender === $femaleEv)->sum(fn ($ge) => $int($counts[$ge->id] ?? null)),
            ];
            // Over-limit line: shown when Phase2Limits flags the sport (live or on save), in the canvas wording.
            $overs = collect(['male' => ['Male', $pm, $gs->p2_max_men], 'female' => ['Female', $pf, $gs->p2_max_women], 'total' => ['Total', $pm + $pf, $gs->p2_max_total]])
                ->filter(fn ($v, $k) => $msg("sport.{$gs->id}.{$k}"))->map(fn ($v) => "{$v[0]} {$v[1]} of max {$v[2]}");
        @endphp
        <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start" wire:key="sport-{{ $gs->id }}">

            <section aria-labelledby="sport-title-{{ $gs->id }}" style="flex: 999 1 520px; min-width: 0; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px">
                <div style="display: flex; flex-direction: column; gap: 2px; padding: 16px 20px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
                    <h2 id="sport-title-{{ $gs->id }}" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 600">{{ $gs->sport->name }}</h2>
                    <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">Enter how many athletes you plan to send to each event</span>
                </div>

                <fieldset wire:key="participants-{{ $gs->id }}" style="margin: 0; border: 0; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0); padding: 14px 20px; display: flex; flex-wrap: wrap; align-items: center; gap: 12px 24px; background: var(--sta-bg-teal-50, #F0FDFA)">
                    <legend style="float: left; padding: 0; display: flex; flex-direction: column; gap: 2px; margin-right: 8px">
                        <span style="{{ $legendTitle }}">Total number of participants</span>
                        <span style="font-size: 12px; color: var(--sta-fg-muted, #475569); font-weight: 400">Different athletes — one athlete can enter several events</span>
                    </legend>
                    @foreach (['male' => 'Male', 'female' => 'Female'] as $field => $label)
                        <label style="display: flex; align-items: center; gap: 10px; font-weight: 600">{{ $label }}
                            <input id="totals-{{ $gs->id }}-{{ $field }}" type="number" min="0" max="999" style="width: 88px; {{ $numInput }}"
                                wire:model.live.debounce.400ms="totals.{{ $gs->id }}.{{ $field }}" @disabled($readOnlyReason)>
                        </label>
                        @if ($m = $msg("totals.{$gs->id}.{$field}"))<p class="sta-error">{{ $m }}</p>@endif
                    @endforeach
                    <span style="font-size: 13px; color: var(--sta-fg-strong, #334155)">Total <strong>{{ $pm + $pf }}</strong></span>
                </fieldset>

                <div style="overflow-x: auto; position: relative">
                    <table style="width: 100%; min-width: 520px; border-collapse: collapse">
                        <thead>
                            <tr style="text-align: left; font-size: 12px; color: var(--sta-fg-muted, #475569); text-transform: uppercase; letter-spacing: 0.05em">
                                <th scope="col" style="padding: 10px 20px; font-weight: 600">Event</th>
                                <th scope="col" style="padding: 10px 12px; font-weight: 600; text-align: center; width: 170px">Male</th>
                                <th scope="col" style="padding: 10px 20px; font-weight: 600; text-align: center; width: 170px">Female</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($gs->gameEvents as $ge)
                                @php
                                    $g = $ge->event->gender;
                                    $eMsg = $msg('counts.'.$ge->id);
                                    $c = $int($counts[$ge->id] ?? null);
                                    $max = $ge->p2_max_athletes;
                                @endphp
                                <tr style="border-top: 1px solid var(--sta-bd-row, #EEF2F6)" wire:key="event-{{ $ge->id }}">
                                    <th scope="row" style="padding: 8px 20px; text-align: left; font-weight: 500">
                                        <span style="display: flex; flex-direction: column; gap: 2px"><span>{{ $ge->event->name }}</span>
                                            @if ($max !== null)<span style="font-size: 12px; font-weight: 600; color: var(--sta-fg-teal-800, #115E59)">Up to {{ $max }} {{ \Illuminate\Support\Str::plural('athlete', $max) }}</span>@endif
                                        </span>
                                    </th>
                                    @foreach ($g === $mixedEv ? ['mixed'] : [$maleEv, $femaleEv] as $i => $col)
                                        @php $on = $col === 'mixed' || $g === $col; @endphp
                                        <td @if ($col === 'mixed') colspan="2" @endif style="padding: 8px {{ $i === 0 && $col !== 'mixed' ? '12px' : '20px' }}; text-align: center; background: {{ $on ? 'var(--sta-bg-surface, #FFFFFF)' : 'var(--sta-bg-line, #E2E8F0)' }}">
                                            @if ($on)
                                                <div style="display: inline-flex; align-items: center; gap: 6px">
                                                    <button type="button" aria-label="Fewer athletes in {{ $ge->event->name }}" wire:click="stepCount({{ $ge->id }}, -1)"
                                                        @disabled($readOnlyReason || $c <= 0) style="{{ $stepBtn }}">−</button>
                                                    <output aria-live="polite" aria-label="{{ $ge->event->name }}{{ $col === 'mixed' ? ' (Mixed)' : '' }}" style="width: 32px; text-align: center; font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 600">{{ $c }}</output>
                                                    <button type="button" aria-label="More athletes in {{ $ge->event->name }}" wire:click="stepCount({{ $ge->id }}, 1)"
                                                        @disabled($readOnlyReason || $c >= ($max ?? 999)) style="{{ $stepBtn }}">+</button>
                                                </div>
                                                @if ($col === 'mixed')<span class="sta-muted">Mixed</span>@endif
                                                @if ($eMsg)<p class="sta-error">{{ $eMsg }}</p>@endif
                                            @else
                                                <span class="sta-sr-only">Not open to this gender</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            <tr style="border-top: 2px solid var(--sta-bd-line, #E2E8F0); background: var(--sta-bg-surface-alt, #F8FAFC)">
                                <th scope="row" style="padding: 12px 20px; text-align: left; font-weight: 700">Event entries</th>
                                <td style="padding: 12px; text-align: center; font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 700">{{ $entries['male'] }}</td>
                                <td style="padding: 12px 20px; text-align: center; font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 700">{{ $entries['female'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <fieldset wire:key="medals-{{ $gs->id }}" style="margin: 0; border: 0; border-top: 1px solid var(--sta-bd-line, #E2E8F0); padding: 14px 20px; display: flex; flex-wrap: wrap; align-items: center; gap: 12px 24px">
                    <legend style="float: left; padding: 0; display: flex; flex-direction: column; gap: 2px; margin-right: 8px">
                        <span style="{{ $legendTitle }}">Expected medals</span>
                        <span style="font-size: 12px; color: var(--sta-fg-muted, #475569); font-weight: 400">Optional · not linked to an athlete or event</span>
                    </legend>
                    @foreach (['gold' => ['Gold', 'var(--sta-bg-yellow-600, #CA8A04)'], 'silver' => ['Silver', 'var(--sta-bg-faint, #94A3B8)'], 'bronze' => ['Bronze', 'var(--sta-bg-amber-700, #B45309)']] as $field => [$label, $dot])
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 600">
                            <span aria-hidden="true" style="width: 14px; height: 14px; border-radius: 50%; background: {{ $dot }}; flex-shrink: 0"></span>{{ $label }}
                            <input id="totals-{{ $gs->id }}-{{ $field }}" type="number" min="0" max="99" style="width: 72px; {{ $numInput }}"
                                wire:model.live.debounce.400ms="totals.{{ $gs->id }}.{{ $field }}" @disabled($readOnlyReason)>
                        </label>
                        @if ($m = $msg("totals.{$gs->id}.{$field}"))<p class="sta-error">{{ $m }}</p>@endif
                    @endforeach
                </fieldset>

                @if ($submitted && isset($paths[$gs->id]))
                    <div class="sta-card-foot"><a target="_blank" href="{{ \App\Filament\Shared\AttachmentActions::url($paths[$gs->id]) }}">View Entry by Event PDF</a></div>
                @endif
            </section>

            <aside aria-label="{{ $gs->sport->name }} limits and entry policy" style="flex: 1 1 300px; display: flex; flex-direction: column; gap: 16px">
                <section aria-labelledby="limits-title-{{ $gs->id }}" style="background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px; padding: 18px 20px; display: flex; flex-direction: column; gap: 16px">
                    <div style="display: flex; flex-direction: column; gap: 2px">
                        <h2 id="limits-title-{{ $gs->id }}" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 17px; font-weight: 600">{{ $gs->sport->name }} limits</h2>
                        <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">Checked against the total number of participants</span>
                    </div>
                    <x-sta.meter label="Male participants" :value="$pm" :max="$gs->p2_max_men" />
                    <x-sta.meter label="Female participants" :value="$pf" :max="$gs->p2_max_women" />
                    <x-sta.meter label="Total participants" :value="$pm + $pf" :max="$gs->p2_max_total" />
                    @if ($overs->isNotEmpty())
                        <div role="alert" style="padding: 10px 12px; border-radius: 10px; background: var(--sta-bg-orange-50, #FFF7ED); color: var(--sta-fg-orange-800, #9A3412); font-size: 13px; line-height: 1.45">Over the limit: {{ $overs->implode(' · ') }}</div>
                    @endif
                </section>

                <section aria-labelledby="policy-title-{{ $gs->id }}" style="background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px; padding: 18px 20px; display: flex; flex-direction: column; gap: 10px">
                    <h2 id="policy-title-{{ $gs->id }}" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 17px; font-weight: 600">Entry policy</h2>
                    <p style="margin: 0; line-height: 1.55; color: var(--sta-fg-strong, #334155)">Each event shows how many athletes it allows.</p>
                    @if ($gs->entry_policy)<p style="margin: 0; line-height: 1.55; color: var(--sta-fg-strong, #334155)">{{ $gs->entry_policy }}</p>@endif
                    @if ($gs->dob_from || $gs->dob_to)
                        <div style="margin-top: 4px; padding: 10px 12px; border-radius: 10px; background: var(--sta-bg-subtle, #F1F5F9); font-size: 13px; color: var(--sta-fg-strong, #334155)"><strong>Age eligibility:</strong>
                            @if ($gs->dob_from && $gs->dob_to) born between {{ $gs->dob_from->format('j M Y') }} and {{ $gs->dob_to->format('j M Y') }}
                            @elseif ($gs->dob_to) born on or before {{ $gs->dob_to->format('j M Y') }}
                            @else born on or after {{ $gs->dob_from->format('j M Y') }}
                            @endif
                        </div>
                    @endif
                </section>

                @if ($loop->last)
                    @include('filament.portal.partials.submit-block', ['pdfName' => 'Entry by Event'])
                @endif
            </aside>
        </div>
    @empty
        <p class="sta-muted">No sports to enter for your association in this game.</p>
        @include('filament.portal.partials.submit-block', ['pdfName' => 'Entry by Event'])
    @endforelse

    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
