<?php

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\QueryException;

test('una membresía relaciona usuario y organización con enums', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $inviter = User::factory()->create();

    $membership = OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create([
            'invited_by_user_id' => $inviter->id,
            'role' => OrganizationRole::Trainer,
            'status' => MembershipStatus::Active,
        ]);

    expect($membership->role)->toBe(OrganizationRole::Trainer)
        ->and($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->organization->is($organization))->toBeTrue()
        ->and($membership->user->is($user))->toBeTrue()
        ->and($membership->invitedBy->is($inviter))->toBeTrue()
        ->and($organization->organizationMemberships->contains($membership))->toBeTrue()
        ->and($user->organizationMemberships->contains($membership))->toBeTrue();
});

test('un usuario no puede duplicar su acceso a la misma organización', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create();

    expect(fn () => OrganizationMembership::factory()
        ->for($organization)
        ->for($user)
        ->create()
    )->toThrow(QueryException::class);
});

test('el acceso conserva la membresía si desaparece quien invitó', function () {
    $inviter = User::factory()->create();

    $membership = OrganizationMembership::factory()->create([
        'invited_by_user_id' => $inviter->id,
    ]);

    $inviter->delete();

    expect($membership->fresh()->invited_by_user_id)->toBeNull();
});
