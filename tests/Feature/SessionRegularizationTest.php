<?php

use App\Enums\MembershipStatus;
use App\Enums\MemberStatus;
use App\Enums\OrganizationRole;
use App\Models\Member;
use App\Models\MemberIncident;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($this->user)->create([
        'role' => OrganizationRole::Trainer,
        'status' => MembershipStatus::Active,
    ]);
    $this->actingAs($this->user);
});

test('a later payment settles attendance entries made without available sessions', function () {
    $member = Member::create([
        'organization_id' => $this->organization->id,
        'first_name' => 'Ana',
        'last_name' => 'Ruiz',
        'status' => MemberStatus::Active,
        'started_on' => today(),
        'sessions_remaining' => 0,
    ]);
    $incident = MemberIncident::create([
        'organization_id' => $this->organization->id,
        'member_id' => $member->id,
        'type' => 'attendance_without_sessions',
        'severity' => 'warning',
        'status' => 'open',
        'source' => 'kiosk',
        'title' => 'Asistencia sin sesiones disponibles',
        'context' => ['sessions_pending' => 1],
        'occurred_at' => now(),
    ]);

    $this->post(route('attendance.store'), ['code' => $member->check_in_token])->assertRedirect();
    $incident = $member->incidents()->latest('id')->firstOrFail();

    $this->post(route('members.payments.store', $member), [
        'sessions_purchased' => 5,
        'amount' => 80,
        'payment_method' => 'card',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();

    $this->assertDatabaseHas('member_payments', [
        'member_id' => $member->id,
        'sessions_purchased' => 5,
        'sessions_regularized' => 1,
    ]);
    expect($member->refresh()->sessions_remaining)->toBe(4)
        ->and($incident->refresh()->status)->toBe('resolved')
        ->and($incident->resolved_by_user_id)->toBe($this->user->id);
});

test('manual attendance also creates a regularizable incident when the member has no sessions', function () {
    $member = Member::create([
        'organization_id' => $this->organization->id,
        'first_name' => 'Ana',
        'last_name' => 'Ruiz',
        'status' => MemberStatus::Active,
        'started_on' => today(),
        'sessions_remaining' => 0,
    ]);

    $this->post(route('attendance.store'), ['code' => $member->check_in_token])->assertRedirect();

    $this->assertDatabaseHas('member_incidents', [
        'organization_id' => $this->organization->id,
        'member_id' => $member->id,
        'type' => 'attendance_without_sessions',
        'source' => 'staff',
        'status' => 'open',
    ]);
});
