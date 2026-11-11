@props(['name', 'size' => 'md'])
<span {{ $attributes->class(['sta-avatar', 'sta-avatar-'.$size => $size !== 'md']) }} role="img" aria-label="{{ $name }}">{{ \App\Support\Sta::initials($name) }}</span>
