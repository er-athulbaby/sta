@props(['association' => null, 'size' => 'md'])
@if ($association === null)
    <span {{ $attributes->class(['sta-avatar', 'sta-avatar-'.$size => $size !== 'md']) }} aria-hidden="true">?</span>
@elseif ($association->logo_path)
    <span {{ $attributes->class(['sta-logo', 'sta-logo-'.$size => $size !== 'md']) }}>
        <img src="{{ \App\Filament\Shared\AttachmentActions::url($association->logo_path) }}" alt="{{ $association->name }} logo" loading="lazy">
    </span>
@else
    <span {{ $attributes->class(['sta-avatar', 'sta-avatar-'.$size => $size !== 'md']) }}
          style="background: {{ \App\Support\Sta::avatarColour($association->code ?? $association->name) }}" aria-hidden="true">{{ $association->code ?? \App\Support\Sta::initials($association->name) }}</span>
@endif
