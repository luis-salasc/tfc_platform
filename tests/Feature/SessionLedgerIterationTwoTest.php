<?php

use App\Enums\MembershipStatus;
use App\Enums\MemberStatus;
use App\Enums\OrganizationRole;
use App\Enums\SessionMovementType;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberAttendance;
use App\Models\MemberPayment;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SessionMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    Location::factory()->for($this->organization)->create();
    $this->user = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($this->user)->create([
        'role' => OrganizationRole::Owner,
        'status' => MembershipStatus::Active,
    ]);
    $this->actingAs($this->user);
});

function ledgerMember(Organization $organization, int $sessionsRemaining = 0): Member
{
    return Member::create([
        'organization_id' => $organization->id,
        'first_name' => 'Ana',
        'last_name' => 'García',
        'status' => MemberStatus::Active,
        'started_on' => today(),
        'sessions_remaining' => $sessionsRemaining,
    ]);
}

test('a payment creates one positive payment movement for its complete purchase', function () {
    $member = ledgerMember($this->organization);

    $this->post(route('members.payments.store', $member), [
        'sessions_purchased' => 8,
        'amount' => 120,
        'payment_method' => 'card',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();

    $payment = MemberPayment::query()->sole();
    $movement = SessionMovement::query()->sole();

    expect($movement->type)->toBe(SessionMovementType::Payment)
        ->and($movement->quantity)->toBe(8)
        ->and($movement->member_id)->toBe($member->id)
        ->and($movement->source_type)->toBe(MemberPayment::class)
        ->and($movement->source_id)->toBe($payment->id)
        ->and($movement->created_by_user_id)->toBe($this->user->id);
});

test('manual attendance with sessions creates one negative attendance movement', function () {
    $member = ledgerMember($this->organization, 2);

    SessionMovement::create([
        'member_id' => $member->id,
        'quantity' => 2,
        'type' => SessionMovementType::Payment,
        'occurred_at' => now(),
    ]);

    $this->post(route('attendance.store'), ['code' => $member->check_in_token])
        ->assertRedirect();

    $attendance = $member->attendances()->sole();
    $movement = $member->sessionMovements()->where('type', SessionMovementType::Attendance)->sole();

    expect($movement->type)->toBe(SessionMovementType::Attendance)
        ->and($movement->quantity)->toBe(-1)
        ->and($movement->source_type)->toBe($attendance::class)
        ->and($movement->source_id)->toBe($attendance->id)
        ->and($movement->created_by_user_id)->toBe($this->user->id)
        ->and($member->refresh()->sessions_remaining)->toBe(1);
});

test('manual attendance without sessions creates a movement and keeps the incident flow', function () {
    $member = ledgerMember($this->organization);

    $this->post(route('attendance.store'), ['code' => $member->check_in_token])
        ->assertRedirect();

    expect($member->sessionMovements()->sole()->quantity)->toBe(-1)
        ->and($member->refresh()->sessions_remaining)->toBe(0);
    $this->assertDatabaseHas('member_incidents', [
        'member_id' => $member->id,
        'type' => 'attendance_without_sessions',
        'source' => 'staff',
        'status' => 'open',
    ]);
});

test('kiosk attendance with sessions creates one negative attendance movement', function () {
    $member = ledgerMember($this->organization, 2);

    $this->postJson(route('kiosk.store', $this->organization), ['code' => $member->check_in_token])
        ->assertOk();

    $attendance = $member->attendances()->sole();
    $movement = $member->sessionMovements()->sole();

    expect($movement->type)->toBe(SessionMovementType::Attendance)
        ->and($movement->quantity)->toBe(-1)
        ->and($movement->source_type)->toBe($attendance::class)
        ->and($movement->source_id)->toBe($attendance->id)
        ->and($movement->created_by_user_id)->toBeNull();
});

test('kiosk attendance without sessions creates a movement and keeps the incident flow', function () {
    $member = ledgerMember($this->organization);

    $this->postJson(route('kiosk.store', $this->organization), ['code' => $member->check_in_token])
        ->assertOk()
        ->assertJsonPath('state', 'warning');

    expect($member->sessionMovements()->sole()->quantity)->toBe(-1)
        ->and($member->refresh()->sessions_remaining)->toBe(0);
    $this->assertDatabaseHas('member_incidents', [
        'member_id' => $member->id,
        'type' => 'attendance_without_sessions',
        'source' => 'kiosk',
        'status' => 'open',
    ]);
});

test('a duplicate source movement rolls back the payment that created it', function () {
    $member = ledgerMember($this->organization);
    SessionMovement::create([
        'member_id' => $member->id,
        'quantity' => 8,
        'type' => SessionMovementType::Payment,
        'source_type' => MemberPayment::class,
        'source_id' => 1,
        'occurred_at' => now(),
    ]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->post(route('members.payments.store', $member), [
        'sessions_purchased' => 8,
        'amount' => 120,
        'payment_method' => 'card',
        'idempotency_key' => (string) Str::uuid(),
    ]))->toThrow(QueryException::class);

    $this->assertDatabaseCount('member_payments', 0);
    $this->assertDatabaseCount('session_movements', 1);
});

test('a duplicate source movement rolls back the manual attendance that created it', function () {
    $member = ledgerMember($this->organization, 2);
    SessionMovement::create([
        'member_id' => $member->id,
        'quantity' => -1,
        'type' => SessionMovementType::Attendance,
        'source_type' => MemberAttendance::class,
        'source_id' => 1,
        'occurred_at' => now(),
    ]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->post(route('attendance.store'), ['code' => $member->check_in_token]))
        ->toThrow(QueryException::class);

    $this->assertDatabaseCount('member_attendances', 0);
    $this->assertDatabaseCount('session_movements', 1);
    expect($member->refresh()->sessions_remaining)->toBe(2);
});
