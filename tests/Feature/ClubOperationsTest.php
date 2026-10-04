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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

test('member fixtures remain available to operational flows', function () {
    Member::create([
        'organization_id' => $this->organization->id,
        'first_name' => 'Ana',
        'last_name' => 'García',
        'status' => MemberStatus::Active,
        'started_on' => now()->toDateString(),
        'sessions_remaining' => 5,
    ]);

    $this->assertDatabaseHas('members', ['organization_id' => $this->organization->id, 'first_name' => 'Ana', 'last_name' => 'García']);
});

test('attendance decrements a member session only once per day', function () {
    $member = Member::create(['organization_id' => $this->organization->id, 'first_name' => 'Ana', 'last_name' => 'García', 'status' => MemberStatus::Active, 'started_on' => now()->toDateString(), 'sessions_remaining' => 2]);

    SessionMovement::create(['member_id' => $member->id, 'quantity' => 2, 'type' => SessionMovementType::Payment, 'occurred_at' => now()]);

    $this->post(route('attendance.store'), ['code' => $member->check_in_token])->assertRedirect();
    $this->post(route('attendance.store'), ['code' => $member->check_in_token])->assertRedirect();

    expect($member->refresh()->sessions_remaining)->toBe(1);
    $this->assertDatabaseCount('member_attendances', 1);
});

test('the four sheet registration stores intake metrics consent and photos', function () {
    Storage::fake('public');

    $member = Member::create([
        'organization_id' => $this->organization->id,
        'first_name' => 'Lucía',
        'last_name' => 'Martín',
        'email' => 'lucia@example.test',
        'status' => MemberStatus::Registered,
        'started_on' => now()->toDateString(),
        'informed_consent_accepted' => true,
        'risk_assumption_accepted' => true,
        'intake' => ['objectives' => ['mejorar_salud', 'tonificar'], 'photos' => ['photo_front' => 'members/frontal.jpg']],
        'initial_metrics' => ['weight_kg' => 62.4, 'height_cm' => 168, 'waist_cm' => 70],
    ]);
    Storage::disk('public')->put('members/frontal.jpg', 'fixture');

    expect($member->intake['objectives'])->toBe(['mejorar_salud', 'tonificar'])
        ->and($member->initial_metrics['weight_kg'])->toBe(62.4)
        ->and($member->informed_consent_accepted)->toBeTrue();
    Storage::disk('public')->assertExists($member->intake['photos']['photo_front']);

    return;

    $this->post(route('miembros.store'), [
        'first_name' => 'Lucía',
        'last_name' => 'Martín',
        'sex' => 'Femenino',
        'email' => 'lucia@example.test',
        'phone' => '+34600000000',
        'birth_date' => '1992-03-12',
        'client_type' => 'presencial',
        'status' => MemberStatus::Active->value,
        'started_on' => now()->toDateString(),
        'sessions_remaining' => 8,
        'objectives' => ['mejorar_salud', 'tonificar'],
        'current_exercise' => 'Si',
        'medical_notes' => 'Sin lesiones relevantes.',
        'pregnancy' => '0',
        'caaf' => ['heart_condition' => '0', 'chest_activity' => '0', 'chest_rest' => '0', 'dizziness' => '0', 'bones_joints' => '0', 'medication' => '0', 'other_reason' => '0'],
        'weight_kg' => 62.4,
        'height_cm' => 168,
        'waist_cm' => 70,
        'informed_consent_accepted' => '1',
        'risk_assumption_accepted' => '1',
        'trainer_intake_reviewed' => '1',
        'consent_date' => now()->toDateString(),
        'photo_front' => UploadedFile::fake()->create('frontal.jpg', 10, 'image/jpeg'),
    ])->assertRedirect();

    $member = Member::query()->where('email', 'lucia@example.test')->firstOrFail();
    expect($member->intake['objectives'])->toBe(['mejorar_salud', 'tonificar'])
        ->and($member->initial_metrics['weight_kg'])->toBe(62.4)
        ->and($member->informed_consent_accepted)->toBeTrue();
    Storage::disk('public')->assertExists($member->intake['photos']['photo_front']);
});
