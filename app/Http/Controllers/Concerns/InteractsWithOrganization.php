<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\OrganizationRole;
use App\Models\Member;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Http\Request;

trait InteractsWithOrganization
{
    protected function organization(Request $request): Organization
    {
        return $request->attributes->get('organization');
    }

    protected function locationId(Request $request): ?int
    {
        return $request->attributes->get('location')?->id;
    }

    protected function membership(Request $request): OrganizationMembership
    {
        return $request->attributes->get('organizationMembership');
    }

    protected function requireRole(Request $request, OrganizationRole ...$roles): void
    {
        abort_unless(in_array($this->membership($request)->role, $roles, true), 403);
    }

    protected function requirePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->canWithinOrganization($permission, $this->membership($request)), 403);
    }

    protected function member(Request $request, Member $member): Member
    {
        abort_unless($member->organization_id === $this->organization($request)->id, 404);

        return $member;
    }
}
