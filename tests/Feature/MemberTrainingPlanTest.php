<?php

use App\Enums\MembershipStatus;
use App\Enums\MemberStatus;
use App\Enums\OrganizationRole;
use App\Models\Location;
use App\Models\Member;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    Location::factory()->for($this->organization)->create();
    $this->trainer = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($this->trainer)->create([
        'role' => OrganizationRole::Trainer,
        'status' => MembershipStatus::Active,
    ]);
    $this->member = Member::create([
        'organization_id' => $this->organization->id,
        'first_name' => 'Ana',
        'last_name' => 'García',
        'status' => MemberStatus::Active,
        'started_on' => now()->toDateString(),
    ]);
    $this->actingAs($this->trainer);
});

test('a trainer can assign and update a structured training plan', function () {
    $payload = [
        'title' => 'Fuerza inicial',
        'objective' => 'Mejorar fuerza general',
        'starts_on' => now()->toDateString(),
        'sessions_per_week' => 3,
        'items' => [['session_name' => 'Sesión A', 'exercise' => 'Sentadilla', 'sets' => '3', 'repetitions' => '10', 'rest_seconds' => 90]],
    ];

    $this->post(route('members.training-plans.store', $this->member), $payload)->assertRedirect();
    $plan = $this->member->trainingPlans()->firstOrFail();
    expect($plan->status)->toBe('active')->and($plan->items()->first()->exercise)->toBe('Sentadilla');

    $this->put(route('members.training-plans.update', [$this->member, $plan]), [
        ...$payload,
        'title' => 'Fuerza revisada',
        'items' => [['session_name' => 'Sesión B', 'exercise' => 'Peso muerto', 'sets' => '4', 'repetitions' => '8']],
    ])->assertRedirect();

    expect($plan->refresh()->title)->toBe('Fuerza revisada')
        ->and($plan->items()->count())->toBe(1)
        ->and($plan->items()->first()->exercise)->toBe('Peso muerto');
});

test('a training plan cannot be changed through a member from another organization', function () {
    $otherOrganization = Organization::factory()->create();
    $otherMember = Member::create(['organization_id' => $otherOrganization->id, 'first_name' => 'Otro', 'last_name' => 'Centro', 'status' => MemberStatus::Active, 'started_on' => now()->toDateString()]);
    $plan = $this->member->trainingPlans()->create(['organization_id' => $this->organization->id, 'title' => 'Privado', 'starts_on' => now(), 'status' => 'active']);

    $this->put(route('members.training-plans.update', [$otherMember, $plan]), [
        'title' => 'No permitido', 'starts_on' => now()->toDateString(), 'items' => [['exercise' => 'Prueba']],
    ])->assertNotFound();
});
