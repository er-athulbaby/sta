{{-- Shared head of the public invitation pages: canvas fonts, colours, brand block, inputs and buttons (no design screen). --}}
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
@vite('resources/css/fonts.css'){{-- self-hosted Inter and Space Grotesk --}}
<style>
    body{font-family:Inter,system-ui,Arial,sans-serif;background:#F5F7FA;color:#0F172A;margin:0;padding:24px;font-size:14px}
    main{max-width:440px;margin:40px auto;background:#fff;padding:28px;border-radius:14px;border:1px solid #E2E8F0}
    .brand{display:flex;align-items:center;gap:12px;margin-bottom:20px}
    .brand-logo{width:40px;height:56px;flex-shrink:0;box-sizing:border-box;padding:4px;border-radius:10px;background:#fff;border:1px solid #E2E8F0;display:flex;align-items:center;justify-content:center}
    .brand-logo img{width:100%;height:100%;object-fit:contain}
    .brand small{display:block;font-size:12px;color:#475569}
    .brand strong{display:block;font-family:'Space Grotesk',sans-serif;font-weight:600;font-size:16px}
    h1{font-family:'Space Grotesk',sans-serif;font-size:24px;font-weight:700;letter-spacing:-.01em;margin:0 0 8px}
    p{color:#475569;line-height:1.55}
    label{display:block;margin-top:14px;font-weight:600;font-size:13px}
    input{width:100%;min-height:44px;padding:0 12px;margin-top:6px;border:1px solid #CBD5E1;border-radius:10px;box-sizing:border-box;font:14px Inter,sans-serif;color:#0F172A}
    input:focus-visible,button:focus-visible{outline:2px solid #1F3A5F;outline-offset:2px}
    button{margin-top:20px;width:100%;min-height:44px;background:#1F3A5F;color:#fff;border:0;border-radius:10px;font:600 14px Inter,sans-serif;cursor:pointer}
    .error{color:#B91C1C;font-size:13px;font-weight:600;margin:6px 0 0}
</style>
