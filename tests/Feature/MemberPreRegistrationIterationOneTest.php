<?php

use App\Enums\MemberPreRegistrationStatus;
use App\Enums\MemberPreRegistrationReviewDecision;
use App\Enums\MembershipStatus;
use App\Enums\MemberStatus;
use App\Enums\OrganizationRole;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberPreRegistration;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->location = Location::factory()->for($this->organization)->create();
    $this->user = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($this->user)->create([
        'role' => OrganizationRole::Trainer,
        'status' => MembershipStatus::Active,
    ]);
    $this->actingAs($this->user);
});

function preRegistrationPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'first_name' => 'Lucía',
        'last_name' => 'Martín',
        'birth_date' => '1990-01-02',
        'document_type' => 'dni',
        'document_number' => '12345678A',
        'email' => 'lucia@example.test',
        'phone' => '600000000',
        'client_type' => 'presencial',
        'informed_consent_accepted' => true,
        'risk_assumption_accepted' => true,
        'consent_version' => '2026-09-11',
        'consent_accepted_at' => now(),
        'member_signature_path' => 'member-signatures/test.png',
        'sex' => 'mujer',
        'city' => 'Madrid',
        'objectives' => ['mejorar_salud'],
        'caaf' => [
            'heart_condition' => '0',
            'chest_activity' => '0',
            'chest_rest' => '0',
            'dizziness' => '0',
            'bones_joints' => '0',
            'medication' => '0',
            'other_reason' => '0',
        ],
        'pregnancy' => '0',
        'informed_consent_accepted' => '1',
        'risk_assumption_accepted' => '1',
        'member_signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL9dQAAAABJRU5ErkJggg==',
    ], $overrides);
}

function finalizablePreRegistration(Organization $organization, Location $location, User $user): MemberPreRegistration
{
    return MemberPreRegistration::create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'created_by_user_id' => $user->id,
        'status' => MemberPreRegistrationStatus::ReadyForFinalization,
        'first_name' => 'Lucía',
        'last_name' => 'Martín',
        'birth_date' => '1990-01-02',
        'document_type' => 'dni',
        'document_number' => '12345678A',
        'email' => 'lucia@example.test',
        'phone' => '600000000',
        'client_type' => 'presencial',
        'informed_consent_accepted' => true,
        'risk_assumption_accepted' => true,
        'consent_version' => '2026-09-11',
        'consent_accepted_at' => now(),
        'member_signature_path' => 'member-signatures/test.png',
        'intake' => [
            'public_alias' => 'Lucía M.',
            'address' => 'Calle Mayor 10',
            'city' => 'Madrid',
            'sex' => 'mujer',
            'objectives' => ['mejorar_salud'],
            'caaf' => ['heart_condition' => false],
        ],
    ]);
}

test('a pre-registration can be saved as an incomplete draft', function () {
    $this->post(route('pre-registrations.store'), ['save_as_draft' => '1'])->assertRedirect();

    expect(MemberPreRegistration::query()->sole()->status)->toBe(MemberPreRegistrationStatus::Draft)
        ->and(Member::query()->count())->toBe(0);
});

test('the normal new member route starts a pre-registration instead', function () {
    $this->get(route('miembros.create'))
        ->assertOk()
        ->assertSee('Nuevo miembro')
        ->assertSee('Presencial: la ficha se completa en el centro.')
        ->assertSee('Online:')
        ->assertSee('data-registration-wizard', false);

    expect(Member::query()->count())->toBe(0);
});

