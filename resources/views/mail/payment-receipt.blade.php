<h1>Justificante de pago</h1>
<p>Hola {{ $payment->member->first_name }}, hemos registrado tu pago en The Fitness Club.</p>
<ul>
    <li>Fecha: {{ $payment->paid_at->format('d/m/Y H:i') }}</li>
    <li>Importe: {{ number_format((float) $payment->amount, 2, ',', '.') }} €</li>
    <li>Sesiones añadidas: {{ $payment->sessions_purchased }}</li>
    <li>Forma de pago: {{ ucfirst($payment->payment_method) }}</li>
</ul>
<p>Gracias por confiar en The Fitness Club.</p>
