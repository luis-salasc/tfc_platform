<?php

namespace App\Mail;

use App\Models\MemberPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public MemberPayment $payment) {}

    public function build(): self
    {
        return $this->subject('Justificante de pago · The Fitness Club')
            ->view('mail.payment-receipt');
    }
}
