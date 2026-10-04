<?php

use App\Enums\MembershipStatus;
use App\Enums\MemberStatus;
use App\Enums\OrganizationRole;
use App\Enums\SessionMovementType;
use App\Models\Location;
use App\Models\Member;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SessionMovement;
use App\Models\User;
use Illuminate\Database\QueryException;

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

test('a new member always starts with zero sessions even when a balance is submitted', function () {
    $this->post(route('miembros.store'), [
        'first_name' => 'Ana',
        'last_name' => 'García',
        'status' => MemberStatus::Active->value,
        'started_on' => now()->toDateString(),
        'sessions_remaining' => 500,
    ])->assertRedirect();

    expect(Member::query()->where('first_name', 'Ana')->sole()->sessions_remaining)->toBe(0);
});

test('member forms do not expose a manually editable sessions balance', function () {
    $member = Member::create([
        'organization_id' => $this->organization->id,
        'first_name' => 'Ana',
        'last_name' => 'García',
        'status' => MemberStatus::Active,
        'started_on' => now()->toDateString(),
        'sessions_remaining' => 7,
    ]);

    $this->get(route('miembros.create'))
        ->assertOk()
        ->assertDontSee('name="sessions_remaining"', false);

    $this->get(route('miembros.edit', $member))
        ->assertOk()
        ->assertDontSee('name="sessions_remaining"', false);
});

test('a generic member update cannot change an existing sessions balance', function () {
    $member = Member::create([
        'organization_id' => $this->organization->id,
        'first_name' => 'Ana',
        'last_name' => 'García',
        'status' => MemberStatus::Active,
        'started_on' => now()->toDateString(),
        'sessions_remaining' => 7,
    ]);

    $this->put(route('miembros.update', $member), [
        'first_name' => 'Ana María',
        'last_name' => 'García',
        'status' => MemberStatus::Active->value,
        'started_on' => now()->toDateString(),
        'sessions_remaining' => 500,
    ])->assertRedirect();

    expect($member->refresh()->sessions_remaining)->toBe(7)
        ->and($member->first_name)->toBe('Ana María');
});

test('a session movement persists and belongs to its member', function () {
    $member = Member::create([
        'organization_id' => $this->organization->id,
        'first_name' => 'Ana',
        'last_name' => 'García',
        'status' => MemberStatus::Active,
        'started_on' => now()->toDateString(),
    ]);

    $movement = SessionMovement::create([
        'member_id' => $member->id,
        'quantity' => 8,
        'type' => SessionMovementType::LegacyImport,
        'source_type' => 'legacy_import',
        'source_id' => 123,
        'occurred_at' => now(),
        'created_by_user_id' => $this->user->id,
        'idempotency_key' => 'ledger-test-operation-1',
    ]);

    expect($movement->type)->toBe(SessionMovementType::LegacyImport)
        ->and($movement->member->is($member))->toBeTrue()
        ->and($member->sessionMovements->sole()->is($movement))->toBeTrue();
});

test('a repeated session movement idempotency key is rejected', function () {
    $member = Member::create([
        'organization_id' => $this->organization->id,
        'first_name' => 'Ana',
        'last_name' => 'García',
        'status' => MemberStatus::Active,
        'started_on' => now()->toDateString(),
    ]);

    $attributes = [
        'member_id' => $member->id,
        'quantity' => 1,
        'type' => SessionMovementType::Complimentary,
        'occurred_at' => now(),
        'idempotency_key' => 'ledger-test-operation-duplicate',
    ];

    SessionMovement::create($attributes);

    expect(fn () => SessionMovement::create($attributes))
        ->toThrow(QueryException::class);
});
