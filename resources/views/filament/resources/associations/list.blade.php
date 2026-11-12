{{-- Canvas AdminAssociations.dc.html: the association list (Filament table records of this page) and the selected association's aside. --}}
@php
    use App\Enums\Role;
    use App\Filament\Resources\Associations\Tables\AssociationsTable;
    use App\Support\Sta;

    $records = $this->getTableRecords();
    $sel = $this->panelAssociation();
    $pill = fn (string $bg, string $fg, ?string $ring = null) => 'display: inline-flex; align-items: center; height: 24px; padding: 0 9px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; background: '.$bg.'; color: '.$fg.($ring ? '; box-shadow: inset 0 0 0 1px '.$ring : '');
    $pills = ['active' => $pill('var(--sta-bg-green-100, #DCFCE7)', 'var(--sta-fg-green-800, #166534)'), 'pending' => $pill('var(--sta-bg-amber-100, #FEF3C7)', 'var(--sta-fg-amber-800, #92400E)'), 'off' => $pill('var(--sta-bg-subtle, #F1F5F9)', 'var(--sta-fg-muted, #475569)'), 'none' => $pill('var(--sta-bg-surface, #FFFFFF)', 'var(--sta-fg-orange-800, #9A3412)', 'var(--sta-bd-orange-300, #FDBA74)')];
    $code = fn ($a) => $a->code ?? Sta::initials($a->name);
    $roleLabel = fn (Role $r) => $r === Role::AssocEditor ? 'Editor' : $r->label();
    $logoUrl = fn ($a) => $a->logo_path ? \App\Filament\Shared\AttachmentActions::url($a->logo_path) : null;
    $small = 'min-height: 40px; padding: 0 12px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); font: 600 12px Inter, sans-serif; cursor: pointer';
@endphp
<div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start">

    <section aria-label="Association list" style="flex: 999 1 560px; min-width: 0; background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0)">
            @include('filament.people.list-parts', ['part' => 'search', 'basis' => 'auto', 'label' => 'Search associations', 'placeholder' => 'Search by name or email'])
        </div>
        <div style="overflow-x: auto">
            <table style="width: 100%; min-width: 640px; border-collapse: collapse">
                <thead>
                    <tr style="text-align: left; font-size: 12px; color: var(--sta-fg-muted, #475569); text-transform: uppercase; letter-spacing: 0.05em">
                        <th scope="col" style="padding: 12px 20px; font-weight: 600">Association</th>
                        <th scope="col" style="padding: 12px; font-weight: 600">Admin</th>
                        <th scope="col" style="padding: 12px; font-weight: 600">Editors</th>
                        <th scope="col" style="padding: 12px 20px; font-weight: 600">Sports</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($records as $a)
                        @php
                            $on = $sel?->id === $a->id;
                            [$admin, $state] = AssociationsTable::adminState($a);
                            $editors = AssociationsTable::editorCount($a);
                        @endphp
                        <tr wire:key="{{ $this->getId() }}.table.records.{{ $this->getTableRecordKey($a) }}" style="border-top: 1px solid var(--sta-bd-row, #EEF2F6){{ $on ? '; background: var(--sta-bg-row-active, #F1F5FB)' : '' }}">
                            <td style="padding: 10px 20px">
                                <button type="button" wire:click="selectAssociation({{ $a->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}" style="display: flex; align-items: center; gap: 12px; min-height: 44px; border: 0; background: transparent; padding: 0; text-align: left; cursor: pointer; font: inherit; color: inherit">
                                    @if ($url = $logoUrl($a))
                                        <span style="display: inline-flex; width: 58px; height: 36px; flex-shrink: 0; box-sizing: border-box; padding: 3px; border-radius: 8px; background: var(--sta-bg-logo, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); align-items: center; justify-content: center"><img src="{{ $url }}" alt="{{ $a->name }} logo" loading="lazy" style="width: 100%; height: 100%; object-fit: contain" /></span>
                                    @else
                                        <span style="width: 36px; height: 36px; border-radius: 50%; background: {{ Sta::avatarColour($code($a)) }}; color: var(--sta-fg-on-brand, #FFFFFF); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px; flex-shrink: 0">{{ $code($a) }}</span>
                                    @endif
                                    <span style="display: flex; flex-direction: column">
                                        <span style="font-weight: 600">{{ $a->name }}</span>
                                        <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ $a->email }}</span>
                                    </span>
                                </button>
                            </td>
                            <td style="padding: 10px 12px"><span style="{{ $pills[$state] }}">{{ $admin }}</span></td>
                            <td style="padding: 10px 12px; font-weight: 600; color: {{ $editors === 2 ? 'var(--sta-fg-text, #0F172A)' : 'var(--sta-fg-muted, #475569)' }}">{{ $editors }} / 2</td>
                            <td style="padding: 10px 20px; color: var(--sta-fg-strong, #334155)">{{ $a->sports->pluck('name')->implode(' · ') ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($records->isEmpty())
                <p style="margin: 0; padding: 24px 20px; color: var(--sta-fg-muted, #475569)">No associations match this search.</p>
            @endif
        </div>
    </section>

    @if ($sel)
        <aside aria-labelledby="detail-title" wire:key="assoc-aside-{{ $sel->id }}" style="flex: 1 1 400px; min-width: 0; display: flex; flex-direction: column; gap: 16px">
            <section style="background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px; padding: 18px 20px; display: flex; flex-direction: column; gap: 14px">
                <div style="display: flex; align-items: center; gap: 14px">
                    @if ($url = $logoUrl($sel))
                        <span style="display: inline-flex; width: 90px; height: 56px; flex-shrink: 0; box-sizing: border-box; padding: 3px; border-radius: 12px; background: var(--sta-bg-logo, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); align-items: center; justify-content: center"><img src="{{ $url }}" alt="{{ $sel->name }} logo" style="width: 100%; height: 100%; object-fit: contain" /></span>
                    @else
                        <div role="img" aria-label="Association logo" style="width: 56px; height: 56px; border-radius: 12px; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF); display: flex; align-items: center; justify-content: center; font-family: 'Space Grotesk', sans-serif; font-weight: 700; flex-shrink: 0">{{ $code($sel) }}</div>
                    @endif
                    <div style="display: flex; flex-direction: column; gap: 2px; min-width: 0">
                        <h2 id="detail-title" style="margin: 0; font-family: 'Space Grotesk', sans-serif; font-size: 19px; font-weight: 600">{{ $sel->name }}</h2>
                        <span style="font-size: 13px; color: var(--sta-fg-muted, #475569)">{{ collect([$sel->email, $sel->phone])->filter()->implode(' · ') }}</span>
                    </div>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 8px">
                    <a href="{{ \App\Filament\Resources\Associations\AssociationResource::getUrl('edit', ['record' => $sel]) }}" style="min-height: 40px; padding: 0 14px; border-radius: 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); background: var(--sta-bg-surface, #FFFFFF); color: var(--sta-fg-text, #0F172A); font: 600 13px Inter, sans-serif; display: inline-flex; align-items: center; text-decoration: none; box-sizing: border-box">Edit details</a>
                    <button type="button" wire:click="mountTableAction('invite', '{{ $sel->getKey() }}')" style="min-height: 40px; padding: 0 14px; border-radius: 10px; border: 0; background: var(--sta-bg-brand, #1F3A5F); color: var(--sta-fg-on-brand, #FFFFFF); font: 600 13px Inter, sans-serif; cursor: pointer">Invite admin</button>
                </div>
            </section>

            <section aria-labelledby="users-title" style="background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px">
                <h3 id="users-title" style="margin: 0; padding: 14px 20px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0); font-family: 'Space Grotesk', sans-serif; font-size: 16px; font-weight: 600">Users</h3>
                <ul style="list-style: none; margin: 0; padding: 0">
                    @foreach ($sel->users as $u)
                        <li wire:key="assoc-user-{{ $u->id }}" style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; padding: 10px 20px; border-top: 1px solid var(--sta-bd-row, #EEF2F6)">
                            <span style="display: flex; flex-direction: column; flex: 1 1 150px; min-width: 0">
                                <span style="font-weight: 600">{{ $u->name }}</span>
                                <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ $roleLabel($u->role) }} · {{ $u->email }}</span>
                            </span>
                            <span style="{{ $u->is_active ? $pills['active'] : $pills['off'] }}">{{ $u->is_active ? 'Active' : 'Deactivated' }}</span>
                            @if ($u->id !== auth()->id())
                                @if ($u->is_active)
                                    <button type="button" wire:click="mountAction('resetUserPassword', { id: {{ $u->id }} })" aria-label="Send {{ $u->name }} a password reset link" style="{{ $small }}; color: var(--sta-fg-strong, #334155)">Reset password</button>
                                @endif
                                <button type="button" wire:click="mountAction('{{ $u->is_active ? 'deactivateUser' : 'activateUser' }}', { id: {{ $u->id }} })" aria-label="{{ ($u->is_active ? 'Deactivate ' : 'Activate ').$u->name }}" style="{{ $small }}; color: var(--sta-fg-strong, #334155)">{{ $u->is_active ? 'Deactivate' : 'Activate' }}</button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            <section aria-labelledby="inv-title" style="background: var(--sta-bg-surface, #FFFFFF); border: 1px solid var(--sta-bd-line, #E2E8F0); border-radius: 14px">
                <h3 id="inv-title" style="margin: 0; padding: 14px 20px; border-bottom: 1px solid var(--sta-bd-line, #E2E8F0); font-family: 'Space Grotesk', sans-serif; font-size: 16px; font-weight: 600">Invitations</h3>
                @forelse ($sel->invitations as $inv)
                    @php $link = $this->inviteLinks[$sel->id.':'.mb_strtolower($inv->email)] ?? null; @endphp
                    <div wire:key="assoc-inv-{{ $inv->id }}" style="padding: 14px 20px; display: flex; flex-direction: column; gap: 10px{{ $loop->first ? '' : '; border-top: 1px solid var(--sta-bd-row, #EEF2F6)' }}">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px">
                            <span style="display: flex; flex-direction: column">
                                <span style="font-weight: 600">{{ $inv->email }}</span>
                                <span style="font-size: 12px; color: var(--sta-fg-muted, #475569)">{{ $roleLabel($inv->role) }} · expires {{ $inv->expires_at->format('j M Y') }}</span>
                            </span>
                            <span style="{{ $pills['pending'] }}">Pending</span>
                        </div>
                        <label x-data style="display: flex; flex-direction: column; gap: 6px; font-size: 12px; font-weight: 600; color: var(--sta-fg-muted, #475569)">Invitation link (single use, 7 days)
                            <span style="display: flex; gap: 8px">
                                <input x-ref="link" readonly value="{{ $link ?? url('/invitations').'/••••••' }}" style="flex: 1; min-width: 0; min-height: 40px; padding: 0 10px; border: 1px solid var(--sta-bd-line-strong, #CBD5E1); border-radius: 10px; font: 13px Inter, sans-serif; color: var(--sta-fg-strong, #334155); background: var(--sta-bg-surface-alt, #F8FAFC)">
                                @if ($link)
                                    <button type="button" x-on:click="navigator.clipboard.writeText($refs.link.value); $el.textContent = 'Copied'" style="{{ $small }}">Copy</button>
                                @else
                                    <button type="button" disabled title="The link is shown only on the page that sent it. Resend email for a new link." style="{{ $small }}; color: var(--sta-fg-faint, #94A3B8); cursor: not-allowed">Copy</button>
                                @endif
                            </span>
                        </label>
                        <div style="display: flex; gap: 8px">
                            <button type="button" wire:click="mountAction('resendInvite', { id: {{ $inv->id }} })" style="{{ $small }}">Resend email</button>
                            <button type="button" wire:click="mountAction('revokeInvite', { id: {{ $inv->id }} })" style="{{ $small }}; border-color: var(--sta-bd-red-300, #FCA5A5); color: var(--sta-fg-red-800, #991B1B)">Revoke</button>
                        </div>
                    </div>
                @empty
                    <p style="margin: 0; padding: 14px 20px; color: var(--sta-fg-muted, #475569)">No pending invitations.</p>
                @endforelse
            </section>
        </aside>
    @endif
</div>

{{-- The Filament table (which normally renders the action modals) is not drawn on this page. --}}
<x-filament-actions::modals />
