<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMembership>
 */
class OrganizationMembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'invited_by_user_id' => null,
            'role' => OrganizationRole::Client,
            'status' => MembershipStatus::Active,
            'invited_at' => now(),
            'joined_at' => now(),
            'suspended_at' => null,
            'revoked_at' => null,
        ];
    }
}
