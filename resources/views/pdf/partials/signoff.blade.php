{{-- Portrait declaration and signature grid (PdfPhase1/2): Name / Position over Signature / Date, with a dashed Stamp box spanning both rows. --}}
<div style="page-break-inside: avoid">
<p style="margin: 18px 0 12px; font-size: 12px; line-height: 1.55em"><strong>Declaration.</strong> {{ $declaration }}</p>
<table class="sign" style="width: 100%">
    <tr>
        <td class="box" style="width: 259px; height: {{ $boxHeight }}px">Name</td><td style="width: 10px"></td>
        <td class="box" style="width: 259px">Position</td><td style="width: 10px"></td>
        <td class="box stamp" rowspan="3" style="width: 102px">Stamp</td>
    </tr>
    <tr><td colspan="4" style="height: 10px; padding: 0"></td></tr>
    <tr>
        <td class="box" style="height: {{ $boxHeight }}px">Signature</td><td></td>
        <td class="box">Date</td><td></td>
    </tr>
</table>
</div>
