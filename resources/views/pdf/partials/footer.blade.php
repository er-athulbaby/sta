{{-- First element in <body>, so dompdf repeats it on every page. --}}
<div class="footer">
    <table><tr>
        <td>Reference {{ $referenceNo }} · generated {{ now()->timezone('Asia/Bahrain')->format('j M Y') }}</td>
        <td style="text-align: right">{{-- "Page N of M" is drawn here by PdfContext::pdf() --}}</td>
    </tr></table>
</div>