test('the registration workflow exposes only member-facing paths', function (): void {
    $memberRoutes = collect(Route::getRoutes()->getRoutes());

    expect(parse_url(route('pre-registrations.store'), PHP_URL_PATH))->toBe('/miembros/altas')
        ->and(parse_url(route('pre-registrations.show', 1), PHP_URL_PATH))->toBe('/miembros/altas/1')
        ->and(parse_url(route('pre-registrations.edit', 1), PHP_URL_PATH))->toBe('/miembros/altas/1/edit')
        ->and(parse_url(route('pre-registrations.resume', 1), PHP_URL_PATH))->toBe('/miembros/altas/1/retomar')
        ->and(parse_url(route('pre-registrations.reviews.store', 1), PHP_URL_PATH))->toBe('/miembros/altas/1/revisiones')
        ->and(parse_url(route('pre-registrations.finalize', 1), PHP_URL_PATH))->toBe('/miembros/altas/1/finalizar')
        ->and($memberRoutes->contains(fn ($route) => str_starts_with($route->uri(), 'prealtas')))->toBeFalse()
        ->and($memberRoutes->contains(fn ($route) => $route->uri() === 'miembros' && in_array('POST', $route->methods(), true)))->toBeFalse()
        ->and(Route::getRoutes()->getByName('miembros.update')->methods())->toEqualCanonicalizing(['PUT', 'PATCH']);

    $this->get(route('miembros.create'))
        ->assertOk()
        ->assertDontSee('Decisión de diseño')
        ->assertDontSee('prealta');
});

test('a pre-registration without triggering circumstances finalizes directly into a member', function () {
    $response = $this->post(route('pre-registrations.store'), preRegistrationPayload());

    $preRegistration = MemberPreRegistration::query()->sole();
    $member = Member::query()->sole();

    $response->assertRedirect(route('miembros.show', $member));

    expect($preRegistration->status)->toBe(MemberPreRegistrationStatus::ReadyForFinalization)
        ->and($preRegistration->client_type)->toBe('presencial')
        ->and($preRegistration->document_type)->toBe('dni')
        ->and($preRegistration->document_number)->toBe('12345678A')
        ->and($member->status)->toBe(MemberStatus::Registered)
        ->and($member->sessions_remaining)->toBe(0)
        ->and(Member::query()->count())->toBe(1);
});

test('a draft rehydrates all persisted first-sheet data', function () {
    $response = $this->post(route('pre-registrations.store'), preRegistrationPayload([
        'save_as_draft' => '1',
        'client_type' => 'online',
        'sex' => 'no_aplica',
        'document_type' => 'passport',
        'document_number' => 'P-1234567',
        'address' => 'Calle Mayor 10',
        'city' => 'Valencia',
        'occupation' => 'Diseñadora',
        'discovery_channel' => 'recomendacion',
        'discovery_detail' => 'María Pérez',
        'objectives' => ['mejorar_salud', 'aumentar_resistencia'],
        'goal_date' => '2027-02-01',
    ]))->assertRedirect();

    $preRegistration = MemberPreRegistration::query()->sole();

    expect($preRegistration->status)->toBe(MemberPreRegistrationStatus::Draft)
        ->and($preRegistration->client_type)->toBe('online')
        ->and($preRegistration->document_type)->toBe('passport')
        ->and($preRegistration->document_number)->toBe('P-1234567')
        ->and($preRegistration->intake)->toMatchArray([
            'sex' => 'no_aplica',
            'address' => 'Calle Mayor 10',
            'city' => 'Valencia',
            'occupation' => 'Diseñadora',
            'discovery_channel' => 'recomendacion',
            'discovery_detail' => 'María Pérez',
            'objectives' => ['mejorar_salud', 'aumentar_resistencia'],
            'goal_date' => '2027-02-01',
        ]);

    $this->get(route('pre-registrations.edit', $preRegistration))
        ->assertOk()
        ->assertSee('value="P-1234567"', false)
        ->assertSee('value="Valencia"', false)
        ->assertSee('value="Calle Mayor 10"', false)
        ->assertSee('value="Diseñadora"', false)
        ->assertSee('value="María Pérez"', false)
        ->assertSee('value="2027-02-01"', false);
});

test('all allowed sex values are accepted and preserved by a pre-registration', function (string $sex): void {
    $this->post(route('pre-registrations.store'), preRegistrationPayload(['sex' => $sex]))->assertRedirect();

    expect(data_get(MemberPreRegistration::query()->sole()->intake, 'sex'))->toBe($sex);
})->with(['hombre', 'mujer', 'no_aplica']);

