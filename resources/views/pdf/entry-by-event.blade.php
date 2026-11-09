<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; }
    table { width: 100%; border-collapse: collapse; }
    .grid td, .grid th { border: 1px solid #000; padding: 4px 6px; }
    .shade { background: #d9d9d9; }
    .c { text-align: center; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width:20%">@if($nocLogo)<img src="{{ $nocLogo }}" style="height:60px">@endif</td>
        <td class="c">
            <div style="font-size:14px;font-weight:bold">{{ $game->name }}</div>
            <div style="font-size:13px">Entry by Event</div>
            <div style="font-size:13px;font-weight:bold">{{ $sportName }}</div>
        </td>
        <td style="width:20%;text-align:right">@if($gameLogo)<img src="{{ $gameLogo }}" style="height:60px">@endif</td>
    </tr>
</table>
<p>
    {{ $association->name }}
    @if($game->start_date) &middot; {{ $game->start_date->format('d M Y') }}@if($game->end_date) - {{ $game->end_date->format('d M Y') }}@endif @endif
    &middot; Reference: {{ $referenceNo }}
</p>
@if($gameSport->entry_policy)
<div style="border:1px solid #000;padding:6px;margin-bottom:6px"><strong>Entry policy:</strong> {{ $gameSport->entry_policy }}</div>
@endif
@if($gameSport->dob_from || $gameSport->dob_to)
<p><strong>Age eligibility:</strong> born
    @if($gameSport->dob_from && $gameSport->dob_to) between {{ $gameSport->dob_from->format('d M Y') }} and {{ $gameSport->dob_to->format('d M Y') }}
    @elseif($gameSport->dob_from) on or after {{ $gameSport->dob_from->format('d M Y') }}
    @else on or before {{ $gameSport->dob_to->format('d M Y') }} @endif
</p>
@endif
<table class="grid">
    <tr><th style="text-align:left">Event</th><th style="width:15%">Male</th><th style="width:15%">Female</th></tr>
    @foreach($rows as $r)
    <tr>
        <td>{{ $r['event'] }}</td>
        @if($r['gender'] === 'mixed')
            <td class="c" colspan="2">{{ $r['count'] }} (mixed)</td>
        @else
            <td class="c {{ $r['gender'] === 'men' ? '' : 'shade' }}">{{ $r['gender'] === 'men' ? $r['count'] : '' }}</td>
            <td class="c {{ $r['gender'] === 'women' ? '' : 'shade' }}">{{ $r['gender'] === 'women' ? $r['count'] : '' }}</td>
        @endif
    </tr>
    @endforeach
    <tr>
        <td><strong>Total: {{ $totals['total'] }}</strong></td>
        <td class="c"><strong>{{ $totals['men'] }}</strong></td>
        <td class="c"><strong>{{ $totals['women'] }}</strong></td>
    </tr>
</table>
<p style="margin-top:14px">{{ $declaration }}</p>
<table class="grid" style="margin-top:10px">
    <tr>
        @foreach(['Name', 'Position', 'Date', 'Stamp'] as $label)
        <td style="height:60px;width:25%;vertical-align:top">{{ $label }}</td>
        @endforeach
    </tr>
</table>
<p style="margin-top:14px;font-size:9px">Reference {{ $referenceNo }} &middot; generated {{ now()->timezone('Asia/Bahrain')->format('d M Y H:i') }}</p>
</body>
</html>
