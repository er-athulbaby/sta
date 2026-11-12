{{-- Long List travel and insurance (spec: per game, per person; not drawn on the canvas): the saved insurance with its
     certificate, and the "Travel & insurance" modal for people on the saved list. Expects $p, $d (detail or null), $row, $showTravel. --}}
@if ($d)
    <span>Insurance: {{ $d->insurance_company ?? '–' }}@if ($d->insurance_certificate_path) · <a href="{{ \App\Filament\Shared\AttachmentActions::url($d->insurance_certificate_path) }}" target="_blank" style="font-weight: 600">View certificate</a>@endif</span>
@endif
@if ($showTravel && $row['on_list'])
    <button type="button" wire:click="mountAction('travel', { person: {{ $p->id }} })" style="align-self: flex-start; min-height: 44px; padding: 0; border: 0; background: transparent; color: var(--sta-fg-brand, #1F3A5F); font: 600 13px Inter, sans-serif; text-align: left; white-space: nowrap; cursor: pointer">Travel & insurance</button>
@endif
