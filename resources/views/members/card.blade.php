<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Carnet de acceso | {{ $organization->name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        *{box-sizing:border-box}body{margin:0;background:#eceef2;color:#14171d;font-family:'Instrument Sans',Arial,sans-serif}.card-page{min-height:100vh;padding:clamp(18px,4vw,56px);display:grid;align-content:center;justify-items:center;gap:24px}.card-toolbar{width:min(100%,720px);display:flex;flex-wrap:wrap;justify-content:center;gap:10px}.card-action{min-height:44px;padding:10px 16px;border:1px solid #c8ccd4;border-radius:8px;background:#fff;color:#14171d;font:600 14px inherit;cursor:pointer;text-decoration:none}.card-action--primary{border-color:#155de5;background:#155de5;color:#fff}.card-action--danger{color:#a72c3d}.member-card{width:min(100%,720px);aspect-ratio:85.6/53.98;display:grid;grid-template-columns:minmax(0,1fr) minmax(210px,.72fr);overflow:hidden;border-radius:22px;background:#080b12;color:#f7f8fb;box-shadow:0 24px 70px rgba(20,28,45,.24)}.card-copy{min-width:0;padding:clamp(24px,5vw,48px);display:flex;flex-direction:column;justify-content:space-between;background:radial-gradient(circle at 100% 0,rgba(21,93,229,.3),transparent 42%),#080b12}.card-brand{display:flex;align-items:flex-start;gap:6px}.card-brand span{padding-top:3px;color:#4c88ff;font-size:9px;font-weight:800;letter-spacing:.18em}.card-brand strong{font:900 clamp(24px,4vw,38px)/.72 'TFC Yantramanav',Arial,sans-serif}.card-label{margin:0 0 6px;color:#87adff;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase}.card-name{margin:0;overflow-wrap:anywhere;font-size:clamp(25px,5vw,48px);font-weight:700;letter-spacing:-.04em;line-height:1}.card-help{margin:12px 0 0;color:#bdc3cf;font-size:clamp(12px,1.8vw,16px);line-height:1.4}.card-code{display:grid;place-items:center;padding:clamp(20px,4vw,38px);background:#155de5}.card-qr{width:min(100%,310px);aspect-ratio:1;padding:12px;border-radius:12px;background:#fff}.card-qr svg{width:100%;height:100%;display:block}.card-expiry{width:min(100%,720px);margin:0;color:#5d6573;font-size:13px;text-align:center}.card-notice{width:min(100%,720px);padding:12px 16px;border-radius:8px;background:#dff5e8;color:#17673a;text-align:center}.card-regenerate{display:inline}.card-regenerate button{font:inherit}.card-back{color:#3b4557;font-weight:600;text-decoration:none}@media(max-width:560px){.card-page{align-content:start;padding:16px}.member-card{aspect-ratio:auto;grid-template-columns:1fr;border-radius:16px}.card-copy{min-height:240px}.card-code{padding:26px}.card-qr{width:min(76vw,300px)}.card-toolbar{display:grid;grid-template-columns:1fr 1fr}.card-action{text-align:center}.card-action:first-child{grid-column:1/-1}}
        @media print{
            @page{size:80mm auto;margin:4mm}
            html,body{width:72mm;margin:0!important;padding:0!important;background:#fff!important;color:#000!important}
            .card-page{width:72mm;min-height:0;padding:0;display:block;background:#fff}
            .card-toolbar,.card-expiry,.card-notice{display:none!important}
            .member-card{width:72mm;height:auto;aspect-ratio:auto;display:flex;flex-direction:column;border:0;border-radius:0;background:#fff!important;color:#000!important;box-shadow:none!important;print-color-adjust:economy;-webkit-print-color-adjust:economy}
            .card-copy{min-height:0;padding:3mm 2mm 2mm;display:block;background:#fff!important;color:#000!important;text-align:center}
            .card-brand{display:block;margin-bottom:3mm}.card-brand span{display:block;padding:0;color:#000!important;font-size:2.1mm;letter-spacing:.16em}.card-brand strong{display:block;color:#000!important;font-size:8mm;line-height:.72}
            .card-label{margin:0 0 1.5mm;color:#000!important;font-size:2.5mm;letter-spacing:.12em}.card-name{margin:0;color:#000!important;font-size:6mm;line-height:1.1}.card-help{margin:2mm auto 0;max-width:58mm;color:#000!important;font-size:3.2mm;line-height:1.35}
            .card-code{padding:2mm 0 4mm;display:block;background:#fff!important;text-align:center}.card-qr{width:46mm;height:46mm;margin:0 auto;padding:1.5mm;border:1px solid #000;border-radius:0;background:#fff!important}.card-qr svg{width:100%;height:100%}
            .member-card::after{display:block;padding:2.5mm 0 0;border-top:1px dashed #000;color:#000;content:'THE FITNESS CLUB · CARNET DE ACCESO';font-size:2.4mm;font-weight:600;letter-spacing:.08em;text-align:center}
        }
    </style>
</head>
<body>
<main class="card-page">
    @if(session('status'))<p class="card-notice">{{ session('status') }}</p>@endif
    <section class="member-card" id="member-card" aria-label="Carnet de acceso de {{ $member->public_alias ?: $member->first_name }}">
        <div class="card-copy"><div class="card-brand"><span>THE</span><strong>FITNESS<br>CLUB</strong></div><div><p class="card-label">Carnet de acceso</p><h1 class="card-name">{{ $member->public_alias ?: $member->first_name }}</h1><p class="card-help">Presenta este código QR en el quiosco para registrar tu entrada.</p></div></div>
        <div class="card-code"><div class="card-qr" id="card-qr">{!! $qrSvg !!}</div></div>
    </section>
    <div class="card-toolbar">
        @unless($isPublic)
            <a class="card-action" href="{{ route('miembros.show', $member).'#ficha' }}">Volver a la ficha</a>
            <button class="card-action" type="button" onclick="window.print()">Imprimir</button>
        @endunless
        <button class="card-action card-action--primary" type="button" id="download-card">Descargar imagen</button>
        @unless($isPublic)
            @if($whatsappUrl)<a class="card-action" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Enviar por WhatsApp</a>@else<button class="card-action" disabled title="El miembro no tiene teléfono">WhatsApp sin teléfono</button>@endif
            <form class="card-regenerate" method="POST" action="{{ route('members.card.regenerate', $member) }}" onsubmit="return confirm('El código actual dejará de funcionar inmediatamente. ¿Quieres regenerarlo?')">@csrf<button class="card-action card-action--danger">Regenerar QR</button></form>
        @endunless
    </div>
    <p class="card-expiry">El enlace de envío caduca en 7 días. El QR descargado o impreso seguirá funcionando hasta que el centro lo regenere.</p>
</main>
<script>
document.getElementById('download-card').addEventListener('click', async () => {
    await document.fonts?.ready;
    const canvas = document.createElement('canvas'); canvas.width = 1284; canvas.height = 810;
    const context = canvas.getContext('2d');
    context.fillStyle = '#080b12'; context.fillRect(0, 0, 1284, 810);
    const gradient = context.createRadialGradient(720, 0, 20, 720, 0, 620); gradient.addColorStop(0, 'rgba(21,93,229,.42)'); gradient.addColorStop(1, 'rgba(8,11,18,0)'); context.fillStyle = gradient; context.fillRect(0, 0, 760, 810);
    context.fillStyle = '#155de5'; context.fillRect(780, 0, 504, 810);
    context.fillStyle = '#4c88ff'; context.font = '700 20px Arial'; context.fillText('THE', 70, 76);
    context.fillStyle = '#f7f8fb'; context.font = '900 55px Arial'; context.fillText('FITNESS CLUB', 70, 130);
    context.fillStyle = '#87adff'; context.font = '700 20px Arial'; context.fillText('CARNET DE ACCESO', 70, 470);
    context.fillStyle = '#f7f8fb'; context.font = '700 64px Arial'; context.fillText(@json($member->public_alias ?: $member->first_name), 70, 555, 650);
    context.fillStyle = '#bdc3cf'; context.font = '400 25px Arial'; context.fillText('Presenta este QR en el quiosco', 70, 620);
    const svg = new XMLSerializer().serializeToString(document.querySelector('#card-qr svg'));
    const blob = new Blob([svg], {type:'image/svg+xml'}); const image = new Image(); const url = URL.createObjectURL(blob);
    image.onload = () => { context.fillStyle='#fff'; context.fillRect(850, 174, 364, 364); context.drawImage(image, 862, 186, 340, 340); URL.revokeObjectURL(url); canvas.toBlob(file => { const link=document.createElement('a'); link.href=URL.createObjectURL(file); link.download=@json('carnet-'.Str::slug($member->public_alias ?: $member->first_name).'.png'); link.click(); setTimeout(()=>URL.revokeObjectURL(link.href),1000); }, 'image/png'); }; image.src = url;
});
</script>
</body>
</html>
