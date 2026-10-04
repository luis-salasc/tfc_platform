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
use App\Models\SessionSettlement;
use App\Models\User;
use App\Services\SessionLedger;
use Illuminate\Support\Facades\Mail;
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

function ledgerThreeMember(Organization $organization, ?string $email = null): Member
{
    return Member::create([
        'organization_id' => $organization->id,
        'first_name' => 'Ana',
        'last_name' => 'Ruiz',
        'email' => $email,
        'status' => MemberStatus::Active,
        'started_on' => today(),
        'sessions_remaining' => 0,
    ]);
}

function ledgerThreePayment(Member $member, int $sessions, string $key, array $extra = []): void
{
    test()->post(route('members.payments.store', $member), [
        'sessions_purchased' => $sessions,
        'amount' => 100,
        'payment_method' => 'card',
        'idempotency_key' => $key,
        ...$extra,
    ])->assertRedirect();
}

function ledgerThreePendingAttendance(Member $member, DateTimeInterface $at): array
{
    $attendance = MemberAttendance::create([
        'organization_id' => $member->organization_id,
        'member_id' => $member->id,
        'checked_in_at' => $at,
        'requires_settlement' => true,
    ]);
    $movement = SessionMovement::create([
        'member_id' => $member->id,
        'quantity' => -1,
        'type' => SessionMovementType::Attendance,
        'source_type' => MemberAttendance::class,
        'source_id' => $attendance->id,
        'occurred_at' => $at,
    ]);

    return compact('attendance', 'movement');
}

test('attendance without available sessions requires settlement and creates one pending session', function () {
    $member = ledgerThreeMember($this->organization);

    $this->post(route('attendance.store'), ['code' => $member->check_in_token])->assertRedirect();

    expect($member->attendances()->sole()->requires_settlement)->toBeTrue()
        ->and(app(SessionLedger::class)->summary($member)['pending_sessions'])->toBe(1)
        ->and($member->refresh()->sessions_remaining)->toBe(0);
});

test('kiosk attendance without available sessions requires settlement', function () {
    $member = ledgerThreeMember($this->organization);

    $this->postJson(route('kiosk.store', $this->organization), ['code' => $member->check_in_token])
        ->assertOk()
        ->assertJsonPath('state', 'warning');

    expect($member->attendances()->sole()->requires_settlement)->toBeTrue();
});

test('attendance with available sessions does not become pending', function () {
    $member = ledgerThreeMember($this->organization);
    ledgerThreePayment($member, 1, (string) Str::uuid());

    $this->post(route('attendance.store'), ['code' => $member->check_in_token])->assertRedirect();

    expect($member->attendances()->sole()->requires_settlement)->toBeFalse()
        ->and(app(SessionLedger::class)->summary($member))->toMatchArray([
            'available_sessions' => 0,
            'pending_sessions' => 0,
        ]);
});

test('two attendances without sessions produce two pending sessions', function () {
    $member = ledgerThreeMember($this->organization);
    ledgerThreePendingAttendance($member, now()->subDays(2));
    ledgerThreePendingAttendance($member, now()->subDay());

    expect(app(SessionLedger::class)->summary($member))->toMatchArray([
        'mathematical_balance' => -2,
        'available_sessions' => 0,
        'pending_sessions' => 2,
    ]);
});

test('a partial payment settles pending attendances before leaving an available balance', function () {
    $member = ledgerThreeMember($this->organization);
    foreach (range(1, 5) as $day) {
        ledgerThreePendingAttendance($member, now()->subDays(6 - $day));
    }

    ledgerThreePayment($member, 3, (string) Str::uuid());

    expect(SessionSettlement::count())->toBe(3)
        ->and(app(SessionLedger::class)->summary($member))->toMatchArray([
            'available_sessions' => 0,
            'pending_sessions' => 2,
        ])
        ->and($member->refresh()->sessions_remaining)->toBe(0);
});

test('a payment equal to pending sessions settles all of them', function () {
    $member = ledgerThreeMember($this->organization);
    foreach (range(1, 5) as $day) {
        ledgerThreePendingAttendance($member, now()->subDays(6 - $day));
    }

    ledgerThreePayment($member, 5, (string) Str::uuid());

    expect(SessionSettlement::count())->toBe(5)
        ->and(app(SessionLedger::class)->summary($member))->toMatchArray([
            'available_sessions' => 0,
            'pending_sessions' => 0,
        ]);
});

