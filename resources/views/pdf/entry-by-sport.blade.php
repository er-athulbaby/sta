<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; }
    table { width: 100%; border-collapse: collapse; }
    .grid td, .grid th { border: 1px solid #000; padding: 4px 6px; }
    .c { text-align: center; }
</style>
</head>
<body>
<table>
    <tr>
        <td style="width:20%">@if($nocLogo)<img src="{{ $nocLogo }}" style="max-width:100%;max-height:60px">@endif</td>
        <td class="c">
            <div style="font-size:14px;font-weight:bold">{{ $game->name }}</div>
            <div style="font-size:13px">Entry by Sport</div>
        </td>
        <td style="width:20%;text-align:right">@if($gameLogo)<img src="{{ $gameLogo }}" style="max-width:100%;max-height:60px">@endif</td>
    </tr>
</table>
<p>
    {{ $association->name }} ({{ $association->code }})
    @if($game->start_date) &middot; {{ $game->start_date->format('d M Y') }}@if($game->end_date) - {{ $game->end_date->format('d M Y') }}@endif @endif
    &middot; Reference: {{ $referenceNo }}
</p>
<table class="grid">
    <tr><th style="text-align:left">Sport</th><th style="width:25%">Answer</th></tr>
    @foreach($rows as $r)
    <tr><td>{{ $r['sport'] }}</td><td class="c">{{ $r['answer'] }}</td></tr>
    @endforeach
</table>
<p style="margin-top:14px">{{ $declaration }}</p>
<table class="grid" style="margin-top:10px">
    <tr>
        @foreach(['Name', 'Position', 'Signature', 'Date', 'Stamp'] as $label)
        <td style="height:60px;width:20%;vertical-align:top">{{ $label }}</td>
        @endforeach
    </tr>
</table>
<p style="margin-top:14px;font-size:9px">Reference {{ $referenceNo }} &middot; generated {{ now()->format('d M Y H:i') }}</p>
</body>
</html>
