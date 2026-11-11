@props(['status'])
<span {{ $attributes->class(['sta-pill', 'sta-pill-'.$status]) }}>{{ $slot }}</span>
