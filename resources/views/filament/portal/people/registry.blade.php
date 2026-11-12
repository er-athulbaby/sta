{{-- Canvas Registry.dc.html: the people list (Filament table records of this page) and the edit aside for the selected person. --}}
@php
    $records = $this->getTableRecords();
    $total = $records instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $records->total() : $records->count();
    $person = $this->panelPerson();
    $soon = now()->addMonths(6);
    $pillDone = 'display: inline-flex; align-items: center; height: 26px; padding: 0 10px; border-radius: 999px; background: var(--sta-bg-green-100, #DCFCE7); color: var(--sta-fg-green-800, #166534); font-size: 12px; font-weight: 600; white-space: nowrap';
    $pillGap = 'display: inline-flex; align-items: center; height: 26px; padding: 0 10px; border-radius: 999px; background: var(--sta-bg-orange-100, #FFEDD5); color: var(--sta-fg-orange-800, #9A3412); font-size: 12px; font-weight: 600; white-space: nowrap';
    $input = 'min-height: 44px; padding: 0 12px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; font: 14px Inter, sans-serif; font-weight: 400; background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); box-sizing: border-box; width: 100%';
    $photo = fn ($p) => $p->photo_path ? \App\Filament\Shared\AttachmentActions::url($p->photo_path) : null;
