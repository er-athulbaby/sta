<x-filament-panels::page>
    @include('filament.portal.partials.phase-top', ['pdfKey' => null, 'pdfLabel' => null])

    @php
        $paths = $this->submission->pdf_paths ?? [];
        $submitted = $this->submission->status === \App\Enums\SubmissionStatus::Submitted;
        $maleEv = \App\Enums\EventGender::Men;
        $femaleEv = \App\Enums\EventGender::Women;
        $mixedEv = \App\Enums\EventGender::Mixed;
        $msg = fn (string $key) => $errors->first($key) ?: ($liveErrors[$key] ?? null);
        $int = fn ($v) => is_numeric($v) ? (int) $v : 0;
    @endphp

    @forelse ($this->visibleSports() as $gs)
        @php
            $pm = $int($totals[$gs->id]['male'] ?? null);
            $pf = $int($totals[$gs->id]['female'] ?? null);
            $entries = [
                'male' => $gs->gameEvents->filter(fn ($ge) => $ge->event->gender === $maleEv)->sum(fn ($ge) => $int($counts[$ge->id] ?? null)),
                'female' => $gs->gameEvents->filter(fn ($ge) => $ge->event->gender === $femaleEv)->sum(fn ($ge) => $int($counts[$ge->id] ?? null)),
            ];
        @endphp
        <div class="sta-split" wire:key="sport-{{ $gs->id }}">
            <x-sta.card class="sta-main" :heading="$gs->sport->name" description="Enter how many athletes you plan to send to each event">
                <fieldset class="sta-fieldset is-tinted" wire:key="participants-{{ $gs->id }}">
                    <legend>
                        <span class="sta-legend-title">Total number of participants</span>
                        <span class="sta-muted">Count each athlete once, even if they enter several events.</span>
                    </legend>
                    @foreach (['male' => ['Male', $gs->p2_max_men], 'female' => ['Female', $gs->p2_max_women]] as $field => [$label, $max])
                        <label class="sta-inline-field" for="totals-{{ $gs->id }}-{{ $field }}">{{ $label }}@if ($max !== null) <span class="sta-muted">(up to {{ $max }})</span>@endif
                            <input id="totals-{{ $gs->id }}-{{ $field }}" type="number" min="0" max="999" class="sta-input sta-input-num"
                                wire:model.live.debounce.400ms="totals.{{ $gs->id }}.{{ $field }}" @disabled($readOnlyReason)>
                        </label>
                        @if ($m = $msg("totals.{$gs->id}.{$field}"))<p class="sta-error">{{ $m }}</p>@endif
                    @endforeach
                    <span class="sta-muted">Total {{ $pm + $pf }}@if ($gs->p2_max_total !== null) · male + female up to {{ $gs->p2_max_total }}@endif</span>
                    @foreach (['male', 'female', 'total'] as $k)
                        @if ($m = $msg("sport.{$gs->id}.{$k}"))<p class="sta-error" role="alert">{{ $m }}</p>@endif
                    @endforeach
                </fieldset>

                <div class="sta-scroll">
                    <table class="sta-table">
                        <thead>
                            <tr><th scope="col">Event</th><th scope="col" class="sta-c">Male</th><th scope="col" class="sta-c">Female</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($gs->gameEvents as $ge)
                                @php
                                    $g = $ge->event->gender;
                                    $eMsg = $msg('counts.'.$ge->id);
                                @endphp
                                <tr>
                                    <th scope="row">
                                        <span class="sta-who-text">
                                            <span>{{ $ge->event->name }}</span>
                                            @if ($ge->p2_max_athletes !== null)
                                                <span class="sta-allow">Up to {{ $ge->p2_max_athletes }} {{ \Illuminate\Support\Str::plural('athlete', $ge->p2_max_athletes) }}</span>
                                            @endif
                                        </span>
                                    </th>
                                    @if ($g === $mixedEv)
                                        <td colspan="2" class="sta-c">
                                            <input type="number" min="0" aria-label="{{ $ge->event->name }} (Mixed)" class="sta-input sta-input-num"
                                                wire:model.live.debounce.400ms="counts.{{ $ge->id }}" @disabled($readOnlyReason)>
                                            <span class="sta-muted">Mixed</span>
                                            @if ($eMsg)<p class="sta-error">{{ $eMsg }}</p>@endif
                                        </td>
                                    @else
                                        @foreach ([$maleEv, $femaleEv] as $col)
                                            @if ($g === $col)
                                                <td class="sta-c">
                                                    <input type="number" min="0" aria-label="{{ $ge->event->name }}" class="sta-input sta-input-num"
                                                        wire:model.live.debounce.400ms="counts.{{ $ge->id }}" @disabled($readOnlyReason)>
                                                    @if ($eMsg)<p class="sta-error">{{ $eMsg }}</p>@endif
                                                </td>
                                            @else
                                                <td class="sta-off"><span class="sta-sr-only">Not open to this gender</span></td>
                                            @endif
                                        @endforeach
                                    @endif
                                </tr>
                            @endforeach
                            <tr class="sta-total">
                                <th scope="row">Event entries</th>
                                <td class="sta-c">{{ $entries['male'] }}</td>
                                <td class="sta-c">{{ $entries['female'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <fieldset class="sta-fieldset" wire:key="medals-{{ $gs->id }}">
                    <legend>
                        <span class="sta-legend-title">Expected medals</span>
                        <span class="sta-muted">Optional · not linked to an athlete or event</span>
                    </legend>
                    @foreach (['gold' => ['Gold', '#CA8A04'], 'silver' => ['Silver', '#94A3B8'], 'bronze' => ['Bronze', '#B45309']] as $field => [$label, $dot])
                        <label class="sta-inline-field" for="totals-{{ $gs->id }}-{{ $field }}"><span class="sta-dot" style="background: {{ $dot }}" aria-hidden="true"></span>{{ $label }}
                            <input id="totals-{{ $gs->id }}-{{ $field }}" type="number" min="0" max="99" class="sta-input sta-input-num"
                                wire:model.live.debounce.400ms="totals.{{ $gs->id }}.{{ $field }}" @disabled($readOnlyReason)>
                        </label>
                        @if ($m = $msg("totals.{$gs->id}.{$field}"))<p class="sta-error">{{ $m }}</p>@endif
                    @endforeach
                </fieldset>

                @if ($submitted && isset($paths[$gs->id]))
                    <div class="sta-card-foot"><a target="_blank" href="{{ \App\Filament\Shared\AttachmentActions::url($paths[$gs->id]) }}">View Entry by Event PDF</a></div>
                @endif
            </x-sta.card>

            <aside class="sta-aside" aria-label="{{ $gs->sport->name }} limits and entry policy">
                <x-sta.card :heading="$gs->sport->name.' limits'" description="Checked against the total number of participants" heading-tag="h3">
                    <div class="sta-card-body sta-stack" aria-live="polite">
                        <x-sta.meter label="Male participants" :value="$pm" :max="$gs->p2_max_men" />
                        <x-sta.meter label="Female participants" :value="$pf" :max="$gs->p2_max_women" />
                        <x-sta.meter label="Total participants" :value="$pm + $pf" :max="$gs->p2_max_total" />
                    </div>
                </x-sta.card>
                <x-sta.card heading="Entry policy" heading-tag="h3">
                    <div class="sta-card-body sta-stack">
                        <p>Each event shows how many athletes it allows.</p>
                        @if ($gs->entry_policy)<p>{{ $gs->entry_policy }}</p>@endif
                        @if ($gs->dob_from || $gs->dob_to)
                            <div class="sta-note"><strong>Age eligibility:</strong> born {{ $gs->dob_from ? 'from '.$gs->dob_from->format('j M Y') : '' }} {{ $gs->dob_to ? 'to '.$gs->dob_to->format('j M Y') : '' }}</div>
                        @endif
                    </div>
                </x-sta.card>
            </aside>
        </div>
    @empty
        <p class="sta-muted">No sports to enter for your association in this game.</p>
    @endforelse

    <p class="sta-muted">A saved draft is never submitted. After you submit, your association admin approves it before it goes to STA, and a signed Entry by Event PDF is generated.</p>
    @unless ($readOnlyReason)
        <div><x-filament::button color="gray" wire:click="save">Save as draft</x-filament::button></div>
    @endunless
    @include('filament.partials.comments', ['comments' => $this->submission->commentsNewestFirst()])
</x-filament-panels::page>