test('a source detail is required only for recommendation or other', function (string $channel): void {
    $this->post(route('pre-registrations.store'), preRegistrationPayload([
        'discovery_channel' => $channel,
    ]))->assertSessionHasErrors('discovery_detail');
})->with(['recomendacion', 'otro']);

test('valid first names with Unicode punctuation are accepted', function (string $firstName): void {
    $this->post(route('pre-registrations.store'), preRegistrationPayload(['first_name' => $firstName]))->assertRedirect();
})->with(['Arturo', 'José María', 'María-José', "O'Connor", 'Muñoz']);

test('names containing digits are rejected', function (string $firstName): void {
    $this->post(route('pre-registrations.store'), preRegistrationPayload(['first_name' => $firstName]))
        ->assertSessionHasErrors('first_name');
})->with(['Arturo4', '1234', 'María99']);

test('human phone formats with enough digits are accepted', function (string $phone): void {
    $this->post(route('pre-registrations.store'), preRegistrationPayload(['phone' => $phone]))->assertRedirect();
})->with(['612345678', '+34 612 345 678', '+34612345678']);

test('letters or insufficient digits in phone numbers are rejected', function (string $phone): void {
    $this->post(route('pre-registrations.store'), preRegistrationPayload(['phone' => $phone]))
        ->assertSessionHasErrors('phone');
})->with(['abcdef', '612ABC678', '123']);

test('a future birth date is rejected', function () {
    $this->post(route('pre-registrations.store'), preRegistrationPayload([
        'birth_date' => now()->addDay()->toDateString(),
    ]))->assertSessionHasErrors('birth_date');
});

test('an arbitrary sex value is rejected', function () {
    $this->post(route('pre-registrations.store'), preRegistrationPayload(['sex' => 'desconocido']))
        ->assertSessionHasErrors('sex');
});

test('allowed document types are accepted', function (string $documentType): void {
    $this->post(route('pre-registrations.store'), preRegistrationPayload(['document_type' => $documentType]))
        ->assertRedirect();
})->with(['dni', 'nie', 'passport']);

test('invalid document types or numbers are rejected', function (string $field, string $value): void {
    $this->post(route('pre-registrations.store'), preRegistrationPayload([$field => $value]))
        ->assertSessionHasErrors($field);
})->with([
    ['document_type', 'otro'],
    ['document_number', ''],
    ['document_number', 'A1'],
]);

test('valid localities are accepted', function (string $city): void {
    $this->post(route('pre-registrations.store'), preRegistrationPayload(['city' => $city]))->assertRedirect();
})->with(['Madrid', 'Alcalá de Henares']);

test('a numeric-only locality is rejected', function () {
    $this->post(route('pre-registrations.store'), preRegistrationPayload(['city' => '12345']))
        ->assertSessionHasErrors('city');
});

test('arbitrary objective and source values are rejected', function () {
    $this->post(route('pre-registrations.store'), preRegistrationPayload([
        'objectives' => ['objetivo_inventado'],
        'discovery_channel' => 'canal_inventado',
    ]))->assertSessionHasErrors(['objectives.0', 'discovery_channel']);
});

test('a positive caaf answer with an authorized decision finalizes into a member', function () {
    $this->post(route('pre-registrations.store'), preRegistrationPayload([
        'caaf' => ['chest_activity' => '1'],
        'review_decision' => 'can_continue',
        'review_observations' => 'La persona declara que continuará con las indicaciones recibidas.',
    ]))->assertRedirect();

    $preRegistration = MemberPreRegistration::query()->sole();

    expect($preRegistration->status)->toBe(MemberPreRegistrationStatus::ReadyForFinalization)
        ->and($preRegistration->triggeringCircumstances())->toMatchArray(['caaf' => ['chest_activity' => true]])
        ->and($preRegistration->reviews()->count())->toBe(1)
        ->and(Member::query()->count())->toBe(1);
});

