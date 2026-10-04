<?php

namespace App\Services;

use App\Enums\MemberPreRegistrationStatus;
use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberPreRegistration;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MemberPreRegistrationFinalizer
{
    public function finalize(MemberPreRegistration $candidate, Organization $organization, User $user): Member
    {
        return DB::transaction(function () use ($candidate, $organization, $user): Member {
            $preRegistration = MemberPreRegistration::query()
                ->forOrganization($organization)
                ->lockForUpdate()
                ->findOrFail($candidate->id);

            if ($preRegistration->member_id) {
                return Member::forOrganization($organization)->findOrFail($preRegistration->member_id);
            }

            abort_unless($preRegistration->status === MemberPreRegistrationStatus::ReadyForFinalization, 409);
            abort_unless($preRegistration->hasFinalizationEvidence(), 422);

            $intake = $preRegistration->intake ?? [];
            $member = Member::create([
                'organization_id' => $preRegistration->organization_id,
                'location_id' => $preRegistration->location_id,
                'created_by_user_id' => $user->id,
                'first_name' => $preRegistration->first_name,
                'last_name' => $preRegistration->last_name,
                'public_alias' => data_get($intake, 'public_alias'),
                'national_id' => $preRegistration->document_number,
                'email' => $preRegistration->email,
                'phone' => $preRegistration->phone,
                'birth_date' => $preRegistration->birth_date,
                'address' => data_get($intake, 'address'),
                'city' => data_get($intake, 'city'),
                'client_type' => $preRegistration->client_type,
                'status' => MemberStatus::Registered,
                'started_on' => now()->toDateString(),
                'sessions_remaining' => 0,
                'intake' => [
                    ...$intake,
                    'document_type' => $preRegistration->document_type,
                    'consent_version' => $preRegistration->consent_version,
                    'consent_accepted_at' => $preRegistration->consent_accepted_at?->toIso8601String(),
                    'member_signature_path' => $preRegistration->member_signature_path,
                ],
                'informed_consent_accepted' => $preRegistration->informed_consent_accepted,
                'risk_assumption_accepted' => $preRegistration->risk_assumption_accepted,
                'consent_date' => $preRegistration->consent_accepted_at?->toDateString(),
            ]);

            $preRegistration->update([
                'member_id' => $member->id,
                'finalized_at' => now(),
                'finalized_by_user_id' => $user->id,
            ]);

            return $member;
        });
    }
}
