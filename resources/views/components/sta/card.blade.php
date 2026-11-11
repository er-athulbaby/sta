@props(['heading' => null, 'description' => null, 'band' => null, 'headingTag' => 'h2'])
<section {{ $attributes->class(['sta-card', 'sta-band sta-band-'.$band => $band]) }}>
    @if (filled($heading) || isset($actions))
        <div class="sta-card-head">
            <div class="sta-card-head-text">
                @if (filled($heading))
                    <{{ $headingTag }} class="sta-card-title">@isset($icon)<span class="sta-band-icon" aria-hidden="true">{{ $icon }}</span>@endisset{{ $heading }}</{{ $headingTag }}>
                @endif
                @if (filled($description))<span class="sta-card-sub">{{ $description }}</span>@endif
            </div>
            @isset($actions)<div class="sta-card-actions">{{ $actions }}</div>@endisset
        </div>
    @endif
    {{ $slot }}
    @isset($footer)<div class="sta-card-foot">{{ $footer }}</div>@endisset
</section>