test('pregnancy requires the same review flow', function () {
    $this->post(route('pre-registrations.store'), preRegistrationPayload([
        'pregnancy' => '1',
        'review_decision' => 'do_not_continue_for_now',
        'review_observations' => 'La persona decide no continuar por el momento.',
    ]))->assertRedirect();

    expect(MemberPreRegistration::query()->sole()->status)->toBe(MemberPreRegistrationStatus::OnHold);
});

test('a triggering pre-registration cannot continue without its review decision and observations', function () {
    $this->post(route('pre-registrations.store'), preRegistrationPayload([
        'pregnancy' => '1',
    ]))->assertSessionHasErrors(['review_decision', 'review_observations']);

    expect(MemberPreRegistration::query()->count())->toBe(0);
});

test('a receptionist can create normal and review-required pre-registrations without recording a review', function () {
    OrganizationMembership::query()->where('user_id', $this->user->id)->sole()->update([
        'role' => OrganizationRole::Receptionist,
    ]);

    $this->post(route('pre-registrations.store'), preRegistrationPayload())->assertRedirect();
    $response = $this->post(route('pre-registrations.store'), preRegistrationPayload([
        'caaf' => [
            'heart_condition' => '0',
            'chest_activity' => '0',
            'chest_rest' => '0',
            'dizziness' => '1',
            'bones_joints' => '0',
            'medication' => '0',
            'other_reason' => '0',
        ],
    ]));

    $reviewRequired = MemberPreRegistration::query()->where('status', MemberPreRegistrationStatus::RequiresReview)->sole();
    $response->assertRedirect(route('pre-registrations.show', $reviewRequired));

    expect(MemberPreRegistration::query()->where('status', MemberPreRegistrationStatus::ReadyForFinalization)->count())->toBe(1)
        ->and(MemberPreRegistration::query()->where('status', MemberPreRegistrationStatus::RequiresReview)->count())->toBe(1)
        ->and($reviewRequired->reviews()->count())->toBe(0);
});

test('a receptionist cannot inject a review decision through the wizard', function () {
    OrganizationMembership::query()->where('user_id', $this->user->id)->sole()->update([
        'role' => OrganizationRole::Receptionist,
    ]);

    $this->post(route('pre-registrations.store'), preRegistrationPayload([
        'pregnancy' => '1',
        'review_decision' => MemberPreRegistrationReviewDecision::CanContinue->value,
        'review_observations' => 'Intento no autorizado.',
    ]))->assertForbidden();

    expect(MemberPreRegistration::query()->doesntExist())->toBeTrue();
});

test('a receptionist cannot register a can continue review decision', function (): void {
    $decision = MemberPreRegistrationReviewDecision::CanContinue;
    OrganizationMembership::query()->where('user_id', $this->user->id)->sole()->update([
        'role' => OrganizationRole::Receptionist,
    ]);
    $preRegistration = MemberPreRegistration::create([
        'organization_id' => $this->organization->id,
        'status' => MemberPreRegistrationStatus::RequiresReview,
        'intake' => ['pregnancy' => true],
    ]);

    $this->post(route('pre-registrations.reviews.store', $preRegistration), [
        'decision' => $decision->value,
        'observations' => 'Intento no autorizado.',
    ])->assertForbidden();

    expect($preRegistration->refresh()->status)->toBe(MemberPreRegistrationStatus::RequiresReview)
        ->and($preRegistration->reviews()->doesntExist())->toBeTrue();
});

test('an owner can record a review through the authorized endpoint', function (): void {
    $role = OrganizationRole::Owner;
    OrganizationMembership::query()->where('user_id', $this->user->id)->sole()->update([
        'role' => $role,
    ]);
    $preRegistration = MemberPreRegistration::create([
        'organization_id' => $this->organization->id,
        'status' => MemberPreRegistrationStatus::RequiresReview,
        'intake' => ['caaf' => ['heart_condition' => true]],
    ]);

    $this->post(route('pre-registrations.reviews.store', $preRegistration), [
        'decision' => MemberPreRegistrationReviewDecision::CanContinue->value,
        'observations' => 'Revisión autorizada.',
    ])->assertRedirect();

    expect($preRegistration->refresh()->status)->toBe(MemberPreRegistrationStatus::ReadyForFinalization)
        ->and($preRegistration->reviews()->count())->toBe(1);
});

