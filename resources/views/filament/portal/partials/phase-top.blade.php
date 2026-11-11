{{-- Phase pages: stepper, read-only reason and the signed PDF link. Pass $pdfKey (pdf_paths key or null) and $pdfLabel.
     With $canvas (Phase 1 and 2) the stepper is in the header band (phase-header) and the Phase2.dc.html deadline banner is drawn here. --}}
@if ($canvas ?? false)
    @php $deadline = $this->submission->gamePhase()->effectiveDeadline($this->submission); @endphp
    @if ($deadline)
        <div role="status" style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 14px 18px; border-radius: 12px; background: #FFF7ED; color: #9A3412; border: 1px solid #FED7AA">
            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            <span style="font-weight: 600">Deadline {{ $deadline->format('j M Y') }} · {{ \App\Support\Sta::daysLeft($deadline) }}</span>
            @if (! $readOnlyReason && filament()->getId() === 'portal' && $this->submission->status !== \App\Enums\SubmissionStatus::Submitted)
                <span style="color: #7C2D12">Reminder emails go out automatically until you submit.</span>
            @endif
        </div>
    @endif
@else
    <x-sta.stepper :game="$this->gameModel" :current="$this->submission->phase" />
@endif

@if ($readOnlyReason)
    <div class="sta-banner sta-banner-warn" role="status">{{ $readOnlyReason }}</div>
@endif

@php $paths = $this->submission->pdf_paths ?? []; @endphp
@if ($pdfKey && $this->submission->status === \App\Enums\SubmissionStatus::Submitted && isset($paths[$pdfKey]))
    <div><a class="sta-btn" target="_blank" href="{{ \App\Filament\Shared\AttachmentActions::url($paths[$pdfKey]) }}">{{ $pdfLabel }}</a></div>
@endif
