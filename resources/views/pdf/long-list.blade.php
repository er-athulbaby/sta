<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; }
    table { width: 100%; border-collapse: collapse; }
    .grid td, .grid th { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; }
    .grid th { background: #d9d9d9; text-align: left; }
    .c { text-align: center; }
    h3 { font-size: 11px; margin: 10px 0 4px; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width:20%">@if($nocLogo)<img src="{{ $nocLogo }}" style="max-width:100%;max-height:60px">@endif</td>
        <td class="c">
            <div style="font-size:14px;font-weight:bold">{{ $game->name }}</div>
            <div style="font-size:13px">{{ $title ?? 'Long List' }}</div>
            <div style="font-size:13px;font-weight:bold">{{ $association->name }}</div>
        </td>
        <td style="width:20%;text-align:right">@if($gameLogo)<img src="{{ $gameLogo }}" style="max-width:100%;max-height:60px">@endif</td>
    </tr>
</table>
<p>
    @if($game->start_date){{ $game->start_date->format('d M Y') }}@if($game->end_date) - {{ $game->end_date->format('d M Y') }}@endif &middot; @endif
    Reference: {{ $referenceNo }}
</p>

<h3>Athletes</h3>
<table class="grid">
    <tr>
        <th style="width:50px">Photo</th><th>Name</th><th>Sport</th><th>Event</th><th>Date of birth</th>
        @foreach($athleteItems as $item)<th>{{ $item }}</th>@endforeach
        <th>Passport scan</th>
    </tr>
    @forelse($athletes as $r)
    <tr>
        <td class="c">@if($r['photo'])<img src="{{ $r['photo'] }}" style="width:40px;height:50px">@endif</td>
        <td>{{ $r['name'] }}</td>
        <td>{{ $r['sport'] }}</td>
        <td>{{ $r['events'] }}</td>
        <td>{{ $r['dob'] }}</td>
        @foreach($r['sizes'] as $size)<td class="c">{{ $size }}</td>@endforeach
        <td class="c">{{ $r['passport'] ? 'Yes' : 'No' }}</td>
    </tr>
    @empty
    <tr><td colspan="{{ 6 + count($athleteItems) }}">No athletes.</td></tr>
    @endforelse
</table>

<h3>Team officials</h3>
<table class="grid">
    <tr>
        <th style="width:50px">Photo</th><th>Name</th><th>Function</th><th>Date of birth</th><th>Phone</th><th>Email</th><th>Sizes</th><th>Passport scan</th>
    </tr>
    @forelse($officials as $r)
    <tr>
        <td class="c">@if($r['photo'])<img src="{{ $r['photo'] }}" style="width:40px;height:50px">@endif</td>
        <td>{{ $r['name'] }}</td>
        <td>{{ $r['function'] }}</td>
        <td>{{ $r['dob'] }}</td>
        <td>{{ $r['phone'] }}</td>
        <td>{{ $r['email'] }}</td>
        <td>{{ $r['sizes'] }}</td>
        <td class="c">{{ $r['passport'] ? 'Yes' : 'No' }}</td>
    </tr>
    @empty
    <tr><td colspan="8">No team officials.</td></tr>
    @endforelse
</table>

@if(! empty($justification))
<h3>Justification (over the limit)</h3>
<p style="white-space:pre-line">{{ $justification }}</p>
@endif
<p style="margin-top:14px">{{ $declaration }}</p>
<table class="grid" style="margin-top:10px">
    <tr>
        @foreach(['Name', 'Position', 'Signature', 'Stamp'] as $label)
        <td style="height:60px;width:25%;vertical-align:top">{{ $label }}</td>
        @endforeach
    </tr>
</table>
<p style="margin-top:14px;font-size:8px">Reference {{ $referenceNo }} &middot; generated {{ now()->format('d M Y H:i') }}</p>
</body>
</html>