test('a receptionist cannot register a do not continue review decision', function (): void {
    OrganizationMembership::query()->where('user_id', $this->user->id)->sole()->update([
        'role' => OrganizationRole::Receptionist,
    ]);
    $preRegistration = MemberPreRegistration::create([
        'organization_id' => $this->organization->id,
        'status' => MemberPreRegistrationStatus::RequiresReview,
        'intake' => ['pregnancy' => true],
    ]);

    $this->post(route('pre-registrations.reviews.store', $preRegistration), [
        'decision' => MemberPreRegistrationReviewDecision::DoNotContinueForNow->value,
        'observations' => 'Unauthorized attempt.',
    ])->assertForbidden();

    expect($preRegistration->refresh()->status)->toBe(MemberPreRegistrationStatus::RequiresReview)
        ->and($preRegistration->reviews()->doesntExist())->toBeTrue();
});

test('admin and trainer can record a review through the authorized endpoint', function (): void {
    foreach ([OrganizationRole::Admin, OrganizationRole::Trainer] as $role) {
        OrganizationMembership::query()->where('user_id', $this->user->id)->sole()->update([
            'role' => $role,
        ]);
        $preRegistration = MemberPreRegistration::create([
            'organization_id' => $this->organization->id,
            'status' => MemberPreRegistrationStatus::RequiresReview,
            'intake' => ['caaf' => ['heart_condition' => true]],
        ]);

        $this->post(route('pre-registrations.reviews.store', $preRegistration), [
            'decision' => MemberPreRegistrationReviewDecision::CanContinue->value,
            'observations' => 'Authorized review.',
        ])->assertRedirect();

        expect($preRegistration->refresh()->status)->toBe(MemberPreRegistrationStatus::ReadyForFinalization)
            ->and($preRegistration->reviews()->count())->toBe(1);
    }
});

test('a review requires observations', function () {
    $preRegistration = MemberPreRegistration::create([
        'organization_id' => $this->organization->id,
        'status' => MemberPreRegistrationStatus::RequiresReview,
        'intake' => ['caaf' => ['dizziness' => true]],
    ]);

    $this->post(route('pre-registrations.reviews.store', $preRegistration), [
        'decision' => 'can_continue',
    ])->assertSessionHasErrors('observations');

    expect($preRegistration->reviews)->toHaveCount(0);
});

test('a can continue review records its reviewer timestamp and original triggering circumstances', function () {
    $preRegistration = MemberPreRegistration::create([
        'organization_id' => $this->organization->id,
        'status' => MemberPreRegistrationStatus::RequiresReview,
        'intake' => ['caaf' => ['heart_condition' => true]],
    ]);

    $this->post(route('pre-registrations.reviews.store', $preRegistration), [
        'decision' => 'can_continue',
        'observations' => 'La persona declara que seguirá las indicaciones que ha recibido.',
    ])->assertRedirect();

    $review = $preRegistration->refresh()->reviews()->sole();

    expect($preRegistration->status)->toBe(MemberPreRegistrationStatus::ReadyForFinalization)
        ->and($review->reviewed_by_user_id)->toBe($this->user->id)
        ->and($review->reviewed_at)->not->toBeNull()
        ->and($review->triggering_circumstances)->toBe(['caaf' => ['heart_condition' => true]]);
});

test('a do not continue review places the pre-registration on hold and it can be resumed', function () {
    $preRegistration = MemberPreRegistration::create([
        'organization_id' => $this->organization->id,
        'status' => MemberPreRegistrationStatus::RequiresReview,
        'intake' => ['pregnancy' => true],
    ]);

    $this->post(route('pre-registrations.reviews.store', $preRegistration), [
        'decision' => 'do_not_continue_for_now',
        'observations' => 'La persona prefiere no continuar por ahora.',
    ])->assertRedirect();

    expect($preRegistration->refresh()->status)->toBe(MemberPreRegistrationStatus::OnHold)
        ->and(Member::query()->count())->toBe(0);

    $this->post(route('pre-registrations.resume', $preRegistration))->assertRedirect();

    expect($preRegistration->refresh()->status)->toBe(MemberPreRegistrationStatus::RequiresReview);
});

