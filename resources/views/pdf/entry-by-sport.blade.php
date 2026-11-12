<!DOCTYPE html>
<html>
<head><meta charset="utf-8">@include('pdf.partials.styles')
<style>
    .sports th, .sports td { padding: 12px; }
    .sports .head th, .sports .total th, .sports .total td { padding: 10px 12px; }
    .x { text-align: center; font-weight: bold; font-size: 15px; }
</style>
</head>
<body>
@include('pdf.partials.footer')
@include('pdf.partials.header', ['phase' => 1, 'title' => 'Entry by Sport'])
<p style="margin: 18px 0 0; font-size: 13px; line-height: 1.55em; color: #334155">For each sport you were invited to, tell STA whether you plan to take part. This is not yet an entry: numbers per event are given in Phase 2 (Entry by Event).</p>
<table class="grid sports" style="margin-top: 18px">
    <thead>
        <tr class="head">
            <th scope="col" style="text-align: left">Sport</th>
            <th scope="col" class="c" style="width: 46px">Yes</th>
            <th scope="col" class="c" style="width: 46px">No</th>
            <th scope="col" class="c" style="width: 46px">Maybe</th>
            <th scope="col" style="text-align: left">Note</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $r)
        <tr>
            <th scope="row" style="text-align: left">{{ $r['sport'] }}</th>
            @foreach(['yes', 'no', 'maybe'] as $v)<td class="x">{{ ($r['value'] ?? null) === $v ? 'X' : '' }}</td>@endforeach
            <td style="color: #334155">{{ $r['note'] ?? (($r['value'] ?? null) === null ? $r['answer'] : '') }}</td>
        </tr>
        @endforeach
        @php $tally = collect($rows)->countBy(fn ($r) => $r['value'] ?? 'none'); @endphp
        <tr class="total">
            <th scope="row" style="text-align: left">Total</th>
            <td class="c" style="font-weight: bold">{{ $tally['yes'] ?? 0 }}</td>
            <td class="c" style="font-weight: bold">{{ $tally['no'] ?? 0 }}</td>
            <td class="c" style="font-weight: bold">{{ $tally['maybe'] ?? 0 }}</td>
            <td style="color: #334155">{{ count($rows) }} {{ count($rows) === 1 ? 'sport' : 'sports' }} invited</td>
        </tr>
    </tbody>
</table>
<table style="margin-top: 18px; font-size: 13px">
    <tr><td class="muted" style="width: 150px; padding: 0 12px 6px 0">Prepared by</td><td style="padding: 0 0 6px">{{ $preparedBy ?? '–' }}</td></tr>
    <tr><td class="muted" style="padding: 0 12px 6px 0">Approved by</td><td style="padding: 0 0 6px">{{ $approvedBy ?? '–' }}</td></tr>
    <tr><td class="muted" style="padding: 0 12px 0 0">Phase 1 deadline</td><td>{{ $deadline ?? '–' }}</td></tr>
</table>
@include('pdf.partials.signoff', ['boxHeight' => 44])
</body>
</html>
