<!DOCTYPE html>
<html>
<head><meta charset="utf-8">@include('pdf.partials.styles')
<style>
    .events th, .events td { padding: 8px 12px; }
    .events .head th, .events .total th, .events .total td { padding: 9px 12px; }
    .events tbody th { text-align: left; font-weight: normal; }
    .events .total th { font-weight: bold; }
    .events .num { text-align: center; font-weight: bold; }
    .events .off { background: #D9DEE5; }
    .events .first th, .events .first td { border-top: 2px solid #0F172A; }
    .small th, .small td { padding: 8px 10px; }
    .small td { text-align: center; font-weight: bold; }
    .pbox { border: 1px solid #94A3B8; padding: 10px 12px; vertical-align: top; }
    .note { font-size: 12px; color: #475569; }
</style>
</head>
<body>
@include('pdf.partials.footer')
@include('pdf.partials.header', ['phase' => $phase ?? 2, 'title' => 'Entry by Event — '.$sportName])
<table class="grid events" style="margin-top: 16px">
    <thead>
        <tr class="head">
            <th scope="col" style="text-align: left">Event</th>
            <th scope="col" class="c" style="width: 86px">Allowed</th>
            <th scope="col" class="c" style="width: 96px">Male</th>
            <th scope="col" class="c" style="width: 96px">Female</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $r)
        <tr>
            <th scope="row">{{ $r['event'] }}</th>
            <td class="c" style="color: #334155">{{ ($r['max'] ?? null) !== null ? 'Up to '.$r['max'] : '–' }}</td>
            @if($r['gender'] === 'mixed')
                <td class="num" colspan="2">{{ $r['count'] }} (mixed)</td>
            @else
                <td class="num {{ $r['gender'] === 'men' ? '' : 'off' }}">{{ $r['gender'] === 'men' ? $r['count'] : '' }}</td>
                <td class="num {{ $r['gender'] === 'women' ? '' : 'off' }}">{{ $r['gender'] === 'women' ? $r['count'] : '' }}</td>
            @endif
        </tr>
        @endforeach
        <tr class="total first"><th scope="row" colspan="2">Event entries</th><td class="num">{{ $totals['men'] }}</td><td class="num">{{ $totals['women'] }}</td></tr>
        <tr class="total"><th scope="row" colspan="2">All events (incl. mixed)</th><td class="num" colspan="2">{{ $totals['total'] }} {{ $totals['total'] === 1 ? 'entry' : 'entries' }}</td></tr>
    </tbody>
</table>
<div class="note" style="margin-top: 16px">Grey cells: event not open to that gender.</div>
@php
    $male = $participants['male'] ?? null;
    $female = $participants['female'] ?? null;
@endphp
<table style="width: 100%; margin-top: 16px"><tr>
    <td style="width: 343px; padding: 0 12px 0 0; vertical-align: top">
        <div class="cap">Total number of participants</div>
        <table class="grid small">
            <thead><tr class="head"><th scope="col">Male</th><th scope="col">Female</th><th scope="col">Total</th></tr></thead>
            <tbody><tr><td>{{ $male ?? '–' }}</td><td>{{ $female ?? '–' }}</td><td>{{ $male === null && $female === null ? '–' : (int) $male + (int) $female }}</td></tr></tbody>
        </table>
    </td>
    <td style="padding: 0; vertical-align: top">
        <div class="cap">Expected medals</div>
        <table class="grid small">
            <thead><tr class="head"><th scope="col">Gold</th><th scope="col">Silver</th><th scope="col">Bronze</th></tr></thead>
            <tbody><tr><td>{{ $medals['gold'] ?? '–' }}</td><td>{{ $medals['silver'] ?? '–' }}</td><td>{{ $medals['bronze'] ?? '–' }}</td></tr></tbody>
        </table>
    </td>
</tr></table>
<div class="note" style="margin-top: 16px">Participants are different athletes (one athlete can enter several events); the sport limits apply to these totals. Medals are not linked to an athlete or event.</div>
<table style="width: 100%; margin-top: 16px"><tr>
    <td class="pbox">
        <div class="cap" style="padding-bottom: 4px">Entry policy</div>
        <div style="font-size: 12px; line-height: 1.5em">{{ $gameSport->entry_policy ?: 'Each event allows the number of athletes shown above.' }}</div>
    </td>
    <td style="width: 12px; padding: 0"></td>
    <td class="pbox" style="width: 194px">
        <div class="cap" style="padding-bottom: 4px">Age eligibility</div>
        <div style="font-size: 12px; line-height: 1.5em">Born from: {{ $gameSport->dob_from?->format('j M Y') ?? 'no lower limit' }}<br>Born to: {{ $gameSport->dob_to?->format('j M Y') ?? 'no upper limit' }}</div>
    </td>
</tr></table>
@include('pdf.partials.signoff', ['boxHeight' => 40])
</body>
</html>
