<?php

namespace App\Http\Controllers;

use App\Enums\MemberPreRegistrationReviewDecision;
use App\Enums\MemberPreRegistrationStatus;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Models\MemberPreRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MemberPreRegistrationReviewController extends Controller
{
    use InteractsWithOrganization;

    public function store(Request $request, MemberPreRegistration $preRegistration)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        abort_unless($preRegistration->organization_id === $this->organization($request)->id, 404);
        abort_unless($preRegistration->status === MemberPreRegistrationStatus::RequiresReview, 409);

        $data = $request->validate([
            'decision' => ['required', Rule::enum(MemberPreRegistrationReviewDecision::class)],
            'observations' => ['required', 'string', 'max:5000'],
        ]);
        $decision = MemberPreRegistrationReviewDecision::from($data['decision']);

        DB::transaction(function () use ($preRegistration, $decision, $data, $request): void {
            $preRegistration->reviews()->create([
                'decision' => $decision,
                'observations' => $data['observations'],
                'triggering_circumstances' => $preRegistration->triggeringCircumstances(),
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            $preRegistration->update([
                'status' => $decision === MemberPreRegistrationReviewDecision::CanContinue
                    ? MemberPreRegistrationStatus::ReadyForFinalization
                    : MemberPreRegistrationStatus::OnHold,
            ]);
        });

        return to_route('pre-registrations.show', $preRegistration)->with('status', 'Revisión registrada.');
    }
}