test('a later review appends without overwriting the previous snapshot', function () {
    $preRegistration = MemberPreRegistration::create([
        'organization_id' => $this->organization->id,
        'status' => MemberPreRegistrationStatus::RequiresReview,
        'intake' => ['caaf' => ['medication' => true]],
    ]);

    $this->post(route('pre-registrations.reviews.store', $preRegistration), [
        'decision' => 'do_not_continue_for_now',
        'observations' => 'Pendiente de retomar la conversación.',
    ])->assertRedirect();
    $firstReview = $preRegistration->refresh()->reviews()->sole();

    $this->post(route('pre-registrations.resume', $preRegistration))->assertRedirect();
    $this->post(route('pre-registrations.reviews.store', $preRegistration), [
        'decision' => 'can_continue',
        'observations' => 'La persona desea continuar más adelante.',
    ])->assertRedirect();

    expect($preRegistration->refresh()->reviews()->count())->toBe(2)
        ->and($firstReview->refresh()->triggering_circumstances)->toBe(['caaf' => ['medication' => true]])
        ->and($preRegistration->status)->toBe(MemberPreRegistrationStatus::ReadyForFinalization);
});

test('the existing registered member lifecycle remains unchanged', function () {
    $member = Member::create([
        'organization_id' => $this->organization->id,
        'first_name' => 'Ana',
        'last_name' => 'Ruiz',
        'status' => MemberStatus::Registered,
        'started_on' => today(),
    ]);

    $this->post(route('attendance.store'), ['code' => $member->check_in_token])->assertRedirect();

    expect($member->refresh()->status)->toBe(MemberStatus::Active)
        ->and($member->attendances)->toHaveCount(1);
});

test('a ready pre-registration can be finalized into a registered member with its intake data', function () {
    $preRegistration = finalizablePreRegistration($this->organization, $this->location, $this->user);

    $response = $this->post(route('pre-registrations.finalize', $preRegistration));
    $member = Member::query()->sole();

    $response->assertRedirect(route('miembros.show', $member));
    expect($member->organization_id)->toBe($this->organization->id)
        ->and($member->location_id)->toBe($this->location->id)
        ->and($member->created_by_user_id)->toBe($this->user->id)
        ->and($member->first_name)->toBe('Lucía')
        ->and($member->last_name)->toBe('Martín')
        ->and($member->public_alias)->toBe('Lucía M.')
        ->and($member->national_id)->toBe('12345678A')
        ->and($member->email)->toBe('lucia@example.test')
        ->and($member->phone)->toBe('600000000')
        ->and($member->birth_date->toDateString())->toBe('1990-01-02')
        ->and($member->address)->toBe('Calle Mayor 10')
        ->and($member->city)->toBe('Madrid')
        ->and($member->client_type)->toBe('presencial')
        ->and($member->status)->toBe(MemberStatus::Registered)
        ->and($member->sessions_remaining)->toBe(0)
        ->and($member->intake)->toMatchArray([
            'document_type' => 'dni',
            'objectives' => ['mejorar_salud'],
        ]);

    expect($preRegistration->refresh()->member_id)->toBe($member->id)
        ->and($preRegistration->finalized_at)->not->toBeNull()
        ->and($preRegistration->finalized_by_user_id)->toBe($this->user->id);
    $this->assertDatabaseCount('member_payments', 0);
    $this->assertDatabaseCount('member_attendances', 0);
    $this->assertDatabaseCount('session_movements', 0);
    $this->assertDatabaseCount('session_settlements', 0);
    $this->assertDatabaseCount('member_portal_accesses', 0);
});

