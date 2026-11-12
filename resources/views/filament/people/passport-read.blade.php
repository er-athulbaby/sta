{{-- Canvas inline result of "Read details from passport" (green role="status"; amber when some values could not be confirmed;
     an alert when the scan could not be read). Set by ReadPassportAction on the page's $passportRead. --}}
@php $read = property_exists($this, 'passportRead') ? $this->passportRead : null; @endphp
@if ($read)
    @php
        [$bg, $ring, $fg] = match ($read['tone']) {
            'ok' => ['var(--sta-bg-green-50, #F0FDF4)', 'var(--sta-bd-green-200, #BBF7D0)', 'var(--sta-fg-green-800, #166534)'],
            'warn' => ['var(--sta-bg-orange-50, #FFF7ED)', 'var(--sta-bd-orange-200, #FED7AA)', 'var(--sta-fg-orange-800, #9A3412)'],
            default => ['var(--sta-bg-red-50, #FEF2F2)', 'var(--sta-bd-red-300, #FCA5A5)', 'var(--sta-fg-red-800, #991B1B)'],
        };
    @endphp
    <div role="{{ $read['tone'] === 'error' ? 'alert' : 'status' }}" style="display: flex; gap: 10px; padding: 12px; border-radius: 10px; background: {{ $bg }}; box-shadow: inset 0 0 0 1px {{ $ring }}; font-size: 13px; color: {{ $fg }}; line-height: 1.5">
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px">@if ($read['tone'] === 'ok')<path d="m5 12 4 4 10-10"/>@else<path d="M12 8v5"/><path d="M12 16h.01"/><circle cx="12" cy="12" r="9"/>@endif</svg>
        <span>{{ $read['text'] }}</span>
    </div>
@endif
