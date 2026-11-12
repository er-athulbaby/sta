{{-- Shared PDF styles (spec 17, PdfPhase1–4 samples). dompdf renders at 96 dpi, so the sample's px values map 1:1 to A4. Portrait values here; the Long List overrides the page size ones. --}}
@php $font = fn (string $file) => str_replace('\\', '/', resource_path('fonts/'.$file)); @endphp
<style>
    {{-- The samples' fonts, bundled (SIL OFL, resources/fonts): Inter for text, Space Grotesk for the title. --}}
    @font-face { font-family: 'Inter'; font-weight: normal; font-style: normal; src: url("{{ $font('Inter/Inter-Regular.ttf') }}") format('truetype'); }
    @font-face { font-family: 'Inter'; font-weight: bold; font-style: normal; src: url("{{ $font('Inter/Inter-Bold.ttf') }}") format('truetype'); }
    @font-face { font-family: 'Space Grotesk'; font-weight: normal; font-style: normal; src: url("{{ $font('SpaceGrotesk/SpaceGrotesk-Medium.ttf') }}") format('truetype'); }
    @font-face { font-family: 'Space Grotesk'; font-weight: bold; font-style: normal; src: url("{{ $font('SpaceGrotesk/SpaceGrotesk-Bold.ttf') }}") format('truetype'); }
    @page { margin: 48px 48px 90px; }
    body { margin: 0; font-family: 'Inter', 'DejaVu Sans', sans-serif; font-size: 13px; color: #0F172A; }
    table { border-collapse: collapse; }
    .muted { color: #475569; }
    .c { text-align: center; }
    .brand { width: 100%; border-bottom: 2px solid #0F2440; }
    .brand td { padding: 0 0 12px; vertical-align: middle; }
    .name { font-weight: bold; font-size: 14px; }
    .sub { font-size: 12px; color: #475569; }
    .chip { display: inline-block; height: 24px; line-height: 24px; padding: 0 10px; border-radius: 12px; font-size: 12px; font-weight: bold; white-space: nowrap; }
    .dot { display: inline-block; width: 8px; height: 8px; border-radius: 4px; margin-right: 6px; }
    .chip-1 { background: #E0F2FE; color: #075985; } .chip-1 .dot { background: #0369A1; }
    .chip-2 { background: #CCFBF1; color: #115E59; } .chip-2 .dot { background: #0F766E; }
    .chip-3 { background: #FEF3C7; color: #92400E; } .chip-3 .dot { background: #B45309; }
    .chip-4 { background: #EDE9FE; color: #5B21B6; } .chip-4 .dot { background: #6D28D9; }
    h1 { margin: 6px 0 0; font-family: 'Space Grotesk', 'DejaVu Sans', sans-serif; font-size: 30px; line-height: 1.1; font-weight: bold; }
    .alogo { display: inline-block; width: 56px; height: 32px; padding: 3px; border: 1px solid #E2E8F0; border-radius: 8px; text-align: center; }
    .grid { width: 100%; border-collapse: collapse; }
    .grid th, .grid td { border: 1px solid #94A3B8; }
    .grid .head { background: #F1F5F9; }
    .grid .total { background: #F8FAFC; }
    .cap { text-align: left; font-weight: bold; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #334155; padding-bottom: 6px; }
    .sign td.box { border: 1px solid #475569; padding: 6px 8px; font-size: 12px; color: #475569; vertical-align: top; }
    .sign td.stamp { border-style: dashed; }
    .footer { position: fixed; left: 0; right: 0; bottom: -42px; border-top: 1px solid #CBD5E1; padding-top: 8px; font-size: 12px; color: #475569; }
    .footer table { width: 100%; }
    .footer td { padding: 0; }
</style>
