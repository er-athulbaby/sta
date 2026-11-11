{{-- Phase pages: stepper, read-only reason and the signed PDF link. Pass $pdfKey (pdf_paths key or null) and $pdfLabel. --}}
<x-sta.stepper :game="$this->gameModel" :current="$this->submission->phase" />

@if ($readOnlyReason)
    <div class="sta-banner sta-banner-warn" role="status">{{ $readOnlyReason }}</div>
@endif

@php $paths = $this->submission->pdf_paths ?? []; @endphp
@if ($pdfKey && $this->submission->status === \App\Enums\SubmissionStatus::Submitted && isset($paths[$pdfKey]))
    <div><a class="sta-btn" target="_blank" href="{{ \App\Filament\Shared\AttachmentActions::url($paths[$pdfKey]) }}">{{ $pdfLabel }}</a></div>
@endif
