<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Enums\SessionMovementType;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Mail\PaymentReceipt;
use App\Models\Member;
use App\Models\MemberIncident;
use App\Models\MemberPayment;
use App\Models\SessionMovement;
use App\Models\SessionSettlement;
use App\Services\SessionLedger;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class MemberPaymentController extends Controller
{
    use InteractsWithOrganization;

    public function store(Request $request, Member $member, SessionLedger $ledger)
    {
        $this->requirePermission($request, 'payments.manage');
        $member = $this->member($request, $member);
        $data = $request->validate([
            'sessions_purchased' => ['required', 'integer', 'min:1', 'max:10000'],
            'amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'transfer', 'bizum', 'other'])],
            'payment_method_detail' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
            ...$this->deliveryRules(),
        ]);
        if ($payment = MemberPayment::query()->where('idempotency_key', $data['idempotency_key'])->first()) {
            return to_route('payments.receipt', $payment)->with('status', 'Pago ya registrado anteriormente.');
        }

        try {
            [$payment, $regularized] = DB::transaction(function () use ($request, $member, $data, $ledger): array {
                $member = Member::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
                $pendingAttendances = $ledger->pendingAttendances($member, lock: true);
                $payment = $member->payments()->create([
                    'organization_id' => $member->organization_id,
                    'location_id' => $this->locationId($request),
                    'received_by_user_id' => $request->user()->id,
                    ...$data,
                    'delivery_methods' => $data['delivery_methods'] ?? [],
                    'paid_at' => now(),
                ]);
                $paymentMovement = SessionMovement::create([
                    'member_id' => $member->id,
                    'quantity' => $data['sessions_purchased'],
                    'type' => SessionMovementType::Payment,
                    'source_type' => MemberPayment::class,
                    'source_id' => $payment->id,
                    'occurred_at' => $payment->paid_at,
                    'created_by_user_id' => $request->user()->id,
                    'idempotency_key' => $data['idempotency_key'],
                ]);

                $remainingToRegularize = $data['sessions_purchased'];
                $regularized = 0;
                foreach ($pendingAttendances as ['attendance' => $attendance, 'movement' => $attendanceMovement]) {
                    if ($remainingToRegularize === 0) {
                        break;
                    }

                    SessionSettlement::create([
                        'attendance_movement_id' => $attendanceMovement->id,
                        'payment_movement_id' => $paymentMovement->id,
                        'quantity' => 1,
                        'settled_at' => now(),
                        'settled_by_user_id' => $request->user()->id,
                    ]);
                    $regularized++;
                    $remainingToRegularize--;

                    MemberIncident::query()
                        ->where('attendance_id', $attendance->id)
                        ->where('type', 'attendance_without_sessions')
                        ->where('status', 'open')
                        ->update([
                            'status' => 'resolved',
                            'resolved_at' => now(),
                            'resolved_by_user_id' => $request->user()->id,
                            'resolution' => "Regularizada con el pago #{$payment->id}.",
                        ]);
                }

                $payment->update(['sessions_regularized' => $regularized]);
                $member->update(['sessions_remaining' => max($ledger->mathematicalBalance($member), 0)]);

                return [$payment, $regularized];
            });
        } catch (QueryException $exception) {
            $payment = MemberPayment::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if (! $payment) {
                throw $exception;
            }

            return to_route('payments.receipt', $payment)->with('status', 'Pago ya registrado anteriormente.');
        }

        $notice = $regularized > 0
            ? "Pago registrado. Se han regularizado {$regularized} asistencia(s) pendiente(s)."
            : 'Pago registrado y saldo actualizado.';
        if (in_array('email', $data['delivery_methods'] ?? [], true)) {
            if ($member->email) {
                try {
                    Mail::to($member->email)->send(new PaymentReceipt($payment->load('member')));
                    $notice .= ' Justificante enviado por correo.';
                } catch (\Throwable $exception) {
                    report($exception);
                    $notice .= ' No se pudo enviar el justificante.';
                }
            } else {
                $notice .= ' No se envio correo porque el miembro no tiene email.';
            }
        }

        return to_route('payments.receipt', $payment)->with('status', $notice);
    }

    public function receipt(Request $request, MemberPayment $payment)
    {
        $this->requirePermission($request, 'payments.view');
        abort_unless($payment->organization_id === $this->organization($request)->id, 404);

        return view('payments.receipt', ['payment' => $payment->load(['member', 'receivedBy', 'editedBy'])]);
    }

    public function edit(Request $request, MemberPayment $payment)
    {
        $this->requirePermission($request, 'payments.manage');
        abort_unless($payment->organization_id === $this->organization($request)->id, 404);

        return view('payments.edit', compact('payment'));
    }

    public function update(Request $request, MemberPayment $payment)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        abort_unless($payment->organization_id === $this->organization($request)->id, 404);
        if (SessionMovement::query()
            ->where('type', SessionMovementType::Payment)
            ->where('source_type', MemberPayment::class)
            ->where('source_id', $payment->id)
            ->exists()) {
            return back()->withErrors(['payment' => 'Los pagos registrados en el ledger no se pueden corregir todavía.']);
        }
        $data = $request->validate([
            'sessions_purchased' => ['required', 'integer', 'min:1', 'max:10000'],
            'amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'payment_method' => ['required', Rule::in(['cash', 'card', 'transfer', 'bizum', 'other'])],
            'payment_method_detail' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...$this->deliveryRules(),
        ]);

        if ($data['sessions_purchased'] < $payment->sessions_regularized) {
            return back()->withInput()->withErrors([
                'sessions_purchased' => 'No puede ser inferior a las sesiones ya usadas para regularizar asistencias.',
            ]);
        }

        DB::transaction(function () use ($request, $payment, $data): void {
            $member = $payment->member()->lockForUpdate()->firstOrFail();
            $newBalance = $member->sessions_remaining + ($data['sessions_purchased'] - $payment->sessions_purchased);

            $member->update(['sessions_remaining' => max(0, $newBalance)]);
            $payment->update([
                ...$data,
                'delivery_methods' => $data['delivery_methods'] ?? [],
                'edited_by_user_id' => $request->user()->id,
                'edited_at' => now(),
            ]);
        });

        return to_route('payments.receipt', $payment)->with('status', 'Pago corregido y auditoria registrada.');
    }

    public function resendReceipt(Request $request, MemberPayment $payment)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        abort_unless($payment->organization_id === $this->organization($request)->id, 404);
        $data = $request->validate($this->deliveryRules(required: true));
        $payment->load('member');
        $delivery = $data['delivery_methods'];
        $messages = [];

        if (in_array('email', $delivery, true)) {
            if (! $payment->member->email) {
                return back()->withErrors(['receipt' => 'El miembro no tiene un email al que enviar el justificante.']);
            }

            Mail::to($payment->member->email)->send(new PaymentReceipt($payment));
            $messages[] = 'Correo enviado a '.$payment->member->email.'.';
        }

        if (in_array('whatsapp', $delivery, true)) {
            if (! $payment->member->phone) {
                return back()->withErrors(['receipt' => 'El miembro no tiene un telefono para WhatsApp.']);
            }

            $messages[] = 'WhatsApp abierto para confirmar el envio.';
        }

        if (in_array('print', $delivery, true)) {
            $messages[] = 'Preparando la impresion del ticket.';
        }

        return to_route('payments.receipt', $payment)
            ->with('status', implode(' ', $messages))
            ->with('auto_print_receipt', in_array('print', $delivery, true));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function deliveryRules(bool $required = false): array
    {
        return [
            'delivery_methods' => [$required ? 'required' : 'nullable', 'array', ...($required ? ['min:1'] : [])],
            'delivery_methods.*' => [Rule::in(['email', 'whatsapp', 'print'])],
        ];
    }
}