@endphp
<div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start">

    <section aria-label="People" style="flex: 999 1 560px; min-width: 0; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px">
        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
            @include('filament.people.list-parts', ['part' => 'search', 'basis' => '220px', 'label' => 'Search by name or passport', 'placeholder' => 'Search by name or passport'])
            @include('filament.people.list-parts', ['part' => 'type'])
        </div>
        <div style="overflow-x: auto">
            <table style="width: 100%; min-width: 680px; border-collapse: collapse">
                <thead>
                    <tr style="text-align: left; font-size: 12px; color: var(--sta-fg-muted, #475569); text-transform: uppercase; letter-spacing: 0.05em">
                        @include('filament.people.list-parts', ['part' => 'name-th'])
                        <th scope="col" style="padding: 12px; font-weight: 600">Type</th>
                        <th scope="col" style="padding: 12px; font-weight: 600">Date of birth</th>
                        <th scope="col" style="padding: 12px; font-weight: 600">Passport expiry</th>
                        <th scope="col" style="padding: 12px 20px; font-weight: 600">Sizes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($records as $p)
                        @php $missing = \App\Filament\Shared\PersonTable::missingSizes($p); @endphp
                        <tr wire:key="{{ $this->getId() }}.table.records.{{ $this->getTableRecordKey($p) }}" style="border-top: 1px solid var(--sta-bd-row, #EEF2F6){{ $person?->id === $p->id ? '; background: var(--sta-bg-row-active, #F1F5FB)' : '' }}">
                            <td style="padding: 12px 20px">
                                <div style="display: flex; align-items: center; gap: 12px">
                                    @if ($url = $photo($p))
                                        <img src="{{ $url }}" alt="Photo of {{ $p->fullName() }}" loading="lazy" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0">
                                    @else
                                        <div role="img" aria-label="Photo of {{ $p->fullName() }}" style="width: 36px; height: 36px; border-radius: 50%; background: var(--sta-bg-avatar, #2B4C7E); color: var(--sta-fg-on-brand, #FFFFFF); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; flex-shrink: 0">{{ \App\Support\Sta::initials($p->fullName()) }}</div>
                                    @endif
                                    <a href="{{ \App\Filament\Portal\Resources\People\PersonResource::getUrl('edit', ['record' => $p]) }}" wire:click.prevent="selectPerson({{ $p->id }})"
                                        aria-current="{{ $person?->id === $p->id ? 'true' : 'false' }}" style="font-weight: 600; text-decoration: none; color: var(--sta-fg-text, #0F172A)">{{ $p->fullName() }}</a>
                                </div>
                            </td>
                            <td style="padding: 12px; color: var(--sta-fg-strong, #334155)">{{ $p->type === \App\Enums\PersonType::Official && filled($p->function) ? $p->function : $p->type->label() }}</td>
                            <td style="padding: 12px; color: var(--sta-fg-strong, #334155)">{{ $p->dob?->format('d M Y') }}</td>
                            <td style="padding: 12px; font-weight: 600; color: {{ $p->passport_expiry?->lt($soon) ? 'var(--sta-fg-orange-800, #9A3412)' : 'var(--sta-fg-strong, #334155)' }}">{{ $p->passport_expiry?->format('d M Y') }}</td>
                            <td style="padding: 12px 20px"><span style="{{ $missing ? $pillGap : $pillDone }}">{{ $missing ? $missing.' missing' : 'Complete' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($records->isEmpty())
                <p style="margin: 0; padding: 24px 20px; color: var(--sta-fg-muted, #475569)">No people match these filters.</p>
            @endif
        </div>
        @include('filament.people.list-parts', ['part' => 'foot', 'text' => 'Showing '.$records->count().' of '.$total.' · passport dates in orange expire within 6 months'])
    </section>

    @if ($person)
        @php
            $items = \App\Filament\Shared\PersonForm::sizeItems($person->type);
            $typeLabel = $person->type === \App\Enums\PersonType::Official ? ($person->function ?: 'Team official') : 'Athlete';
            $canEdit = \App\Filament\Portal\Resources\People\PersonResource::canEdit($person);
            $upload = 'display: flex; align-items: center; gap: 10px; min-height: 52px; padding: 0 12px; border-radius: 10px; border: 1px dashed var(--sta-bd-faint, #94A3B8); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-strong, #334155); font: 600 13px Inter, sans-serif; cursor: pointer; box-sizing: border-box';
            $scans = [
                ['column' => 'passport_scan_path', 'model' => 'panelPassportScan', 'label' => 'passport scan'],
                ['column' => 'cpr_front_path', 'model' => 'panelCprScan', 'label' => 'CPR scan'],
            ];
        @endphp
        <aside aria-labelledby="edit-title" wire:key="registry-aside-{{ $person->id }}" style="flex: 1 1 380px; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px; display: flex; flex-direction: column">
            <div style="display: flex; align-items: center; gap: 14px; padding: 18px 20px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
                @if ($url = $photo($person))
                    <img src="{{ $url }}" alt="Photo of {{ $person->fullName() }}" style="width: 56px; height: 56px; border-radius: 50%; object-fit: cover; flex-shrink: 0">
                @else
                    <div role="img" aria-label="Photo of {{ $person->fullName() }}" style="width: 56px; height: 56px; border-radius: 50%; background: var(--sta-bg-avatar, #2B4C7E); color: var(--sta-fg-on-brand, #FFFFFF); display: flex; align-items: center; justify-content: center; font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 18px; flex-shrink: 0">{{ \App\Support\Sta::initials($person->fullName()) }}</div>
                @endif
                <div style="display: flex; flex-direction: column; gap: 2px; min-width: 0">
                    <h2 id="edit-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 19px; font-weight: 600"><a href="{{ \App\Filament\Portal\Resources\People\PersonResource::getUrl('edit', ['record' => $person]) }}" style="color: var(--sta-fg-text, #0F172A); text-decoration: none">{{ $person->fullName() }}</a></h2>
                    <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">{{ implode(' · ', array_filter([$typeLabel, $person->gender?->label(), $person->dob ? $person->age().' years' : null])) }}</span>
                </div>
            </div>

            <div style="padding: 18px 20px; display: flex; flex-direction: column; gap: 18px">
                <fieldset @disabled(! $canEdit) style="border: 0; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px">
                    <legend style="font-size: 12px; font-weight: 600; color: var(--sta-fg-muted, #475569); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px">Documents</legend>
                    @foreach (['passport_no' => ['Passport number', 'text'], 'passport_expiry' => ['Passport expiry', 'date'], 'cpr_no' => ['CPR number', 'text'], 'cpr_expiry' => ['CPR expiry', 'date']] as $field => [$fieldLabel, $inputType])
                        <label style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600">{{ $fieldLabel }}
                            <input type="{{ $inputType }}" wire:model="panel.{{ $field }}" style="{{ $input }}" @error('panel.'.$field) aria-invalid="true" @enderror>
                            @error('panel.'.$field)<span role="alert" style="color: var(--sta-fg-red-700, #B91C1C); font-size: 12px">{{ $message }}</span>@enderror
                        </label>
                    @endforeach
                    <div style="grid-column: 1 / -1; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px">
                        @foreach ($scans as $scan)
                            @php $file = $this->{$scan['model']}; @endphp
                            @if (filled($person->{$scan['column']}) && ! $file)
                                <div style="display: flex; align-items: center; gap: 10px; min-height: 52px; padding: 0 12px; border-radius: 10px; background: var(--sta-bg-green-50, #F0FDF4); box-shadow: inset 0 0 0 1px var(--sta-bd-green-200, #BBF7D0); font-size: 13px; min-width: 0">
                                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="stroke: var(--sta-fg-green-800, #166534); flex-shrink: 0"><path d="m5 12 4 4 10-10"/></svg>
                                    <span style="color: var(--sta-fg-green-800, #166534); font-weight: 600; overflow-wrap: anywhere">{{ basename($person->{$scan['column']}) }}</span>
                                    <a href="{{ \App\Filament\Shared\AttachmentActions::url($person->{$scan['column']}) }}" target="_blank" rel="noopener" aria-label="View {{ $scan['label'] }}" style="margin-left: auto; display: inline-flex; align-items: center; min-height: 44px; padding: 0 4px; font-weight: 600; font-size: 13px">View</a>
                                </div>
                            @else
                                <label class="sta-upload" style="{{ $upload }}">
                                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="flex-shrink: 0"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/></svg>
                                    <span style="overflow-wrap: anywhere">{{ $file ? $file->getClientOriginalName() : 'Upload '.$scan['label'] }}</span>
                                    <input type="file" wire:model="{{ $scan['model'] }}" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0)">
                                </label>
                            @endif
                        @endforeach
                        @foreach ($scans as $scan)
                            @error($scan['model'])<span role="alert" style="grid-column: 1 / -1; color: var(--sta-fg-red-700, #B91C1C); font-size: 12px">{{ $message }}</span>@enderror
                        @endforeach
                    </div>
                </fieldset>

                @if ($items->isNotEmpty())
                    <fieldset @disabled(! $canEdit) style="border: 0; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px">
                        <legend style="font-size: 12px; font-weight: 600; color: var(--sta-fg-muted, #475569); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px">Clothing sizes</legend>
                        @foreach ($items as $item)
                            @php $picked = $this->panel['sizes'][$item->id] ?? null; $saved = filled($person->sizes->firstWhere('clothing_item_id', $item->id)?->size); @endphp
                            <div wire:key="size-{{ $item->id }}" style="display: flex; flex-direction: column; gap: 8px">
                                <div style="display: flex; justify-content: space-between; font-size: 13px">
                                    <span style="font-weight: 600">{{ $item->name }}</span>
                                    <span style="color: {{ $saved ? 'var(--sta-fg-muted, #475569)' : 'var(--sta-fg-orange-800, #9A3412)' }}">{{ $saved ? 'Saved on this person' : 'Required for this game' }}</span>
                                </div>
                                <div role="radiogroup" aria-label="{{ $item->name }} size" style="display: flex; flex-wrap: wrap; gap: 6px">
                                    @foreach ($item->sizes as $size)
                                        @php $on = (string) $picked === (string) $size; @endphp
                                        <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="$set('panel.sizes.{{ $item->id }}', {{ \Illuminate\Support\Js::from((string) $size) }})"
                                            style="min-width: 44px; min-height: 40px; padding: 0 12px; border-radius: 8px; font: 600 13px Inter, sans-serif; cursor: pointer; {{ $on ? 'border: 0; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF)' : 'border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-strong, #334155)' }}">{{ $size }}</button>
                                    @endforeach
                                </div>
                                @error('panel.sizes.'.$item->id)<span role="alert" style="color: var(--sta-fg-red-700, #B91C1C); font-size: 12px">{{ $message }}</span>@enderror
                            </div>
                        @endforeach
                    </fieldset>
                @endif
            </div>

            @if ($canEdit)
                <div style="margin-top: auto; display: flex; gap: 10px; padding: 16px 20px; border-top: 1px solid var(--sta-bd-line, #E2E8F0)">
                    <button type="button" wire:click="savePanel" style="flex: 1; min-height: 44px; border-radius: 10px; border: 0; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF); font: 600 14px Inter, sans-serif; cursor: pointer">Save changes</button>
                    <button type="button" wire:click="savePanel" style="min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); font: 600 14px Inter, sans-serif; cursor: pointer">Save as draft</button>
                    <button type="button" wire:click="cancelPanel" style="min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); font: 600 14px Inter, sans-serif; cursor: pointer">Cancel</button>
                </div>
            @endif
        </aside>
    @endif
</div>
