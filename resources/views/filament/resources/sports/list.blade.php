{{-- Canvas AdminSports.dc.html: the sports list (Filament table records of this page), the selected sport's details and its events. --}}
@php
    use App\Enums\EventGender;
    use App\Support\Sta;

    $records = $this->getTableRecords();
    $sel = $this->panelSport();
    $options = $this->associationOptions();
    $pill = fn (string $bg, string $fg) => 'display: inline-flex; align-items: center; height: 24px; padding: 0 9px; border-radius: 999px; font-size: 12px; font-weight: 600; background: '.$bg.'; color: '.$fg;
    $genderStyle = ['men' => $pill('#E0E7FF', '#1E3A8A'), 'women' => $pill('#FCE7F3', '#9D174D'), 'mixed' => $pill('#F1F5F9', '#334155')];
    $field = 'min-height: 44px; border: 1px solid #CBD5E1; border-radius: 10px; font: 14px Inter, sans-serif; background: #FFFFFF; box-sizing: border-box';
    $label = 'display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600';
    $used = $sel ? $this->gamesUsing($sel) : collect();
@endphp
<div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start">

    <section aria-label="Sports" style="flex: 1 1 380px; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px">
        <div style="padding: 16px 20px; border-bottom: 1px solid #E2E8F0; display: flex; flex-direction: column; gap: 10px">
            <label style="{{ $label }}">Association
                <select wire:model.live="tableFilters.association.value" style="{{ $field }}; padding: 0 10px">
                    <option value="">All associations</option>
                    @foreach ($options as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <ul style="list-style: none; margin: 0; padding: 8px">
            @foreach ($records as $s)
                @php $on = $sel?->id === $s->id; $a = $s->association; @endphp
                <li wire:key="{{ $this->getId() }}.table.records.{{ $this->getTableRecordKey($s) }}">
                    <button type="button" wire:click="selectSport({{ $s->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}" style="width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 8px; min-height: 56px; padding: 6px 12px; border: 0; border-radius: 10px; cursor: pointer; font: inherit; color: inherit; background: {{ $on ? '#E8EEF6' : 'transparent' }}">
                        <span style="display: flex; flex-direction: column; gap: 2px; text-align: left">
                            <span style="font-weight: 600">{{ $s->name }}</span>
                            <span style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #475569">
                                @if ($a?->logo_path)
                                    <span style="display: inline-flex; width: 38px; height: 24px; flex-shrink: 0; box-sizing: border-box; padding: 3px; border-radius: 8px; background: #FFFFFF; border: 1px solid #E2E8F0; align-items: center; justify-content: center"><img src="{{ \App\Filament\Shared\AttachmentActions::url($a->logo_path) }}" alt="{{ $a->name }} logo" loading="lazy" style="width: 100%; height: 100%; object-fit: contain" /></span>
                                @elseif ($a)
                                    <span aria-hidden="true" style="width: 24px; height: 24px; border-radius: 50%; background: {{ Sta::avatarColour($a->code ?? $a->name) }}; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 9px; flex-shrink: 0">{{ $a->code ?? Sta::initials($a->name) }}</span>
                                @endif
                                {{ preg_replace('/^Bahrain\s+/u', '', (string) $a?->name) }}
                            </span>
                        </span>
                        <span style="display: flex; align-items: center; gap: 8px">
                            <span style="font-size: 12px; color: #475569">{{ $s->events_count }} {{ $s->events_count === 1 ? 'event' : 'events' }}</span>
                            <span style="{{ $s->is_active ? $pill('#DCFCE7', '#166534') : $pill('#F1F5F9', '#475569') }}">{{ $s->is_active ? 'Active' : 'Inactive' }}</span>
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>
        @if ($records->isEmpty())
            <p style="margin: 0; padding: 0 20px 20px; color: #475569">No sports for this association yet.</p>
        @endif
    </section>

    @if ($sel)
        <section aria-labelledby="sport-title" wire:key="sport-detail-{{ $sel->id }}" style="flex: 999 1 520px; min-width: 0; display: flex; flex-direction: column; gap: 16px">
            <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; padding: 18px 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end">
                <label style="{{ $label }}">Sport name
                    <input value="{{ $sel->name }}" wire:change="saveSport({{ $sel->id }}, 'name', $event.target.value)" maxlength="255" required style="{{ $field }}; padding: 0 12px" @error('sport.name') aria-invalid="true" @enderror>
                    @error('sport.name')<span role="alert" style="font-weight: 500; color: #B91C1C">{{ $message }}</span>@enderror
                </label>
                <label style="{{ $label }}">Belongs to association
                    <select wire:change="saveSport({{ $sel->id }}, 'association_id', $event.target.value)" style="{{ $field }}; padding: 0 10px">
                        @foreach ($options as $id => $name)
                            <option value="{{ $id }}" @selected($id === $sel->association_id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('sport.association_id')<span role="alert" style="font-weight: 500; color: #B91C1C">{{ $message }}</span>@enderror
                </label>
                <label style="display: flex; align-items: center; gap: 10px; min-height: 44px; font-weight: 600">
                    <input type="checkbox" @checked($sel->is_active) wire:change="saveSport({{ $sel->id }}, 'is_active', $event.target.checked)" style="width: 20px; height: 20px; accent-color: #1F3A5F">
                    Active
                </label>
            </div>

            <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px">
                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; padding: 16px 20px; border-bottom: 1px solid #E2E8F0">
                    <div style="display: flex; flex-direction: column; gap: 2px">
                        <h2 id="sport-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 18px; font-weight: 600">{{ $sel->name }} events</h2>
                        <span style="font-size: 13px; color: #475569">Events used in a game can be deactivated, not deleted</span>
                    </div>
                    <a href="{{ \App\Filament\Resources\Sports\SportResource::getUrl('edit', ['record' => $sel]) }}" style="min-height: 44px; padding: 0 14px; border-radius: 10px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A; font: 600 13px Inter, sans-serif; display: inline-flex; align-items: center; text-decoration: none; box-sizing: border-box">Rename events</a>
                </div>
                <form wire:submit="addEvent" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; padding: 14px 20px; background: #F8FAFC; border-bottom: 1px solid #E2E8F0">
                    <label style="{{ $label }}; flex: 2 1 220px">New event name
                        <input wire:model="newName" placeholder="e.g. Male Singles" style="{{ $field }}; padding: 0 12px">
                    </label>
                    <label style="{{ $label }}; flex: 1 1 140px">Gender
                        <select wire:model="newGender" style="{{ $field }}; padding: 0 10px">
                            @foreach (EventGender::cases() as $g)
                                <option value="{{ $g->value }}">{{ $g->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" style="min-height: 44px; padding: 0 18px; border-radius: 10px; border: 0; background: #1F3A5F; color: #FFFFFF; font: 600 14px Inter, sans-serif; cursor: pointer">Add event</button>
                </form>
                @if ($this->eventError)
                    <p role="alert" style="margin: 0; padding: 10px 20px; color: #991B1B; background: #FEF2F2; font-size: 13px">{{ $this->eventError }}</p>
                @endif
                <div style="overflow-x: auto">
                    <table style="width: 100%; min-width: 480px; border-collapse: collapse">
                        <thead>
                            <tr style="text-align: left; font-size: 12px; color: #475569; text-transform: uppercase; letter-spacing: 0.05em">
                                <th scope="col" style="padding: 12px 20px; font-weight: 600">Event</th>
                                <th scope="col" style="padding: 12px; font-weight: 600">Gender</th>
                                <th scope="col" style="padding: 12px; font-weight: 600">Used in games</th>
                                <th scope="col" style="padding: 12px 20px; font-weight: 600">Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sel->events as $e)
                                @php $n = (int) ($used[$e->id] ?? 0); @endphp
                                <tr wire:key="sport-event-{{ $e->id }}" style="border-top: 1px solid #EEF2F6">
                                    <td style="padding: 10px 20px; font-weight: 600; color: {{ $e->is_active ? '#0F172A' : '#475569' }}">{{ $e->name }}</td>
                                    <td style="padding: 10px 12px"><span style="{{ $genderStyle[$e->gender->value] }}">{{ $e->gender->label() }}</span></td>
                                    <td style="padding: 10px 12px; color: #334155">{{ $n ? $n.($n === 1 ? ' game' : ' games') : 'Not yet' }}</td>
                                    <td style="padding: 0 20px">
                                        <button type="button" role="switch" aria-checked="{{ $e->is_active ? 'true' : 'false' }}" aria-label="Active: {{ $e->name }} {{ $e->gender->label() }}" wire:click="toggleEvent({{ $e->id }})" style="display: inline-flex; align-items: center; min-height: 44px; border: 0; padding: 0; background: transparent; cursor: pointer">
                                            <span style="position: relative; width: 48px; height: 28px; border-radius: 999px; background: {{ $e->is_active ? '#1F3A5F' : '#CBD5E1' }}"><span style="position: absolute; top: 3px; left: {{ $e->is_active ? '23px' : '3px' }}; width: 22px; height: 22px; border-radius: 50%; background: #FFFFFF"></span></span>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($sel->events->isEmpty())
                        <p style="margin: 0; padding: 16px 20px; color: #475569">No events yet.</p>
                    @endif
                </div>
            </div>
        </section>
    @endif
</div>
