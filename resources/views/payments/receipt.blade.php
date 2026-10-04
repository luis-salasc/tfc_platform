<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo {{ str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT) }} - The Fitness Club</title>
    <style>
        :root{color:#000;background:#fff;font-family:Arial,Helvetica,sans-serif}*{box-sizing:border-box}body{width:80mm;margin:0 auto;padding:4mm;background:#fff;font-size:12px;line-height:1.35}.ticket{width:72mm}.center{text-align:center}.brand{margin:0;font-size:20px;line-height:.88;font-weight:900;letter-spacing:-1px}.brand small{display:block;font-size:8px;letter-spacing:2px;line-height:1.5}.muted{font-size:10px}.rule{border:0;border-top:1px dashed #000;margin:10px 0}.row{display:flex;justify-content:space-between;gap:8px;margin:3px 0}.item{margin:8px 0}.total{font-size:16px;font-weight:900}.footer{margin-top:12px;font-size:10px}.cut{margin:14px -4mm 0;border-top:1px dashed #777}.cut:after{content:'x';display:block;width:20px;margin:-10px auto 0;background:#fff;text-align:center;color:#777}.receipt-actions{width:72mm;margin:20px auto 0;display:grid;gap:8px}.receipt-actions button,.receipt-actions a{width:100%;border:0;padding:11px 12px;background:#075e54;color:#fff;font:700 12px Arial,sans-serif;cursor:pointer;text-decoration:none;text-align:center}.receipt-actions .secondary{background:#111}.receipt-actions .back{background:transparent;color:#111;border:1px solid #111}.notice{width:72mm;margin:14px auto;padding:10px;border:1px solid #075e54;background:#eaf6f3;font-size:11px}.error{border-color:#b91c1c;background:#fff1f2}.modal[hidden]{display:none}.modal{position:fixed;z-index:10;inset:0;display:grid;place-items:center;padding:20px;background:rgba(0,0,0,.58)}.modal-card{width:min(360px,100%);background:#101210;color:#f7f5ee;border:1px solid #345148;padding:24px;box-shadow:0 18px 45px rgba(0,0,0,.45)}.modal-card h2{margin:0 0 8px;font-size:22px}.modal-card p{margin:0 0 18px;color:#c8c3b8}.delivery-list{display:grid;gap:10px;margin:16px 0}.delivery-list label{display:flex;gap:9px;align-items:center;padding:11px;border:1px solid #3b403b;cursor:pointer}.delivery-list input{accent-color:#1556df}.modal-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:18px}.modal-actions button{border:0;padding:10px 13px;font-weight:700;cursor:pointer}.modal-actions .cancel{background:#343634;color:#fff}.modal-actions .process{background:#1556df;color:#fff}@page{size:80mm auto;margin:0}@media print{body{width:80mm;margin:0;padding:4mm}.receipt-actions,.modal,.notice{display:none!important}.ticket{width:72mm}.cut{display:none}}
    </style>
</head>
<body>
    <main class="ticket">
        <header class="center"><p class="brand"><small>THE</small>FITNESS<br>CLUB</p><p class="muted">JUSTIFICANTE DE PAGO</p></header>
        <hr class="rule">
        <div class="row"><span>Recibo</span><strong>#{{ str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT) }}</strong></div>
        <div class="row"><span>Fecha</span><span>{{ $payment->paid_at->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span>Atendido por</span><span>{{ $payment->receivedBy?->name ?? 'Sistema' }}</span></div>
        @if($payment->edited_at)<div class="row"><span>Corregido</span><span>{{ $payment->edited_at->format('d/m/Y H:i') }} - {{ $payment->editedBy?->name ?? '-' }}</span></div>@endif
        <hr class="rule">
        <div class="item"><strong>MIEMBRO</strong><br>{{ $payment->member->first_name }} {{ $payment->member->last_name }}@if($payment->member->national_id)<br><span class="muted">{{ $payment->member->national_id }}</span>@endif</div>
        <hr class="rule">
        <div class="row"><span>Bonos / sesiones</span><span>{{ $payment->sessions_purchased }}</span></div>
        @if($payment->sessions_regularized > 0)
            <div class="row"><span>Regularización de asistencias</span><span>-{{ $payment->sessions_regularized }}</span></div>
            <div class="row"><span>Sesiones añadidas al saldo</span><span>{{ $payment->sessions_purchased - $payment->sessions_regularized }}</span></div>
        @endif
        <div class="row"><span>Forma de pago</span><span>{{ match($payment->payment_method) {'cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'bizum' => 'Bizum', default => 'Otro'} }}</span></div>
        @if($payment->payment_method_detail)<div class="row"><span>Referencia</span><span>{{ $payment->payment_method_detail }}</span></div>@endif
        @if($payment->notes)<div class="item"><strong>NOTAS</strong><br>{{ $payment->notes }}</div>@endif
        <hr class="rule"><div class="row total"><span>TOTAL</span><span>{{ number_format((float) $payment->amount, 2, ',', '.') }} EUR</span></div>
        <hr class="rule"><footer class="center footer">Gracias por confiar en The Fitness Club.<br>Conserva este ticket como justificante.</footer><div class="cut"></div>
    </main>

    @if(session('status'))<p class="notice">{{ session('status') }}</p>@endif
    @if($errors->has('receipt'))<p class="notice error">{{ $errors->first('receipt') }}</p>@endif

    <nav class="receipt-actions" aria-label="Acciones del recibo">
        <button type="button" id="manage-receipt">Gestionar justificante</button>
        <a class="secondary" href="{{ route('payments.edit', $payment) }}">Editar pago completo</a>
        <a class="back" href="{{ route('payments.index') }}">Volver a pagos</a>
    </nav>

    @php($canEmailReceipt = filled($payment->member->email))
    @php($canWhatsappReceipt = filled($payment->member->phone))
    <div class="modal" id="receipt-modal" hidden role="dialog" aria-modal="true" aria-labelledby="receipt-modal-title">
        <form class="modal-card" method="POST" action="{{ route('payments.receipt.resend', $payment) }}" data-whatsapp-url="https://wa.me/{{ preg_replace('/\D+/', '', $payment->member->phone ?? '') }}?text={{ rawurlencode('Hola '.$payment->member->first_name.', te enviamos el justificante de tu pago de '.number_format((float) $payment->amount, 2, ',', '.').' EUR en The Fitness Club.') }}">
            @csrf
            <h2 id="receipt-modal-title">Enviar justificante</h2>
            <p>Selecciona una o varias vias y confirma la operacion.</p>
            <div class="delivery-list">
                <label style="{{ $canEmailReceipt ? '' : 'opacity:.45;cursor:not-allowed' }}"><input type="checkbox" name="delivery_methods[]" value="email" {{ $canEmailReceipt ? '' : 'disabled' }}> Correo{{ $canEmailReceipt ? '' : ' (sin email)' }}</label>
                <label style="{{ $canWhatsappReceipt ? '' : 'opacity:.45;cursor:not-allowed' }}"><input type="checkbox" name="delivery_methods[]" value="whatsapp" {{ $canWhatsappReceipt ? '' : 'disabled' }}> WhatsApp{{ $canWhatsappReceipt ? '' : ' (sin telefono)' }}</label>
                <label><input type="checkbox" name="delivery_methods[]" value="print"> Imprimir ticket</label>
            </div>
            <div class="modal-actions"><button class="cancel" type="button" data-close-modal>Cancelar</button><button class="process" type="submit">Procesar envio</button></div>
        </form>
    </div>

    <script>
        const modal=document.getElementById('receipt-modal');
        document.getElementById('manage-receipt').addEventListener('click',()=>{modal.hidden=false;modal.querySelector('input:not([disabled])')?.focus()});
        document.querySelector('[data-close-modal]').addEventListener('click',()=>modal.hidden=true);
        modal.addEventListener('click',event=>{if(event.target===modal)modal.hidden=true});
        modal.querySelector('form').addEventListener('submit',event=>{
            const form=event.currentTarget;
            const channels=[...form.querySelectorAll('input[name="delivery_methods[]"]:checked')].map(input=>input.value);
            if(!channels.length){event.preventDefault();alert('Selecciona al menos una via de entrega.');return;}
            if(channels.includes('whatsapp')) window.open(form.dataset.whatsappUrl,'_blank','noopener');
        });
    </script>
    @if(session('auto_print_receipt'))<script>window.addEventListener('load',()=>window.print())</script>@endif
</body>
</html>
