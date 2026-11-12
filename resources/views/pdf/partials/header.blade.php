{{-- Portrait header (PdfPhase1/2): BOC block and game block over a 2px navy rule, then the phase chip and title beside the association. --}}
<table class="brand">
    <tr>
        <td style="width: 48px; height: 66px; padding-right: 12px; text-align: center">@if(! empty($nocLogo))<img src="{{ $nocLogo }}" alt="Bahrain Olympic Committee logo" style="max-width:40px;max-height:60px">@endif</td>
        <td><span class="name">Bahrain Olympic Committee</span><br><span class="sub">National Olympic Committee of Bahrain</span></td>
        <td style="text-align: right"><span class="name">{{ $game->name }}</span><br><span class="sub">{{ \App\Support\PdfContext::dates($game->start_date, $game->end_date) }}</span></td>
        @if(! empty($gameLogo))<td style="width: 60px; padding-left: 12px; text-align: right"><img src="{{ $gameLogo }}" alt="Game logo" style="max-width:60px;max-height:60px"></td>@endif
    </tr>
</table>
<table style="width: 100%; margin-top: 18px">
    <tr>
        <td style="vertical-align: bottom">
            <span class="chip chip-{{ $phase }}"><span class="dot"></span>Phase {{ $phase }}</span>
            <h1>{{ $title }}</h1>
        </td>
        <td style="vertical-align: bottom; text-align: right; white-space: nowrap">
            <table style="margin-left: auto"><tr>
                @if(! empty($assocLogo))<td style="padding-right: 10px; vertical-align: middle"><span class="alogo"><img src="{{ $assocLogo }}" alt="{{ $association->name }} logo" style="max-width:56px;max-height:32px"></span></td>@endif
                <td style="text-align: left; vertical-align: middle"><span class="sub">Association</span><br><span class="name">{{ $association->name }} ({{ $association->code }})</span></td>
            </tr></table>
        </td>
    </tr>
</table>
