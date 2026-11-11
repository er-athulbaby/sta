@props(['phase', 'label' => null])
<span {{ $attributes->class(['sta-phase-chip', 'sta-ph-'.$phase]) }}>{{ $label ?? 'Phase '.$phase }}</span>
