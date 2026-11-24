{{-- "Add {name} to {game}" pop-up (AddsToLongList): every Add on the Long List pages, portal and STA "Edit as STA".
     Drawn with the side panel's field styles (person-panel). --}}
@if ($adding !== null && ! $readOnlyReason && ($ap = $this->addingPerson()))
    @php
        $isOfficial = $ap->type === \App\Enums\PersonType::Official;
        $targets = $this->addTargets($ap);
        $field = 'min-height: 44px; box-sizing: border-box; padding: 0 12px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; font: 14px Inter, sans-serif; width: 100%; background: var(--sta-bg-surface, #FFFFFF)';
        $lbl = 'display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600';
        $errStyle = 'font-size: 12px; font-weight: 600; color: var(--sta-fg-red-700, #B91C1C)';
        $btn = 'min-height: 44px; padding: 0 14px; border-radius: 10px; font: 600 14px Inter, sans-serif; cursor: pointer; ';
        $h3 = 'margin: 0; font-size: 15px; font-weight: 700';
        $err = fn (string $key) => $errors->first($key);
        $sizeItems = \App\Filament\Shared\PersonForm::sizeItems($ap->type);
    @endphp
    <div wire:key="add-to-list-{{ $ap->id }}" x-data x-on:keydown.escape.window="$wire.closeAdd()"
        style="position: fixed; inset: 0; z-index: 50; display: flex; align-items: center; justify-content: center; padding: 16px; background: rgba(15,36,64,0.45)">
        <div role="dialog" aria-modal="true" aria-labelledby="add-title"
            style="width: 560px; max-width: 100%; max-height: calc(100vh - 32px); box-sizing: border-box; overflow-y: auto; background: var(--sta-bg-surface, #FFFFFF); border-radius: 14px; box-shadow: 0 24px 48px rgba(15,36,64,0.25); display: flex; flex-direction: column">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 20px 24px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
                <div style="display: flex; flex-direction: column; gap: 2px; min-width: 0">
                    <h2 id="add-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 20px; font-weight: 700">Add {{ $ap->fullName() }} to {{ $this->gameModel->name }}</h2>
                    <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">{{ $ap->code }} · {{ $isOfficial ? 'Team official'.(filled($ap->function) ? ' · '.$ap->function : '') : 'Athlete · '.$ap->gender?->label() }}</span>
                </div>
                <button type="button" aria-label="Close" wire:click="closeAdd" style="width: 44px; height: 44px; flex-shrink: 0; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); display: inline-flex; align-items: center; justify-content: center; cursor: pointer"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg></button>
            </div>

            <div style="padding: 18px 24px; display: flex; flex-direction: column; gap: 18px">
                @if ($m = $err('adding.person'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 12px">
                    @if (! $isOfficial || $targets->count() > 1)
                        <label style="grid-column: 1 / -1; {{ $lbl }}">{{ $isOfficial ? 'Sport' : 'Event' }}
                            <select wire:model="adding.target" style="{{ $field }}; padding: 0 10px">
                                <option value="">Choose…</option>
                                @foreach ($targets as $t)<option value="{{ $t['id'] }}">{{ $t['label'] }}</option>@endforeach
                            </select>
                            @if ($m = $err('adding.target'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                        </label>
                    @endif
                    @if ($isOfficial)
                        <label style="grid-column: 1 / -1; {{ $lbl }}">Function in this game
                            <select wire:model="adding.function" style="{{ $field }}; padding: 0 10px">
                                <option value="">Choose…</option>
                                @foreach (\App\Filament\Shared\PersonForm::FUNCTIONS as $fn)<option value="{{ $fn }}">{{ $fn }}</option>@endforeach
                            </select>
                            @if ($m = $err('adding.function'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                        </label>
                    @endif
                </div>

                <section aria-labelledby="add-passport" style="display: flex; flex-direction: column; gap: 10px; padding-top: 14px; border-top: 1px solid var(--sta-bd-line, #E2E8F0)">
                    <h3 id="add-passport" style="{{ $h3 }}">Passport</h3>
                    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 12px">
                        <label style="{{ $lbl }}">Passport number
                            <input wire:model="adding.passport_no" autocomplete="off" style="{{ $field }}">
                            @if ($m = $err('adding.passport_no'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                        </label>
                        <label style="{{ $lbl }}">Passport expiry
                            <x-sta.date-input wire:model="adding.passport_expiry" style="{{ $field }}" />
                            @if ($m = $err('adding.passport_expiry'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                        </label>
                    </div>
                    <div style="font-size: 13px">
                        @if ($ap->passport_scan_path)<x-sta.passport-links :person="$ap" label="Current passport scan" />@else<span style="color: var(--sta-fg-orange-800, #9A3412); font-weight: 600">No passport scan in the registry</span>@endif
                    </div>
                    @if ($addingPassportScan)
                        <div wire:key="add-scan-file" style="display: flex; align-items: center; gap: 10px; min-height: 48px; padding: 0 12px; border-radius: 10px; background: var(--sta-bg-green-50, #F0FDF4); box-shadow: inset 0 0 0 1px var(--sta-bd-green-200, #BBF7D0); font-size: 13px">
                            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="stroke: var(--sta-fg-green-800, #166534)"><path d="m5 12 4 4 10-10"/></svg>
                            <span style="color: var(--sta-fg-green-800, #166534); font-weight: 600; flex: 1; min-width: 0; overflow-wrap: anywhere">{{ $addingPassportScan->getClientOriginalName() }} · {{ \Illuminate\Support\Number::fileSize($addingPassportScan->getSize()) }} · replaces the current scan</span>
                            <button type="button" wire:click="$set('addingPassportScan', null)" aria-label="Remove the new passport scan" style="min-height: 44px; padding: 0; border: 0; background: transparent; color: var(--sta-fg-brand, #1F3A5F); font: 600 13px Inter, sans-serif; cursor: pointer">Remove</button>
                        </div>
                    @else
                        <div {!! \App\Support\Sta::UPLOAD_PROGRESS !!} wire:key="add-scan-upload" style="display: flex; flex-direction: column; gap: 6px">
                            <label style="display: flex; align-items: center; gap: 10px; min-height: 48px; padding: 0 12px; border-radius: 10px; border: 1px dashed var(--sta-bd-faint, #94A3B8); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-strong, #334155); font: 600 13px Inter, sans-serif; cursor: pointer">
                                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/></svg>
                                Upload latest passport
                                <input type="file" wire:model="addingPassportScan" accept="application/pdf,image/jpeg,image/png" class="sta-sr-only">
                            </label>
                            <x-sta.upload-progress />
                        </div>
                    @endif
                    @if ($m = $err('addingPassportScan'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                    <label style="display: flex; align-items: flex-start; gap: 10px; min-height: 44px; font-size: 14px; font-weight: 600; cursor: pointer">
                        <input type="checkbox" wire:model="adding.confirmed" style="width: 20px; height: 20px; margin-top: 1px; accent-color: var(--sta-bd-brand, #1F3A5F); flex-shrink: 0">
                        The passport shown is the latest one
                    </label>
                    @if ($m = $err('adding.confirmed'))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                </section>

                @if ($sizeItems->isNotEmpty())
                    <section aria-labelledby="add-sizes" style="display: flex; flex-direction: column; gap: 10px; padding-top: 14px; border-top: 1px solid var(--sta-bd-line, #E2E8F0)">
                        <h3 id="add-sizes" style="{{ $h3 }}">Clothing sizes</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px">
                            @foreach ($sizeItems as $item)
                                <label style="{{ $lbl }}">{{ $item->name }}
                                    <select wire:model="adding.sizes.{{ $item->id }}" style="{{ $field }}; padding: 0 8px">
                                        <option value="">Choose</option>
                                        @foreach ($item->sizes as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                                    </select>
                                    @if ($m = $err('adding.sizes.'.$item->id))<span role="alert" style="{{ $errStyle }}">{{ $m }}</span>@endif
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endif
                <p style="margin: 0; font-size: 12px; color: var(--sta-fg-muted, #475569)">The passport and sizes are saved to the registry; the Long List draft is saved with this person.</p>
            </div>

            <div style="display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid var(--sta-bd-line, #E2E8F0)">
                <button type="button" wire:click="closeAdd" style="{{ $btn }}border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A)">Cancel</button>
                <button type="button" wire:click="confirmAdd" wire:loading.attr="disabled" style="{{ $btn }}padding: 0 16px; border: 0; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF)">Add to Long List</button>
            </div>
        </div>
    </div>
@endif
