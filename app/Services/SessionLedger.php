<?php

namespace App\Services;

use App\Enums\SessionMovementType;
use App\Models\Member;
use App\Models\MemberAttendance;
use App\Models\SessionMovement;
use Illuminate\Support\Collection;

class SessionLedger
{
    public function mathematicalBalance(Member $member): int
    {
        return (int) $member->sessionMovements()->sum('quantity');
    }

    /**
     * @return array{mathematical_balance: int, available_sessions: int, pending_sessions: int}
     */
    public function summary(Member $member): array
    {
        $balance = $this->mathematicalBalance($member);

        return [
            'mathematical_balance' => $balance,
            'available_sessions' => max($balance, 0),
            'pending_sessions' => $this->pendingAttendances($member)->count(),
        ];
    }

    /**
     * @return Collection<int, array{attendance: MemberAttendance, movement: SessionMovement}>
     */
    public function pendingAttendances(Member $member, bool $lock = false): Collection
    {
        $attendances = $member->attendances()
            ->where('requires_settlement', true)
            ->orderBy('checked_in_at')
            ->orderBy('id');

        if ($lock) {
            $attendances->lockForUpdate();
        }

        return $attendances->get()->map(function (MemberAttendance $attendance) use ($member): ?array {
            $movement = SessionMovement::query()
                ->where('member_id', $member->id)
                ->where('type', SessionMovementType::Attendance)
                ->where('source_type', MemberAttendance::class)
                ->where('source_id', $attendance->id)
                ->first();

            if (! $movement || $movement->settlementsAsAttendance()->exists()) {
                return null;
            }

            return compact('attendance', 'movement');
        })->filter()->values();
    }
}
