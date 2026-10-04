<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Models\Member;
use App\Models\MemberIncident;
use Illuminate\Http\Request;

class MemberIncidentController extends Controller
{
    use InteractsWithOrganization;

    public function store(Request $request, Member $member)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        $member = $this->member($request, $member);
        $data = $request->validate([
            'type' => ['required', 'in:attendance_failed,membership,payment,reservation,conduct,documentation,other'],
            'severity' => ['required', 'in:info,warning,action_required,critical'],
            'title' => ['required', 'string', 'max:180'],
            'details' => ['nullable', 'string', 'max:3000'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
        ]);
        MemberIncident::create([...$data, 'organization_id' => $member->organization_id, 'location_id' => $member->location_id, 'member_id' => $member->id, 'created_by_user_id' => $request->user()->id, 'source' => 'manual', 'status' => 'open']);

        return back()->with('status', 'Incidencia creada y pendiente de seguimiento.');
    }

    public function resolve(Request $request, Member $member, MemberIncident $incident)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        $member = $this->member($request, $member);
        abort_unless($incident->organization_id === $member->organization_id && $incident->member_id === $member->id, 404);
        $data = $request->validate(['resolution' => ['required', 'string', 'max:3000']]);
        $incident->update(['status' => 'resolved', 'resolution' => $data['resolution'], 'resolved_by_user_id' => $request->user()->id, 'resolved_at' => now()]);

        return back()->with('status', 'Incidencia resuelta y auditada.');
    }
}
