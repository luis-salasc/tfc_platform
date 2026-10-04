<?php

use App\Enums\MemberStatus;
use App\Enums\SessionMovementType;
use App\Models\Member;
use App\Models\Organization;
use App\Models\SessionMovement;

test('kiosk confirms a check-in only once and returns a structured response', function () {
    $organization = Organization::factory()->create();
    $member = Member::create([
        'organization_id' => $organization->id,
        'first_name' => 'Ana',
        'last_name' => 'Ruiz',
        'public_alias' => 'Atlas',
        'status' => MemberStatus::Active,
        'started_on' => today(),
        'sessions_remaining' => 2,
    ]);

    SessionMovement::create(['member_id' => $member->id, 'quantity' => 2, 'type' => SessionMovementType::Payment, 'occurred_at' => now()]);

    $this->postJson(route('kiosk.store', $organization), ['code' => $member->check_in_token])
        ->assertOk()->assertJsonPath('state', 'success')->assertJsonPath('title', 'Bienvenido/a, Atlas');
    SessionMovement::create(['member_id' => $member->id, 'quantity' => 2, 'type' => SessionMovementType::Payment, 'occurred_at' => now()]);

    $this->postJson(route('kiosk.store', $organization), ['code' => $member->check_in_token])
        ->assertOk()
        ->assertJsonPath('state', 'duplicate')
        ->assertJsonPath('title', 'Atlas, entrada ya registrada');

    expect($member->refresh()->sessions_remaining)->toBe(1);
    $this->assertDatabaseCount('member_attendances', 1);
});

test('kiosk allows entry without sessions and opens one follow-up incident', function () {
    $organization = Organization::factory()->create();
    $member = Member::create([
        'organization_id' => $organization->id,
        'first_name' => 'Ana',
        'last_name' => 'Ruiz',
        'status' => MemberStatus::Active,
        'started_on' => today(),
        'sessions_remaining' => 0,
    ]);

    $this->postJson(route('kiosk.store', $organization), ['code' => $member->check_in_token])
        ->assertOk()
        ->assertJsonPath('state', 'warning')
        ->assertJsonPath('title', 'Bienvenido/a, Ana');

    $this->assertDatabaseHas('member_incidents', [
        'member_id' => $member->id,
        'type' => 'attendance_without_sessions',
        'status' => 'open',
    ]);
});

test('kiosk returns a controlled response when the reader sends an invalid code', function () {
    $organization = Organization::factory()->create();

    $this->postJson(route('kiosk.store', $organization), ['code' => 'not-a-qr-code'])
        ->assertUnprocessable()
        ->assertJsonPath('state', 'error')
        ->assertJsonPath('title', 'C'."\u{00F3}".'digo no v'."\u{00E1}".'lido');
});

test('kiosk returns the celebration state when it is the member birthday', function () {
    $organization = Organization::factory()->create();
    $member = Member::create([
        'organization_id' => $organization->id,
        'first_name' => 'Ana',
        'last_name' => 'Ruiz',
        'public_alias' => 'Atlas',
        'status' => MemberStatus::Active,
        'started_on' => today(),
        'birth_date' => today()->subYears(30),
        'sessions_remaining' => 2,
    ]);

    SessionMovement::create(['member_id' => $member->id, 'quantity' => 2, 'type' => SessionMovementType::Payment, 'occurred_at' => now()]);

    $this->postJson(route('kiosk.store', $organization), ['code' => $member->check_in_token])
        ->assertOk()
        ->assertJsonPath('state', 'celebration')
        ->assertJsonPath('title', "\u{00A1}Feliz cumplea\u{00F1}os, Atlas!");
});
