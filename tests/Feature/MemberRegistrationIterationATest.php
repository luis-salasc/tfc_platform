<?php

use App\Enums\MembershipStatus;
use App\Enums\MemberStatus;
use App\Enums\OrganizationRole;
use App\Enums\SessionMovementType;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberAttendance;
use App\Models\MemberIncident;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SessionMovement;
use App\Models\User;
use App\Services\SessionLedger;
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

function registeredMember(Organization $organization): Member
{
    return Member::create([
        'organization_id' => $organization->id,
        'first_name' => 'Ana',
        'last_name' => 'Ruiz',
        'status' => MemberStatus::Registered,
        'started_on' => today(),
    ]);
}

function registerPayment(Member $member, int $sessions): void
{
    test()->post(route('members.payments.store', $member), [
        'sessions_purchased' => $sessions,
        'amount' => 100,
        'payment_method' => 'card',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();
}

test('a registered member starts with zero sessions and a system registration date', function () {
    $member = Member::create([
        'organization_id' => $this->organization->id,
        'first_name' => 'Lucía',
        'last_name' => 'Martín',
        'status' => MemberStatus::Registered,
        'started_on' => today(),
        'sessions_remaining' => 0,
    ]);

    expect($member->status)->toBe(MemberStatus::Registered)
        ->and($member->sessions_remaining)->toBe(0)
        ->and($member->created_at)->not->toBeNull()
        ->and($member->started_on->toDateString())->toBe(today()->toDateString())
        ->and(app(SessionLedger::class)->summary($member))->toMatchArray([
            'available_sessions' => 0,
            'pending_sessions' => 0,
        ]);
});

test('member list uses created at for current month registrations', function () {
    $member = registeredMember($this->organization);
    $member->update(['started_on' => now()->subYear()->toDateString()]);

    $this->get(route('miembros.index', ['new_this_month' => 1]))
        ->assertOk()
        ->assertSee('Ana Ruiz')
        ->assertSee('Registro');
});

test('a registered member can pay without becoming active', function () {
    $member = registeredMember($this->organization);

    registerPayment($member, 2);

    expect($member->refresh()->status)->toBe(MemberStatus::Registered)
        ->and(app(SessionLedger::class)->summary($member))->toMatchArray([
            'available_sessions' => 2,
            'pending_sessions' => 0,
        ]);
});

test('first manual attendance with sessions activates a registered member', function () {
    $member = registeredMember($this->organization);
    registerPayment($member, 1);

    $this->post(route('attendance.store'), ['code' => $member->check_in_token])->assertRedirect();

    expect($member->refresh()->status)->toBe(MemberStatus::Active)
        ->and($member->attendances()->sole()->requires_settlement)->toBeFalse()
        ->and(app(SessionLedger::class)->summary($member))->toMatchArray([
            'available_sessions' => 0,
            'pending_sessions' => 0,
        ]);
});

test('first manual attendance without sessions activates and records a pending attendance', function () {
    $member = registeredMember($this->organization);

    $this->post(route('attendance.store'), ['code' => $member->check_in_token])->assertRedirect();

    expect($member->refresh()->status)->toBe(MemberStatus::Active)
        ->and($member->attendances()->sole()->requires_settlement)->toBeTrue()
        ->and(app(SessionLedger::class)->summary($member))->toMatchArray([
            'available_sessions' => 0,
            'pending_sessions' => 1,
        ]);
    expect(MemberIncident::query()->sole()->details)->toBe('Asistencia registrada sin sesiones disponibles.');
});

test('kiosk activates registered members with and without sessions', function () {
    $withSessions = registeredMember($this->organization);
    registerPayment($withSessions, 1);

    $this->postJson(route('kiosk.store', $this->organization), ['code' => $withSessions->check_in_token])
        ->assertOk()
        ->assertJsonPath('state', 'success');

    expect($withSessions->refresh()->status)->toBe(MemberStatus::Active)
        ->and(app(SessionLedger::class)->summary($withSessions)['pending_sessions'])->toBe(0);

    $withoutSessions = registeredMember($this->organization);

    $this->postJson(route('kiosk.store', $this->organization), ['code' => $withoutSessions->check_in_token])
        ->assertOk()
        ->assertJsonPath('state', 'warning');

    expect($withoutSessions->refresh()->status)->toBe(MemberStatus::Active)
        ->and($withoutSessions->attendances()->sole()->requires_settlement)->toBeTrue()
        ->and(app(SessionLedger::class)->summary($withoutSessions)['pending_sessions'])->toBe(1);
});

test('inactive and frozen members remain blocked from manual and kiosk check in', function () {
    foreach ([MemberStatus::Inactive, MemberStatus::Frozen] as $status) {
        $member = registeredMember($this->organization);
        $member->update(['status' => $status]);

        $this->post(route('attendance.store'), ['code' => $member->check_in_token])
            ->assertSessionHasErrors('code');
        $this->postJson(route('kiosk.store', $this->organization), ['code' => $member->check_in_token])
            ->assertUnprocessable();

        expect($member->attendances)->toHaveCount(0);
    }
});

test('a failed first attendance transaction leaves a registered member unchanged', function () {
    $member = registeredMember($this->organization);
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

    expect($member->refresh()->status)->toBe(MemberStatus::Registered);
    $this->assertDatabaseCount('member_attendances', 0);
});

test('member profile always displays zero available and pending sessions', function () {
    $member = registeredMember($this->organization);

    $this->get(route('miembros.show', $member))
        ->assertOk()
        ->assertSee('Sesiones disponibles')
        ->assertSee('Sesiones pendientes');
});