test('a payment greater than pending sessions leaves the surplus available', function () {
    $member = ledgerThreeMember($this->organization);
    ledgerThreePendingAttendance($member, now()->subDays(2));
    ledgerThreePendingAttendance($member, now()->subDay());

    ledgerThreePayment($member, 8, (string) Str::uuid());

    expect(SessionSettlement::count())->toBe(2)
        ->and(app(SessionLedger::class)->summary($member))->toMatchArray([
            'mathematical_balance' => 6,
            'available_sessions' => 6,
            'pending_sessions' => 0,
        ])
        ->and($member->refresh()->sessions_remaining)->toBe(6);
});

test('settlements are FIFO and retain both movements, timestamp, and operator', function () {
    $member = ledgerThreeMember($this->organization);
    $first = ledgerThreePendingAttendance($member, now()->subDays(2));
    ledgerThreePendingAttendance($member, now()->subDay());

    ledgerThreePayment($member, 1, (string) Str::uuid());

    $settlement = SessionSettlement::sole();
    expect($settlement->attendance_movement_id)->toBe($first['movement']->id)
        ->and($settlement->paymentMovement->type)->toBe(SessionMovementType::Payment)
        ->and($settlement->settled_by_user_id)->toBe($this->user->id)
        ->and($settlement->settled_at)->not->toBeNull();
});

test('the same attendance cannot be settled twice', function () {
    $member = ledgerThreeMember($this->organization);
    ledgerThreePendingAttendance($member, now()->subDay());

    ledgerThreePayment($member, 1, (string) Str::uuid());
    ledgerThreePayment($member, 1, (string) Str::uuid());

    expect(SessionSettlement::count())->toBe(1)
        ->and($member->refresh()->sessions_remaining)->toBe(1);
});

test('the payment preview values match its eventual ledger result', function () {
    $member = ledgerThreeMember($this->organization);
    ledgerThreePendingAttendance($member, now()->subDays(2));
    ledgerThreePendingAttendance($member, now()->subDay());
    $summary = app(SessionLedger::class)->summary($member);
    $purchased = 8;

    expect(min($summary['pending_sessions'], $purchased))->toBe(2)
        ->and(max($summary['mathematical_balance'] + $purchased, 0))->toBe(6);

    ledgerThreePayment($member, $purchased, (string) Str::uuid());

    expect(app(SessionLedger::class)->summary($member))->toMatchArray([
        'available_sessions' => 6,
        'pending_sessions' => 0,
    ]);
});

test('payment idempotency reuses the result without duplicate movements or settlements', function () {
    $member = ledgerThreeMember($this->organization);
    ledgerThreePendingAttendance($member, now()->subDay());
    $key = (string) Str::uuid();

    ledgerThreePayment($member, 3, $key);
    ledgerThreePayment($member, 3, $key);

    expect(MemberPayment::count())->toBe(1)
        ->and(SessionMovement::where('type', SessionMovementType::Payment)->count())->toBe(1)
        ->and(SessionSettlement::count())->toBe(1)
        ->and($member->refresh()->sessions_remaining)->toBe(2);
});

test('the backend ignores a client supplied regularization amount', function () {
    $member = ledgerThreeMember($this->organization);
    ledgerThreePendingAttendance($member, now()->subDay());

    ledgerThreePayment($member, 3, (string) Str::uuid(), ['sessions_regularized' => 0]);

    expect(SessionSettlement::count())->toBe(1)
        ->and($member->refresh()->sessions_remaining)->toBe(2);
});

test('a ledger payment cannot be edited without a future correction flow', function () {
    $member = ledgerThreeMember($this->organization);
    ledgerThreePayment($member, 3, (string) Str::uuid());
    $payment = MemberPayment::sole();

    $this->put(route('payments.update', $payment), [
        'sessions_purchased' => 4,
        'amount' => 100,
        'payment_method' => 'card',
    ])->assertSessionHasErrors('payment');

    expect($payment->refresh()->sessions_purchased)->toBe(3)
        ->and($member->refresh()->sessions_remaining)->toBe(3);
});

test('different idempotency keys represent different payments', function () {
    $member = ledgerThreeMember($this->organization);

    ledgerThreePayment($member, 1, (string) Str::uuid());
    ledgerThreePayment($member, 1, (string) Str::uuid());

    expect(MemberPayment::count())->toBe(2)
        ->and(SessionMovement::where('type', SessionMovementType::Payment)->count())->toBe(2)
        ->and($member->refresh()->sessions_remaining)->toBe(2);
});

test('a receipt email failure leaves the confirmed payment and ledger result intact', function () {
    $member = ledgerThreeMember($this->organization, 'ana@example.test');
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Mail unavailable'));

    ledgerThreePayment($member, 2, (string) Str::uuid(), ['delivery_methods' => ['email']]);

    expect(MemberPayment::count())->toBe(1)
        ->and(SessionMovement::where('type', SessionMovementType::Payment)->count())->toBe(1)
        ->and($member->refresh()->sessions_remaining)->toBe(2);
});
