<!DOCTYPE html>
<html>
@php
    $title ??= 'Long List';
    $phase ??= $title === 'Short List' ? 4 : 3;
    $sportNames = collect($athletes)->pluck('sport')->unique()->implode(', ');
@endphp
<head><meta charset="utf-8">@include('pdf.partials.styles')
<style>
    @page { margin: 40px 40px 72px; }
    body { font-size: 12px; }
    .brand td { padding-bottom: 8px; }
    .name { font-size: 13px; }
    h1 { margin: 0; font-size: 24px; }
    h3 { margin: 10px 0 4px; font-size: 13px; font-weight: bold; }
    .people th, .people td { padding: 5px 8px; }
    .people .head th { text-align: left; white-space: nowrap; }
    .people .head th:first-child { text-align: center; }
    .people td:nth-child(2) { padding: 3px 6px; text-align: left; }
    .people td:nth-child(3), .people td:last-child { font-weight: bold; }
    .people td:last-child { text-align: center; }
    .people td.empty { font-weight: normal; text-align: left; }
    .summary td { border: 1px solid #94A3B8; padding: 2px 10px; font-size: 12px; color: #475569; text-align: center; white-space: nowrap; }
    .summary td strong { display: block; font-size: 14px; color: #0F172A; }
    .summary td.label { background: #F1F5F9; font-weight: bold; color: #0F172A; }
    .sign td.box { height: 52px; padding: 5px 7px; }
    .footer { bottom: -32px; padding-top: 6px; }
</style>
</head>
<body>
@include('pdf.partials.footer')
<table class="brand">
    <tr>
        <td style="width: 34px; height: 46px; padding-right: 10px; text-align: center">@if(! empty($nocLogo))<img src="{{ $nocLogo }}" alt="Bahrain Olympic Committee logo" style="max-width:34px;max-height:46px">@endif</td>
        <td style="width: 330px"><span class="name">Bahrain Olympic Committee</span><br><span class="sub">National Olympic Committee of Bahrain</span></td>
        <td class="c" style="white-space: nowrap">
            <span class="chip chip-{{ $phase }}" style="vertical-align: middle"><span class="dot"></span>Phase {{ $phase }}</span>
            <span style="display: inline-block; width: 14px"></span>
            <h1 style="display: inline; vertical-align: middle">{{ $title }}</h1>
        </td>
        <td style="width: 330px; text-align: right"><span class="name">{{ $game->name }}</span><br><span class="sub">{{ \App\Support\PdfContext::dates($game->start_date, $game->end_date) }}</span></td>
        @if(! empty($gameLogo))<td style="width: 46px; padding-left: 10px; text-align: right"><img src="{{ $gameLogo }}" alt="Game logo" style="max-width:46px;max-height:46px"></td>@endif
    </tr>
</table>

<table style="width: 100%; margin-top: 10px">
    <tr>
        <td style="vertical-align: middle; white-space: nowrap">
            @if(! empty($assocLogo))<span class="alogo" style="width: 46px; height: 26px; padding: 2px; border-radius: 6px; vertical-align: middle; margin-right: 10px"><img src="{{ $assocLogo }}" alt="{{ $association->name }} logo" style="max-width:46px;max-height:26px"></span>@endif
            <span style="font-weight: bold; font-size: 14px; vertical-align: middle; white-space: nowrap">{{ $association->name }} ({{ $association->code }})</span>
            @if($sportNames !== '')<span class="muted" style="vertical-align: middle; margin-left: 10px">· {{ $sportNames }}</span>@endif
        </td>
        <td style="vertical-align: middle; padding: 0 16px">
            <table class="summary" role="group" aria-label="Summary" style="margin: 0 auto"><tr>
                <td class="label">Summary</td>
                <td>Athletes Male <strong>{{ $summary['male'] }}</strong></td>
                <td>Athletes Female <strong>{{ $summary['female'] }}</strong></td>
                <td>Athletes Total <strong>{{ $summary['male'] + $summary['female'] }}</strong></td>
                <td>Team officials <strong>{{ $summary['officials'] }}</strong></td>
            </tr></table>
        </td>
        <td class="muted" style="vertical-align: middle; text-align: right; width: 30%">{{ $submittedLine ?? '' }}</td>
    </tr>
</table>

<h3>Athletes</h3>
<table class="grid people">
    <thead>
        <tr class="head">
            <th style="width:30px">S.No</th><th style="width: 36px">Photo</th><th>Name</th><th>Sport</th><th>Event</th><th>Date of birth</th>
            @foreach($athleteItems as $item)<th class="c">{{ $item }}</th>@endforeach
            <th class="c">Passport scan</th>
        </tr>
    </thead>
    <tbody>
    @forelse($athletes as $r)
    <tr>
        <td class="c">{{ $loop->iteration }}</td>
        <td class="c">@if($r['photo'])<img src="{{ $r['photo'] }}" alt="Photo of {{ $r['name'] }}" style="width:24px;height:30px">@endif</td>
        <td>{{ $r['name'] }}</td>
        <td>{{ $r['sport'] }}</td>
        <td>{{ $r['events'] }}</td>
        <td>{{ $r['dob'] }}</td>
        @foreach($r['sizes'] as $size)<td class="c">{{ filled($size) ? $size : '—' }}</td>@endforeach
        <td class="c">{{ $r['passport'] ? 'Yes' : 'No' }}</td>
    </tr>
    @empty
    <tr><td class="empty" colspan="{{ 7 + count($athleteItems) }}">No athletes.</td></tr>
    @endforelse
    </tbody>
</table>

<h3>Team officials</h3>
<table class="grid people">
    <thead>
        <tr class="head">
            <th style="width:30px">S.No</th><th style="width: 36px">Photo</th><th>Name</th><th>Function</th><th>Date of birth</th><th>Phone</th><th>Email</th><th>Sizes</th><th class="c">Passport scan</th>
        </tr>
    </thead>
    <tbody>
    @forelse($officials as $r)
    <tr>
        <td class="c">{{ $loop->iteration }}</td>
        <td class="c">@if($r['photo'])<img src="{{ $r['photo'] }}" alt="Photo of {{ $r['name'] }}" style="width:24px;height:30px">@endif</td>
        <td>{{ $r['name'] }}</td>
        <td>{{ $r['function'] }}</td>
        <td>{{ $r['dob'] }}</td>
        <td>{{ $r['phone'] }}</td>
        <td>{{ $r['email'] }}</td>
        <td style="font-weight: normal">{{ filled($r['sizes']) ? $r['sizes'] : '—' }}</td>
        <td class="c">{{ $r['passport'] ? 'Yes' : 'No' }}</td>
    </tr>
    @empty
    <tr><td class="empty" colspan="9">No team officials.</td></tr>
    @endforelse
    </tbody>
</table>

@if(! empty($justification))
<table style="width: 100%; margin-top: 10px"><tr><td style="border: 1px solid #6D28D9; padding: 8px 12px">
    <table style="width: 100%"><tr>
        <td style="font-size: 13px; font-weight: bold; padding: 0">Justification (over limit)</td>
        @if(! empty($overLimits))<td style="text-align: right; font-weight: bold; color: #5B21B6; padding: 0">Limit exceeded: {{ implode('; ', $overLimits) }}</td>@endif
    </tr></table>
    <div style="margin-top: 4px; line-height: 1.5em; white-space: pre-line">{{ $justification }}</div>
</td></tr></table>
@endif

<table style="width: 100%; margin-top: 10px; page-break-inside: avoid" class="sign"><tr>
    <td style="width: 292px; padding: 0 14px 0 0; vertical-align: top; font-size: 12px; line-height: 1.5em"><strong>Declaration.</strong> {{ $declaration }}</td>
    <td class="box" style="width: 132px">Name</td><td style="width: 8px"></td>
    <td class="box" style="width: 132px">Position</td><td style="width: 8px"></td>
    <td class="box" style="width: 132px">Signature</td><td style="width: 8px"></td>
    <td class="box" style="width: 132px">Date</td><td style="width: 8px"></td>
    <td class="box stamp" style="width: 80px">Stamp</td>
</tr></table>
</body>
</html>
