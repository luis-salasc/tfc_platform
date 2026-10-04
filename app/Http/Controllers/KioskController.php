<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Enums\SessionMovementType;
use App\Models\Member;
use App\Models\MemberAttendance;
use App\Models\Organization;
use App\Models\SessionMovement;
use App\Services\AttendanceWithoutSessionsIncident;
use App\Services\SessionLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KioskController extends Controller
{
    public function __construct(private readonly AttendanceWithoutSessionsIncident $incidents, private readonly SessionLedger $ledger) {}

    public function show(Organization $organization)
    {
        abort_unless($organization->is_active, 404);

        return view('kiosk.show', compact('organization'));
    }

    public function store(Request $request, Organization $organization)
    {
        abort_unless($organization->is_active, 404);
        $data = $request->validate(['code' => ['required', 'string', 'max:100']]);

        if (! Str::isUuid($data['code'])) {
            return $this->respond($request, [
                'state' => 'error',
                'title' => 'C'."\u{00F3}".'digo no v'."\u{00E1}".'lido',
                'message' => 'No hemos podido leer el c'."\u{00F3}".'digo QR. Int'."\u{00E9}".'ntalo de nuevo o avisa a tu entrenador.',
            ], 422);
        }

        $member = Member::forOrganization($organization)->where('check_in_token', $data['code'])->first();
        if (! $member || ! in_array($member->status, [MemberStatus::Registered, MemberStatus::Active], true)) {
            return $this->respond($request, [
                'state' => 'error',
                'title' => 'Entrada no confirmada',
                'message' => 'No hemos podido validar tu acceso. Avisa a tu entrenador.',
            ], 422);
        }

        $result = DB::transaction(function () use ($organization, $member): array {
            $member = Member::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            $displayName = trim($member->public_alias ?: $member->first_name);

            if ($member->attendances()->whereDate('checked_in_at', today())->lockForUpdate()->exists()) {
                return [
                    'state' => 'duplicate',
                    'title' => "{$displayName}, entrada ya registrada",
                    'message' => 'Tu entrada de hoy ya est'."\u{00E1}".' confirmada.',
                ];
            }

            $requiresSettlement = $this->ledger->mathematicalBalance($member) <= 0;
            $attendance = MemberAttendance::create([
                'organization_id' => $organization->id,
                'location_id' => $member->location_id,
                'member_id' => $member->id,
                'checked_in_at' => now(),
                'requires_settlement' => $requiresSettlement,
            ]);
            SessionMovement::create([
                'member_id' => $member->id,
                'quantity' => -1,
                'type' => SessionMovementType::Attendance,
                'source_type' => MemberAttendance::class,
                'source_id' => $attendance->id,
                'occurred_at' => $attendance->checked_in_at,
            ]);
            $member->update(['sessions_remaining' => max($this->ledger->mathematicalBalance($member), 0)]);
            if ($member->status === MemberStatus::Registered) {
                $member->update(['status' => MemberStatus::Active]);
            }
            if (! $requiresSettlement) {
                $remaining = $member->refresh()->sessions_remaining;
                $birthday = $member->birth_date?->isBirthday() ?? false;
                $welcome = "Bienvenido/a, {$displayName}";
                $birthdayTitle = "\u{00A1}Feliz cumplea\u{00F1}os, {$displayName}!";

                return [
                    'state' => $birthday ? 'celebration' : 'success',
                    'title' => $birthday ? $birthdayTitle : $welcome,
                    'message' => $birthday
                        ? 'Disfruta tu d'."\u{00ED}"."a. Te quedan {$remaining} sesiones."
                        : "Te quedan {$remaining} sesiones.",
                ];
            }

            $this->incidents->create($organization, $member, $attendance);

            return [
                'state' => 'warning',
                'title' => "Bienvenido/a, {$displayName}",
                'message' => 'No tienes sesiones disponibles; avisa a tu entrenador.',
            ];
        });

        return $this->respond($request, $result);
    }

    private function respond(Request $request, array $payload, int $status = 200)
    {
        return $request->expectsJson()
            ? response()->json($payload, $status)
            : back()->with($status === 200 ? 'status' : 'error', $payload['message']);
    }
}