test('finalizing the same pre-registration twice returns the original member', function () {
    $preRegistration = finalizablePreRegistration($this->organization, $this->location, $this->user);

    $this->post(route('pre-registrations.finalize', $preRegistration))->assertRedirect();
    $member = Member::query()->sole();
    $this->post(route('pre-registrations.finalize', $preRegistration))
        ->assertRedirect(route('miembros.show', $member));

    expect(Member::query()->count())->toBe(1)
        ->and($preRegistration->refresh()->member_id)->toBe($member->id);
});

test('only a ready pre-registration can be finalized', function () {
    foreach ([MemberPreRegistrationStatus::Draft, MemberPreRegistrationStatus::RequiresReview, MemberPreRegistrationStatus::OnHold] as $status) {
        $preRegistration = finalizablePreRegistration($this->organization, $this->location, $this->user);
        $preRegistration->update(['status' => $status]);

        $this->post(route('pre-registrations.finalize', $preRegistration))->assertStatus(409);
    }

    expect(Member::query()->doesntExist())->toBeTrue();
});

test('a pre-registration from another organization cannot be finalized', function () {
    $otherOrganization = Organization::factory()->create();
    $otherLocation = Location::factory()->for($otherOrganization)->create();
    $preRegistration = finalizablePreRegistration($otherOrganization, $otherLocation, $this->user);

    $this->post(route('pre-registrations.finalize', $preRegistration))->assertNotFound();

    expect(Member::query()->doesntExist())->toBeTrue();
});

test('each permitted role can finalize a ready pre-registration', function (OrganizationRole $role): void {
    OrganizationMembership::query()->where('user_id', $this->user->id)->sole()->update(['role' => $role]);
    $preRegistration = finalizablePreRegistration($this->organization, $this->location, $this->user);

    $this->post(route('pre-registrations.finalize', $preRegistration))->assertRedirect();

    expect(Member::query()->count())->toBe(1)
        ->and($preRegistration->refresh()->finalized_by_user_id)->toBe($this->user->id);
})->with([
    'owner' => [OrganizationRole::Owner],
    'admin' => [OrganizationRole::Admin],
    'trainer' => [OrganizationRole::Trainer],
    'receptionist' => [OrganizationRole::Receptionist],
]);

test('the new registration flow excludes the removed interview fields and accepts only objective importance from one to ten', function (): void {
    $this->get(route('miembros.create'))
        ->assertOk()
        ->assertSee('Documentos y firma')
        ->assertDontSee('name="desired_start_on"', false)
        ->assertDontSee('name="thinking_about_start"', false)
        ->assertDontSee('name="body_image_rating"', false)
        ->assertDontSee('name="trainer_intake_reviewed"', false);

    foreach ([0, 11] as $importance) {
        $this->post(route('pre-registrations.store'), preRegistrationPayload([
            'objective_importance' => $importance,
        ]))->assertSessionHasErrors('objective_importance');
    }

    $this->post(route('pre-registrations.store'), preRegistrationPayload([
        'desired_start_on' => '2026-11-01',
        'thinking_about_start' => 'Seis meses',
        'body_image_rating' => 7,
        'objective_importance' => 10,
    ]))->assertRedirect();

    $member = Member::query()->sole();
    expect($member->intake)->not->toHaveKeys(['desired_start_on', 'thinking_about_start', 'body_image_rating'])
        ->and($member->intake['objective_importance'])->toBe(10);
});

