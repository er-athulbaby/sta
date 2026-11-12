{{-- Canvas AdminPeople.dc.html: the people list (Filament table records of this page) and the selected person's aside. --}}
@php
    $records = $this->getTableRecords();
    $total = $records instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $records->total() : $records->count();
    $person = $this->panelPerson();
    $soon = now()->addMonths(6);
    $expiring = (bool) ($this->tableFilters['expiring']['isActive'] ?? false);
    $pill = fn (string $bg, string $fg, ?string $ring = null) => 'display: inline-flex; align-items: center; height: 24px; padding: 0 9px; border-radius: 999px; font-size: 12px; font-weight: 600; background: '.$bg.'; color: '.$fg.($ring ? '; box-shadow: inset 0 0 0 1px '.$ring : '');
    $scans = fn ($p) => (int) filled($p->passport_scan_path) + (int) filled($p->cpr_front_path);
    $typeLabel = fn ($p) => $p->type === \App\Enums\PersonType::Official ? ($p->function ?: 'Team official') : 'Athlete';
@endphp
<div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start">

    <section aria-label="People list" style="flex: 999 1 560px; min-width: 0; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px">
        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
            @include('filament.people.list-parts', ['part' => 'search', 'basis' => '200px', 'label' => 'Search people', 'placeholder' => 'Name, passport or CPR'])
            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600">
                <span style="position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0)">Association</span>
                <select wire:model.live="tableFilters.association.value" style="min-height: 44px; padding: 0 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; font: 14px Inter, sans-serif; background: var(--sta-bg-surface, #FFFFFF)">
                    <option value="">All associations</option>
                    @foreach ($this->associationOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            @include('filament.people.list-parts', ['part' => 'type'])
            <button type="button" aria-pressed="{{ $expiring ? 'true' : 'false' }}" wire:click="$set('tableFilters.expiring.isActive', {{ $expiring ? 'false' : 'true' }})"
                style="min-height: 44px; padding: 0 14px; border-radius: 10px; font: 600 13px Inter, sans-serif; cursor: pointer; {{ $expiring ? 'border: 0; background: var(--sta-bg-orange-800, #9A3412); color: var(--sta-fg-on-brand, #FFFFFF)' : 'border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-strong, #334155)' }}">Expiring passports</button>
        </div>
        <div style="overflow-x: auto">
            <table style="width: 100%; min-width: 680px; border-collapse: collapse">
                <thead>
                    <tr style="text-align: left; font-size: 12px; color: var(--sta-fg-muted, #475569); text-transform: uppercase; letter-spacing: 0.05em">
                        @include('filament.people.list-parts', ['part' => 'name-th'])
                        <th scope="col" style="padding: 12px; font-weight: 600">Association</th>
                        <th scope="col" style="padding: 12px; font-weight: 600">Type</th>
                        <th scope="col" style="padding: 12px; font-weight: 600">Passport expiry</th>
                        <th scope="col" style="padding: 12px 20px; font-weight: 600">Scans</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($records as $p)
                        @php $on = $person?->id === $p->id; $n = $scans($p); $a = $p->association; @endphp
                        <tr wire:key="{{ $this->getId() }}.table.records.{{ $this->getTableRecordKey($p) }}" style="border-top: 1px solid var(--sta-bd-row, #EEF2F6){{ $on ? '; background: var(--sta-bg-row-active, #F1F5FB)' : '' }}">
                            <td style="padding: 8px 20px">
                                <button type="button" wire:click="selectPerson({{ $p->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}" style="display: flex; align-items: center; gap: 12px; min-height: 44px; border: 0; background: transparent; padding: 0; cursor: pointer; font: inherit; color: inherit; text-align: left">
                                    <span aria-hidden="true" style="width: 36px; height: 36px; border-radius: 50%; background: var(--sta-bg-selected, #E8EEF6); color: var(--sta-fg-brand, #1F3A5F); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; flex-shrink: 0">{{ \App\Support\Sta::initials($p->fullName()) }}</span>
                                    <span style="font-weight: 600">{{ $p->fullName() }}</span>
                                </button>
                            </td>
                            <td style="padding: 8px 12px; color: var(--sta-fg-strong, #334155)">
                                <span style="display: flex; align-items: center; gap: 8px">
                                    @if ($a?->logo_path)
                                        <span style="display: inline-flex; width: 38px; height: 24px; flex-shrink: 0; box-sizing: border-box; padding: 3px; border-radius: 8px; background: var(--sta-bg-logo, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); align-items: center; justify-content: center"><img src="{{ \App\Filament\Shared\AttachmentActions::url($a->logo_path) }}" alt="{{ $a->name }} logo" loading="lazy" style="width: 100%; height: 100%; object-fit: contain" /></span>
                                    @elseif ($a)
                                        <span aria-hidden="true" style="width: 24px; height: 24px; border-radius: 50%; background: {{ \App\Support\Sta::avatarColour($a->code ?? $a->name) }}; color: var(--sta-fg-on-brand, #FFFFFF); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 9px; flex-shrink: 0">{{ $a->code ?? \App\Support\Sta::initials($a->name) }}</span>
                                    @endif
                                    {{ preg_replace('/^Bahrain\s+/u', '', (string) $a?->name) }}
                                </span>
                            </td>
                            <td style="padding: 8px 12px; color: var(--sta-fg-strong, #334155)">{{ $typeLabel($p) }}</td>
                            <td style="padding: 8px 12px; font-weight: 600; color: {{ $p->passport_expiry?->lt($soon) ? 'var(--sta-fg-orange-800, #9A3412)' : 'var(--sta-fg-strong, #334155)' }}">{{ $p->passport_expiry?->format('d M Y') }}</td>
                            <td style="padding: 8px 20px"><span style="{{ $n === 2 ? $pill('var(--sta-bg-green-100, #DCFCE7)', 'var(--sta-fg-green-800, #166534)') : ($n === 1 ? $pill('var(--sta-bg-amber-100, #FEF3C7)', 'var(--sta-fg-amber-800, #92400E)') : $pill('var(--sta-bg-surface, #FFFFFF)', 'var(--sta-fg-orange-800, #9A3412)', 'var(--sta-bd-orange-300, #FDBA74)')) }}">{{ $n === 2 ? 'Both' : ($n === 1 ? '1 of 2' : 'None') }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($records->isEmpty())
                <p style="margin: 0; padding: 24px 20px; color: var(--sta-fg-muted, #475569)">No people match these filters.</p>
            @endif
        </div>
        @include('filament.people.list-parts', ['part' => 'foot', 'text' => 'Showing '.$records->count().' of '.number_format($total).' people'])
    </section>

    @if ($person)
        @php
            $n = $scans($person);
            $last4 = fn (?string $v) => filled($v) ? '•••• '.mb_substr($v, -4) : '—';
            $have = $person->sizes->pluck('size', 'clothing_item_id');
            $sizes = \App\Filament\Shared\PersonForm::sizeItems($person->type)->map(fn ($i) => $i->name.' '.($have->get($i->id) ?: '—'))->implode(' · ');
            $facts = [
                ['Date of birth', $person->dob?->format('d M Y') ?? '—', 'var(--sta-fg-text, #0F172A)'],
                ['Gender', $person->gender?->label() ?? '—', 'var(--sta-fg-text, #0F172A)'],
                ['Nationality', $person->nationality ?? '—', 'var(--sta-fg-text, #0F172A)'],
                ['Passport number', $last4($person->passport_no), 'var(--sta-fg-text, #0F172A)'],
                ['Passport expiry', $person->passport_expiry?->format('d M Y') ?? '—', $person->passport_expiry?->lt($soon) ? 'var(--sta-fg-orange-800, #9A3412)' : 'var(--sta-fg-text, #0F172A)'],
                ['CPR number', $last4($person->cpr_no), 'var(--sta-fg-text, #0F172A)'],
                ['Scans uploaded', $n.' of 2', $n === 2 ? 'var(--sta-fg-green-800, #166534)' : 'var(--sta-fg-orange-800, #9A3412)'],
                ['Clothing sizes', $sizes ?: '—', 'var(--sta-fg-text, #0F172A)'],
            ];
            $changed = $this->lastChange($person);
            $audit = \App\Filament\Resources\ActivityLogs\ActivityLogResource::canViewAny() ? \App\Filament\Resources\ActivityLogs\ActivityLogResource::getUrl() : null;
            $scanBtn = 'min-height: 40px; padding: 0 12px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); font: 600 13px Inter, sans-serif; display: inline-flex; align-items: center; text-decoration: none; box-sizing: border-box';
        @endphp
        <aside aria-labelledby="person-title" wire:key="people-aside-{{ $person->id }}" style="flex: 1 1 360px; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px; display: flex; flex-direction: column">
            <div style="display: flex; align-items: center; gap: 14px; padding: 18px 20px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
                <div role="img" aria-label="Photo of {{ $person->fullName() }}" style="width: 60px; height: 60px; border-radius: 50%; background: var(--sta-bg-selected, #E8EEF6); color: var(--sta-fg-brand, #1F3A5F); display: flex; align-items: center; justify-content: center; font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 20px; flex-shrink: 0">{{ \App\Support\Sta::initials($person->fullName()) }}</div>
                <div style="display: flex; flex-direction: column; gap: 2px; min-width: 0">
                    <h2 id="person-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 18px; font-weight: 600">{{ $person->fullName() }}</h2>
                    <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">{{ $person->type === \App\Enums\PersonType::Official ? 'Team official · '.($person->function ?: '—') : 'Athlete' }} · {{ $person->association?->name }}</span>
                </div>
            </div>
            <dl style="margin: 0; padding: 16px 20px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 16px">
                @foreach ($facts as [$k, $v, $color])
                    <div style="display: flex; flex-direction: column; gap: 2px">
                        <dt style="font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ $k }}</dt>
                        <dd style="margin: 0; font-weight: 600; color: {{ $color }}">{{ $v }}</dd>
                    </div>
                @endforeach
            </dl>
            <div style="padding: 0 20px 16px; display: flex; flex-wrap: wrap; gap: 8px">
                @foreach (['passport_scan_path' => 'View passport scan', 'cpr_front_path' => 'View CPR scan'] as $column => $label)
                    @if (filled($person->{$column}))
                        <a href="{{ \App\Filament\Shared\AttachmentActions::url($person->{$column}) }}" target="_blank" rel="noopener" style="{{ $scanBtn }}">{{ $label }}</a>
                    @else
                        <button type="button" disabled title="Not uploaded" style="{{ $scanBtn }}; color: var(--sta-fg-faint, #94A3B8); cursor: not-allowed">{{ $label }}</button>
                    @endif
                @endforeach
            </div>
            <div style="margin: 0 20px 16px; padding: 10px 12px; border-radius: 10px; background: var(--sta-bg-subtle, #F1F5F9); font-size: 12px; color: var(--sta-fg-strong, #334155); line-height: 1.5">Scans open through a secure link valid for 5 minutes.@if ($changed) Last changed by {{ $changed }} ·@endif @if ($audit)<a href="{{ $audit }}">see Audit log</a>@else see Audit log @endif.</div>
            <div style="margin-top: auto; display: flex; gap: 10px; padding: 16px 20px; border-top: 1px solid var(--sta-bd-line, #E2E8F0)">
                <a href="{{ \App\Filament\Resources\People\PersonResource::getUrl('edit', ['record' => $person]) }}" class="sta-btn-primary" style="flex: 1; min-height: 44px; border-radius: 10px; border: 0; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF); font: 600 14px Inter, sans-serif; display: inline-flex; align-items: center; justify-content: center; text-decoration: none">Edit</a>
                @if (auth()->user()?->isSuperAdmin())
                    <button type="button" wire:click="mountTableAction('delete', '{{ $person->getKey() }}')" style="min-height: 44px; padding: 0 14px; border-radius: 10px; border: 1px solid var(--sta-bd-red-300, #FCA5A5); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-red-800, #991B1B); font: 600 14px Inter, sans-serif; cursor: pointer">Delete (Super Admin)</button>
                @endif
            </div>
        </aside>
    @endif
</div>

{{-- The Filament table (which normally renders the action modals) is not drawn on this page. --}}
<x-filament-actions::modals />
