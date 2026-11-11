{{-- Portal menu: the association block under the brand (canvas Phase2 / LongList nav). --}}
@php $a = filament()->getTenant(); @endphp
@if ($a)
    <div class="sta-assoc-block">
        @if ($a->logo_path)
            <span class="sta-assoc-block-logo"><img src="{{ \App\Filament\Shared\AttachmentActions::url($a->logo_path) }}" alt="{{ $a->name }} logo"></span>
        @else
            <span class="sta-avatar" style="background: {{ \App\Support\Sta::avatarColour($a->code ?? $a->name) }}" aria-hidden="true">{{ $a->code ?? \App\Support\Sta::initials($a->name) }}</span>
        @endif
        <span class="sta-assoc-block-text">
            <span class="sta-assoc-block-name">{{ $a->name }}</span>
            @if ($a->code)<span class="sta-assoc-block-code">{{ $a->code }}</span>@endif
        </span>
    </div>
@endif