test('the documents step renders the legacy documents, identity, acceptances, and only the member signature', function (): void {
    $this->get(route('miembros.create'))
        ->assertOk()
        ->assertSee('INFORME CONSENTIMIENTO INFORMADO')
        ->assertSee('INFORME DE ASUNCIÓN DE RIESGOS')
        ->assertSee('data-pre-registration-documents-step', false)
        ->assertSee('data-informed-consent-document', false)
        ->assertSee('data-risk-assumption-document', false)
        ->assertSee('data-member-document-name', false)
        ->assertSee('data-member-document', false)
        ->assertSee('data-member-risk-name', false)
        ->assertSee("dni: 'DNI'", false)
        ->assertSee('document_number', false)
        ->assertSee('name="informed_consent_accepted"', false)
        ->assertSee('name="risk_assumption_accepted"', false)
        ->assertSee('data-member-signature', false)
        ->assertDontSee('name="trainer_intake_reviewed"', false)
        ->assertDontSee('Firma del entrenador')
        ->assertDontSee('Guardar borrador');

    expect(file_get_contents(resource_path('css/app.css')))
        ->not->toContain(".wizard-step[data-step='4'] > .tfc-consent { display: none; }");
});

test('the final registration rejects a missing acceptance', function (): void {
    $this->post(route('pre-registrations.store'), preRegistrationPayload([
        'informed_consent_accepted' => '0',
    ]))->assertSessionHasErrors('informed_consent_accepted');

    expect(MemberPreRegistration::query()->doesntExist())->toBeTrue()
        ->and(Member::query()->doesntExist())->toBeTrue();
});

test('the final registration rejects a missing member signature', function (): void {
    $this->post(route('pre-registrations.store'), preRegistrationPayload([
        'member_signature' => '',
    ]))->assertSessionHasErrors('member_signature');

    expect(MemberPreRegistration::query()->doesntExist())->toBeTrue()
        ->and(Member::query()->doesntExist())->toBeTrue();
});

test('finalization keeps the member signature and acceptance evidence on private storage', function (): void {
    Storage::fake('local');

    $this->post(route('pre-registrations.store'), preRegistrationPayload())->assertRedirect();

    $preRegistration = MemberPreRegistration::query()->sole();
    $member = Member::query()->sole();

    expect($preRegistration->informed_consent_accepted)->toBeTrue()
        ->and($preRegistration->risk_assumption_accepted)->toBeTrue()
        ->and($preRegistration->consent_version)->toBe('2026-09-11')
        ->and($preRegistration->consent_accepted_at)->not->toBeNull()
        ->and($preRegistration->member_signature_path)->toStartWith('member-signatures/')
        ->and($member->intake['member_signature_path'])->toBe($preRegistration->member_signature_path)
        ->and($member->created_by_user_id)->toBe($this->user->id);
    Storage::disk('local')->assertExists($preRegistration->member_signature_path);
});

test('the incremental evidence migration provides all finalization evidence columns', function (): void {
    expect(Schema::hasColumns('member_pre_registrations', [
        'member_id',
        'finalized_at',
        'finalized_by_user_id',
        'informed_consent_accepted',
        'risk_assumption_accepted',
        'consent_version',
        'consent_accepted_at',
        'member_signature_path',
    ]))->toBeTrue();
});

test('a database persistence failure removes the newly stored member signature', function (): void {
    Storage::fake('local');
    MemberPreRegistration::creating(function (): void {
        throw new RuntimeException('Simulated persistence failure.');
    });

    try {
        $this->withoutExceptionHandling();
        expect(fn () => $this->post(route('pre-registrations.store'), preRegistrationPayload()))
            ->toThrow(RuntimeException::class);
        expect(Storage::disk('local')->allFiles('member-signatures'))->toBeEmpty();
    } finally {
        MemberPreRegistration::flushEventListeners();
    }
});

test('member profiles show an initial measurement pending notice only before the first progress entry', function (): void {
    $member = Member::create([
        'organization_id' => $this->organization->id,
        'first_name' => 'Ana',
        'last_name' => 'Ruiz',
        'status' => MemberStatus::Registered,
        'started_on' => today(),
    ]);

    $this->get(route('miembros.show', $member))
        ->assertOk()
        ->assertSee('Medición inicial pendiente')
        ->assertSee('Registrar medición');

    $member->progressEntries()->create([
        'recorded_on' => today(),
        'metrics' => ['weight_kg' => 60],
    ]);

    $this->get(route('miembros.show', $member))
        ->assertOk()
        ->assertDontSee('Medición inicial pendiente');
});
