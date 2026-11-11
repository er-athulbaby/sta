{{-- Canvas Phase2.dc.html submit block (end of the aside). Pass $pdfName ("Entry by Event" / "Entry by Sport").
     The buttons mount the page's existing actions; STA edit pages only save. --}}
@php
    $portal = filament()->getId() === 'portal';
    $can = fn (string $name) => $portal && ($this->getAction($name, isMounting: false)?->isVisible() ?? false);
    $btn = 'min-height: 48px; border-radius: 10px; cursor: pointer; font: 600 14px Inter, sans-serif; ';
    $secondary = $btn.'flex: 1 1 120px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #0F172A';
    $primary = $btn.'flex: 2 1 180px; border: 0; background: #1F3A5F; color: #FFFFFF; font-size: 15px';
    $isAdmin = auth()->user()->role === \App\Enums\Role::AssocAdmin;
    $adminName = $isAdmin ? null : \App\Models\User::where('association_id', $this->submission->association_id)
        ->where('role', \App\Enums\Role::AssocAdmin)->where('is_active', true)->value('name');
@endphp
<div style="display: flex; flex-direction: column; gap: 10px">
    <div style="display: flex; flex-wrap: wrap; gap: 10px">
        @unless ($readOnlyReason)
            <button type="button" wire:click="save" style="{{ $secondary }}">Save as draft</button>
        @endunless
        @if ($can('submit'))
            <button type="button" wire:click="mountAction('submit')" style="{{ $primary }}">{{ $this->getAction('submit', isMounting: false)->getLabel() }}</button>
        @endif
        @if ($can('return'))
            <button type="button" wire:click="mountAction('return')" style="{{ $secondary }}">Return to draft</button>
        @endif
        @if ($can('approve'))
            <button type="button" wire:click="mountAction('approve')" style="{{ $primary }}">Approve and submit to STA</button>
        @endif
    </div>
    @if ($portal)
        <span style="font-size: 12px; color: #475569; line-height: 1.5">A saved draft is never submitted.
            @if ($isAdmin)
                After you submit, it goes straight to STA.
            @else
                After you submit, {{ $adminName ? $adminName.' (association admin)' : 'your association admin' }} approves it before it goes to STA.
            @endif
            A signed {{ $pdfName }} PDF is generated.</span>
    @endif
</div>
