{{-- A person's passport scan files through 5-minute links: "{label} · View", or "{label} (2) · View 1 · View 2" for several pages. --}}
@props(['person', 'label' => null, 'linkStyle' => 'font-weight: 600'])
@php
    $scans = $person->passportScans();
    $n = count($scans);
    $links = collect($scans)->map(fn ($path, $i) => '<a href="'.e(\App\Filament\Shared\AttachmentActions::url($path)).'" target="_blank" rel="noopener" aria-label="View passport scan'.($n > 1 ? ' '.($i + 1) : '').' of '.e($person->fullName()).'" style="'.e($linkStyle).'">View'.($n > 1 ? ' '.($i + 1) : '').'</a>');
@endphp
<span>{{ $label ? $label.($n > 1 ? " ({$n})" : '').' · ' : '' }}{!! $links->implode(' · ') !!}</span>
