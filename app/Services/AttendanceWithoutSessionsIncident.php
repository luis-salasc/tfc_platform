<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberAttendance;
use App\Models\MemberIncident;
use App\Models\Organization;

class AttendanceWithoutSessionsIncident
{
    public function create(Organization $organization, Member $member, MemberAttendance $attendance, ?int $createdByUserId = null): MemberIncident
    {
        return MemberIncident::firstOrCreate(['attendance_id' => $attendance->id], [
            'organization_id' => $organization->id,
            'location_id' => $attendance->location_id,
            'member_id' => $member->id,
            'created_by_user_id' => $createdByUserId,
            'type' => 'attendance_without_sessions',
            'severity' => 'warning',
            'status' => 'open',
            'source' => $createdByUserId ? 'staff' : 'kiosk',
            'title' => 'Asistencia sin sesiones disponibles',
            'details' => 'Asistencia registrada sin sesiones disponibles.',
            'context' => ['sessions_pending' => 1],
            'occurred_at' => $attendance->checked_in_at,
        ]);
    }
}
