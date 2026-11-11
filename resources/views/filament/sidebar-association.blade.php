{{-- Portal menu: the association block under the brand (canvas Phase2 / LongList nav): 64x40 white box with the logo, or the code when there is none. --}}
@php $a = filament()->getTenant(); @endphp
@if ($a)
    <div class="sta-assoc-block">
        <span class="sta-assoc-block-logo">
            @if ($a->logo_path)
                <img src="{{ \App\Filament\Shared\AttachmentActions::url($a->logo_path) }}" alt="{{ $a->name }} logo">
            @else
                <span class="sta-assoc-block-initials" style="color: {{ \App\Support\Sta::avatarColour($a->code ?? $a->name) }}" aria-hidden="true">{{ $a->code ?? \App\Support\Sta::initials($a->name) }}</span>
            @endif
        </span>
        <span class="sta-assoc-block-text">
            <span class="sta-assoc-block-name">{{ $a->name }}</span>
            @if ($a->code)<span class="sta-assoc-block-code">{{ $a->code }}</span>@endif
        </span>
    </div>
@endif
