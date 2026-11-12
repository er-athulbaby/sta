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
        $input = 'min-height: 44px; padding: 0 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; font: 14px Inter, sans-serif; background: var(--sta-bg-surface, #FFFFFF)';
        $addBtn = 'min-height: 40px; padding: 0 12px; border-radius: 10px; border: 1px solid var(--sta-bd-brand, #1F3A5F); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-brand, #1F3A5F); font: 600 13px Inter, sans-serif; cursor: pointer';
        $pill = 'display: inline-flex; align-items: center; height: 24px; padding: 0 8px; border-radius: 999px; font-size: 12px; font-weight: 600; ';
        $btn = 'min-height: 44px; padding: 0 14px; border-radius: 10px; font: 600 14px Inter, sans-serif; cursor: pointer; ';
    @endphp
    <aside role="dialog" aria-modal="false" aria-labelledby="panel-title" wire:key="person-panel-{{ $panel }}" x-data x-on:keydown.escape.window="$wire.closePanel()"
        style="position: fixed; top: 0; right: 0; bottom: 0; z-index: 40; width: 480px; max-width: 100%; box-sizing: border-box; background: var(--sta-bg-surface, #FFFFFF); border-left: 1px solid var(--sta-bd-line-strong, #CBD5E1); box-shadow: -12px 0 32px rgba(15,36,64,0.18); display: flex; flex-direction: column; overflow-y: auto">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 20px 24px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
            <div style="display: flex; flex-direction: column; gap: 2px">
                <h2 id="panel-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 700">{{ $isOfficial ? 'Add team official' : 'Add athlete' }}</h2>
                <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">{{ $several ? 'Stays on the Long List page' : $panelSports->first()?->sport->name.' · stays on the Long List page' }}</span>
            </div>
            <button type="button" aria-label="Close" wire:click="closePanel" style="width: 44px; height: 44px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); display: inline-flex; align-items: center; justify-content: center; cursor: pointer"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg></button>
        </div>

        <section aria-labelledby="pick-title" style="padding: 18px 24px; display: flex; flex-direction: column; gap: 10px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0); background: var(--sta-bg-surface-alt, #F8FAFC)">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px">
                <h3 id="pick-title" style="margin: 0; font-size: 15px; font-weight: 700">Pick from your registry</h3>
                <a href="{{ \App\Filament\Portal\Resources\People\PersonResource::getUrl('index') }}" style="display: inline-flex; align-items: center; min-height: 44px; font-size: 13px; font-weight: 600">Open registry</a>
            </div>
            <label style="display: flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 12px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; background: var(--sta-bg-surface, #FFFFFF)">
                <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" style="stroke: var(--sta-fg-muted, #475569)"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
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
                    <li wire:key="pick-{{ $p->id }}" x-data="{ ask: false }" style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 6px 10px; border-radius: 10px; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0)">
                        <div style="display: flex; flex-direction: column; flex: 1; min-width: 0">
                            <span style="font-weight: 600">{{ $p->fullName() }}</span>
                            <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ $p->code }} · {{ $isOfficial ? $p->function : $p->gender?->label().' · '.$p->dob?->format('Y') }}</span>
                        </div>
                        <span style="{{ $pill }}{{ $complete ? 'background: var(--sta-bg-green-100, #DCFCE7); color: var(--sta-fg-green-800, #166534)' : 'background: var(--sta-bg-orange-100, #FFEDD5); color: var(--sta-fg-orange-800, #9A3412)' }}">{{ $complete ? 'Complete' : 'Incomplete' }}</span>
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
                    <li style="font-size: 13px; color: var(--sta-fg-muted, #475569)">{{ $panelSearch !== '' ? 'Nobody in your registry matches.' : 'Everyone who can be added is already on the list.' }}</li>
                @endforelse
            </ul>
        </section>

        <div style="padding: 18px 24px; display: flex; flex-direction: column; gap: 12px">
            <div style="display: flex; flex-direction: column; gap: 2px">
                <h3 id="new-title" style="margin: 0; font-size: 15px; font-weight: 700">{{ $isOfficial ? 'Or add a new team official' : 'Or add a new athlete' }}</h3>
                <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">Same fields and rules as the registry. After saving, the person is in your registry and can be picked straight away.</span>
            </div>
            @php
                $field = 'min-height: 44px; box-sizing: border-box; padding: 0 12px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; font: 14px Inter, sans-serif; width: 100%';
                $lbl = 'display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600';
                $err = fn (string $key) => $errors->first($key);
                $errStyle = 'font-size: 12px; font-weight: 600; color: var(--sta-fg-red-700, #B91C1C)';
                $gender = $newPerson['gender'] ?? null;
                $fileRow = 'display: flex; align-items: center; gap: 10px; min-height: 48px; padding: 0 12px; border-radius: 10px; background: var(--sta-bg-green-50, #F0FDF4); box-shadow: inset 0 0 0 1px var(--sta-bd-green-200, #BBF7D0); font-size: 13px';
                $uploadBtn = 'display: flex; align-items: center; gap: 10px; min-height: 48px; padding: 0 12px; border-radius: 10px; border: 1px dashed var(--sta-bd-faint, #94A3B8); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-strong, #334155); font: 600 13px Inter, sans-serif; cursor: pointer';
                $files = [['newPersonPhoto', 'photo', 'Upload photo', 'image/jpeg,image/png'],
                    ['newPersonPassportScan', 'passport scan', 'Upload passport scan', 'application/pdf,image/jpeg,image/png'],
                    ['newPersonCprScan', 'CPR scan', 'Upload CPR scan (front and back)', 'application/pdf,image/jpeg,image/png']];
                $missing = $this->shortFormMissing();
                $text = fn (string $key, string $label, string $type = 'text') => [$key, $label, $type];
            @endphp
            <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 12px">
                @if ($isOfficial)
                    <label style="grid-column: 1 / -1; {{ $lbl }}">Function
                        <select wire:model.live="newPerson.function" style="{{ $field }}; padding: 0 10px; background: var(--sta-bg-surface, #FFFFFF)">
                            <option value="">Choose…</option>
                            @foreach (\App\Filament\Shared\PersonForm::FUNCTIONS as $fn)<option value="{{ $fn }}">{{ $fn }}</option>@endforeach
                        </select>
                        @if ($m = $err('newPerson.function'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                    </label>
                @endif
                @foreach ([['first_name', 'First name'], ['last_name', 'Family name']] as [$key, $label])
                    <label style="{{ $lbl }}">{{ $label }}
                        <input wire:model.blur="newPerson.{{ $key }}" autocomplete="off" style="{{ $field }}">
                        @if ($m = $err('newPerson.'.$key))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                    </label>
                @endforeach
                <fieldset style="margin: 0; padding: 0; border: 0; display: flex; flex-direction: column; gap: 6px">
                    <legend style="padding: 0; font-size: 13px; font-weight: 600; margin-bottom: 6px">Gender</legend>
                    <div style="display: flex; gap: 8px">
                        @foreach (\App\Enums\PersonGender::cases() as $g)
                            <label style="flex: 1; display: flex; align-items: center; gap: 6px; min-height: 44px; padding: 0 10px; border: 1px solid {{ $gender === $g->value ? 'var(--sta-bd-brand, #1F3A5F)' : 'var(--sta-bd-line-strong, #CBD5E1)' }}; border-radius: 10px; font-size: 14px"><input type="radio" name="newPersonGender" value="{{ $g->value }}" wire:model.live="newPerson.gender"> {{ $g->label() }}</label>
                        @endforeach
                    </div>
                    @if ($m = $err('newPerson.gender'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                </fieldset>
                <label style="{{ $lbl }}">Date of birth
                    <input type="date" wire:model.blur="newPerson.dob" max="{{ now()->toDateString() }}" style="{{ $field }}">
                    @if ($m = $err('newPerson.dob'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                </label>
                {{-- Not on the canvas, required by the registry to save: drawn as the same grid fields after Date of birth. --}}
                <label style="{{ $isOfficial ? '' : 'grid-column: 1 / -1; ' }}{{ $lbl }}">Nationality
                    <select wire:model.live="newPerson.nationality" style="{{ $field }}; padding: 0 10px; background: var(--sta-bg-surface, #FFFFFF)">
                        <option value="">Choose…</option>
                        @foreach (config('countries') as $country)<option value="{{ $country }}">{{ $country }}</option>@endforeach
                    </select>
                    @if ($m = $err('newPerson.nationality'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                </label>
                @unless ($isOfficial)
                    <label style="grid-column: 1 / -1; {{ $lbl }}">Event
                        <select wire:model="newPersonEvent" style="{{ $field }}; padding: 0 10px; background: var(--sta-bg-surface, #FFFFFF)">
                            <option value="">Choose…</option>
                            @foreach ($events as $e)<option value="{{ $e['ge']->id }}">{{ $e['label'] }}</option>@endforeach
                        </select>
                        @if ($m = $err('newPersonEvent'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                    </label>
                @endunless
                @if ($isOfficial)
                    <label style="{{ $lbl }}">Mobile
                        <input type="tel" wire:model.blur="newPerson.phone" autocomplete="off" style="{{ $field }}">
                        @if ($m = $err('newPerson.phone'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                    </label>
                    <label style="{{ $lbl }}">Email
                        <input type="email" wire:model.blur="newPerson.email" autocomplete="off" style="{{ $field }}">
                        @if ($m = $err('newPerson.email'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                    </label>
                @endif
                <label style="{{ $lbl }}">Passport number
                    <input wire:model.blur="newPerson.passport_no" autocomplete="off" style="{{ $field }}">
                    @if ($m = $err('newPerson.passport_no'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                </label>
                <label style="{{ $lbl }}">Passport expiry
                    <input type="date" wire:model.blur="newPerson.passport_expiry" style="{{ $field }}">
                    @if ($m = $err('newPerson.passport_expiry'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                </label>
                <label style="{{ $lbl }}">CPR number
                    <input wire:model.live.debounce.400ms="newPerson.cpr_no" autocomplete="off" style="{{ $field }}">
                    @if ($m = $err('newPerson.cpr_no'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                </label>
                @if (filled($newPerson['cpr_no'] ?? null) || $err('newPerson.cpr_expiry'))
                    <label style="{{ $lbl }}">CPR expiry
                        <input type="date" wire:model.blur="newPerson.cpr_expiry" style="{{ $field }}">
                        @if ($m = $err('newPerson.cpr_expiry'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                    </label>
                @endif
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px">
                <span style="font-size: 13px; font-weight: 600">Photo and scans</span>
                @foreach ($files as [$prop, $what, $label, $accept])
                    @if ($this->{$prop})
                        <div style="{{ $fileRow }}" wire:key="file-{{ $prop }}">
                            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="stroke: var(--sta-fg-green-800, #166534)"><path d="m5 12 4 4 10-10"/></svg>
                            <span style="color: var(--sta-fg-green-800, #166534); font-weight: 600; flex: 1; min-width: 0; overflow-wrap: anywhere">{{ $this->{$prop}->getClientOriginalName() }} · {{ \Illuminate\Support\Number::fileSize($this->{$prop}->getSize()) }}</span>
                            <button type="button" wire:click="$set('{{ $prop }}', null)" aria-label="Remove the {{ $what }}" style="min-height: 44px; padding: 0; border: 0; background: transparent; color: var(--sta-fg-brand, #1F3A5F); font: 600 13px Inter, sans-serif; cursor: pointer">Remove</button>
                        </div>
                    @else
                        <label style="{{ $uploadBtn }}" wire:key="file-{{ $prop }}">
                            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/></svg>
                            {{ $label }}
                            <input type="file" wire:model="{{ $prop }}" accept="{{ $accept }}" class="sta-sr-only">
                        </label>
                    @endif
                    @if ($m = $err($prop))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                @endforeach
            </div>
            @php $sizeItems = \App\Filament\Shared\PersonForm::sizeItems($isOfficial ? \App\Enums\PersonType::Official : \App\Enums\PersonType::Athlete); @endphp
            @if ($sizeItems->isNotEmpty())
                <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px">
                    @foreach ($sizeItems as $item)
                        <label style="{{ $lbl }}">{{ $item->name }}
                            <select wire:model.live="newPerson.sizes.{{ $item->id }}" style="{{ $field }}; padding: 0 8px; background: var(--sta-bg-surface, #FFFFFF)">
                                <option value="">Choose</option>
                                @foreach ($item->sizes as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                            </select>
                            @if ($m = $err('newPerson.sizes.'.$item->id))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                        </label>
                    @endforeach
                </div>
            @endif
            @if ($missing)
                <div role="status" style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 10px; background: var(--sta-bg-orange-50, #FFF7ED); color: var(--sta-fg-orange-800, #9A3412); font-size: 13px">
                    <span style="display: inline-flex; align-items: center; height: 24px; padding: 0 8px; border-radius: 999px; background: var(--sta-bg-orange-100, #FFEDD5); font-weight: 700; font-size: 12px; flex-shrink: 0">Incomplete</span>
                    <span>{{ \Illuminate\Support\Arr::join($missing, ', ', ' and ') }} missing — you can still save as draft.</span>
                </div>
            @endif
            <div style="display: flex; flex-wrap: wrap; gap: 10px; padding-top: 4px">
                <button type="button" wire:click="closePanel" style="{{ $btn }}border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A)">Cancel</button>
                <button type="button" wire:click="createPerson(false)" style="{{ $btn }}border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A)">Save as draft</button>
                <button type="button" wire:click="createPerson(true)" style="{{ $btn }}flex: 1 1 auto; padding: 0 16px; border: 0; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF)">Save and add to Long List</button>
            </div>
        </div>
    </aside>
@endif
