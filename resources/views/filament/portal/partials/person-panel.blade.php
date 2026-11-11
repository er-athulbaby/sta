{{-- Canvas LongList.dc.html side panel (panel=athlete / panel=official), portal Long List pages only (HasPersonPanel):
     pick from the registry (Add → chip; athletes choose the event inline) or create a person with the registry form. --}}
@if (property_exists($this, 'panel') && $panel !== null && ! $readOnlyReason)
    @php
        $isOfficial = $panel === 'official';
        $panelSports = $this->panelSports();
        $several = $panelSports->count() > 1;
        $events = $panelSports->flatMap(fn ($gs) => $gs->gameEvents->map(fn ($ge) => ['ge' => $ge,
            'label' => ($several ? $gs->sport->name.' · ' : '').\App\Support\Sta::eventLabel($ge->event)]));
        $picks = $this->panelCandidates();
        $input = 'min-height: 44px; padding: 0 10px; border: 1px solid #CBD5E1; border-radius: 10px; font: 14px Inter, sans-serif; background: #FFFFFF';
        $addBtn = 'min-height: 40px; padding: 0 12px; border-radius: 10px; border: 1px solid #1F3A5F; background: #FFFFFF; color: #1F3A5F; font: 600 13px Inter, sans-serif; cursor: pointer';
        $pill = 'display: inline-flex; align-items: center; height: 24px; padding: 0 8px; border-radius: 999px; font-size: 12px; font-weight: 600; ';
        $btn = 'min-height: 44px; padding: 0 14px; border-radius: 10px; font: 600 14px Inter, sans-serif; cursor: pointer; ';
    @endphp
    <aside role="dialog" aria-modal="false" aria-labelledby="panel-title" wire:key="person-panel-{{ $panel }}" x-data x-on:keydown.escape.window="$wire.closePanel()"
        style="position: fixed; top: 0; right: 0; bottom: 0; z-index: 40; width: 480px; max-width: 100%; box-sizing: border-box; background: #FFFFFF; border-left: 1px solid #CBD5E1; box-shadow: -12px 0 32px rgba(15,36,64,0.18); display: flex; flex-direction: column; overflow-y: auto">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 20px 24px; border-bottom: 1px solid #E2E8F0">
            <div style="display: flex; flex-direction: column; gap: 2px">
                <h2 id="panel-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 700">{{ $isOfficial ? 'Add team official' : 'Add athlete' }}</h2>
                <span style="font-size: 13px; color: #475569">{{ $several ? 'Stays on the Long List page' : $panelSports->first()?->sport->name.' · stays on the Long List page' }}</span>
            </div>
            <button type="button" aria-label="Close" wire:click="closePanel" style="width: 44px; height: 44px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; display: inline-flex; align-items: center; justify-content: center; cursor: pointer"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg></button>
        </div>

        <section aria-labelledby="pick-title" style="padding: 18px 24px; display: flex; flex-direction: column; gap: 10px; border-bottom: 1px solid #E2E8F0; background: #F8FAFC">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px">
                <h3 id="pick-title" style="margin: 0; font-size: 15px; font-weight: 700">Pick from your registry</h3>
                <a href="{{ \App\Filament\Portal\Resources\People\PersonResource::getUrl('index') }}" style="display: inline-flex; align-items: center; min-height: 44px; font-size: 13px; font-weight: 600">Open registry</a>
            </div>
            <label style="display: flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 12px; border: 1px solid #CBD5E1; border-radius: 10px; background: #FFFFFF">
                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <span class="sta-sr-only">Search registry</span>
                <input type="search" wire:model.live.debounce.300ms="panelSearch" placeholder="Search by name, code or CPR" style="flex: 1; min-width: 0; border: 0; outline: none; font: 14px Inter, sans-serif; background: transparent">
            </label>
            <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px">
                @forelse ($picks as $p)
                    @php
                        $complete = $p->missingFields() === [];
                        $targets = $isOfficial
                            ? $panelSports->map(fn ($gs) => ['id' => $gs->id, 'label' => $gs->sport->name])->values()
                            : $events->filter(fn ($e) => \App\Services\Phases\LongListChecks::genderMatches($p->gender, $e['ge']->event->gender))->map(fn ($e) => ['id' => $e['ge']->id, 'label' => $e['label']])->values();
                        $call = $isOfficial ? 'addOfficialChip' : 'addAthleteChip';
                    @endphp
                    <li wire:key="pick-{{ $p->id }}" x-data="{ ask: false }" style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 6px 10px; border-radius: 10px; background: #FFFFFF; border: 1px solid #E2E8F0">
                        <div style="display: flex; flex-direction: column; flex: 1; min-width: 0">
                            <span style="font-weight: 600">{{ $p->fullName() }}</span>
                            <span style="font-size: 12px; color: #475569">{{ $p->code }} · {{ $isOfficial ? $p->function : $p->gender?->label().' · '.$p->dob?->format('Y') }}</span>
                        </div>
                        <span style="{{ $pill }}{{ $complete ? 'background: #DCFCE7; color: #166534' : 'background: #FFEDD5; color: #9A3412' }}">{{ $complete ? 'Complete' : 'Incomplete' }}</span>
                        @if ($targets->count() === 1)
                            <button type="button" wire:click="{{ $call }}({{ $targets[0]['id'] }}, {{ $p->id }})" aria-label="Add {{ $p->fullName() }}" style="{{ $addBtn }}">Add</button>
                        @else
                            <button type="button" x-show="! ask" x-on:click="ask = true; $nextTick(() => $refs.target.focus())" aria-label="Add {{ $p->fullName() }}" style="{{ $addBtn }}">Add</button>
                            <label x-show="ask" style="flex-basis: 100%; display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600">{{ $isOfficial ? 'Sport' : 'Event' }}
                                <select x-ref="target" x-on:change="$event.target.value && $wire.{{ $call }}($event.target.value, {{ $p->id }})" style="{{ $input }}">
                                    <option value="">Choose…</option>
                                    @foreach ($targets as $t)<option value="{{ $t['id'] }}">{{ $t['label'] }}</option>@endforeach
                                </select>
                            </label>
                        @endif
                    </li>
                @empty
                    <li style="font-size: 13px; color: #475569">{{ $panelSearch !== '' ? 'Nobody in your registry matches.' : 'Everyone who can be added is already on the list.' }}</li>
                @endforelse
            </ul>
        </section>

        <div style="padding: 18px 24px; display: flex; flex-direction: column; gap: 12px">
            <div style="display: flex; flex-direction: column; gap: 2px">
                <h3 id="new-title" style="margin: 0; font-size: 15px; font-weight: 700">{{ $isOfficial ? 'Or add a new team official' : 'Or add a new athlete' }}</h3>
                <span style="font-size: 12px; color: #475569">Same fields and rules as the registry. After saving, the person is in your registry and can be picked straight away.</span>
            </div>
            {{ $this->newPersonForm }}
            @unless ($isOfficial)
                <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600">Event
                    <select wire:model="newPersonEvent" style="{{ $input }}">
                        <option value="">Choose…</option>
                        @foreach ($events as $e)<option value="{{ $e['ge']->id }}">{{ $e['label'] }}</option>@endforeach
                    </select>
                </label>
            @endunless
            @error('newPersonEvent')<p role="alert" style="margin: 0; font-size: 13px; color: #B91C1C; font-weight: 600">{{ $message }}</p>@enderror
            <div style="display: flex; flex-wrap: wrap; gap: 10px; padding-top: 4px">
                <button type="button" wire:click="closePanel" style="{{ $btn }}border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A">Cancel</button>
                <button type="button" wire:click="createPerson(false)" style="{{ $btn }}border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A">Save as draft</button>
                <button type="button" wire:click="createPerson(true)" style="{{ $btn }}flex: 1 1 auto; padding: 0 16px; border: 0; background: #1F3A5F; color: #FFFFFF">Save and add to Long List</button>
            </div>
        </div>
    </aside>
@endif
