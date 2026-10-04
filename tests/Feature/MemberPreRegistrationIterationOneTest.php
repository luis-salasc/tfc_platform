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

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    Location::factory()->for($this->organization)->create();
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
    ], $overrides);
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

test('a pre-registration without triggering circumstances is ready for finalization', function () {
    $this->post(route('pre-registrations.store'), preRegistrationPayload())->assertRedirect();

    $preRegistration = MemberPreRegistration::query()->sole();

    expect($preRegistration->status)->toBe(MemberPreRegistrationStatus::ReadyForFinalization)
        ->and($preRegistration->client_type)->toBe('presencial')
        ->and($preRegistration->document_type)->toBe('dni')
        ->and($preRegistration->document_number)->toBe('12345678A')
        ->and(Member::query()->count())->toBe(0);
});

test('a draft rehydrates all persisted first-sheet data', function () {
    $this->post(route('pre-registrations.store'), preRegistrationPayload([
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
        'desired_start_on' => '2026-11-01',
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
            'desired_start_on' => '2026-11-01',
            'goal_date' => '2027-02-01',
        ]);

    $this->get(route('pre-registrations.edit', $preRegistration))
        ->assertOk()
        ->assertSee('value="P-1234567"', false)
        ->assertSee('value="Valencia"', false)
        ->assertSee('value="Calle Mayor 10"', false)
        ->assertSee('value="Diseñadora"', false)
        ->assertSee('value="María Pérez"', false)
        ->assertSee('value="2026-11-01"', false)
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

test('a positive caaf answer requires review without creating a member', function () {
    $this->post(route('pre-registrations.store'), preRegistrationPayload([
        'caaf' => ['chest_activity' => '1'],
        'review_decision' => 'can_continue',
        'review_observations' => 'La persona declara que continuará con las indicaciones recibidas.',
    ]))->assertRedirect();

    $preRegistration = MemberPreRegistration::query()->sole();

    expect($preRegistration->status)->toBe(MemberPreRegistrationStatus::ReadyForFinalization)
        ->and($preRegistration->triggeringCircumstances())->toMatchArray(['caaf' => ['chest_activity' => true]])
        ->and($preRegistration->reviews()->count())->toBe(1)
        ->and(Member::query()->count())->toBe(0);
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
    $this->post(route('pre-registrations.store'), preRegistrationPayload([
        'caaf' => [
            'heart_condition' => '0',
            'chest_activity' => '0',
            'chest_rest' => '0',
            'dizziness' => '1',
            'bones_joints' => '0',
            'medication' => '0',
            'other_reason' => '0',
        ],
    ]))->assertRedirect();

    expect(MemberPreRegistration::query()->where('status', MemberPreRegistrationStatus::ReadyForFinalization)->count())->toBe(1)
        ->and(MemberPreRegistration::query()->where('status', MemberPreRegistrationStatus::RequiresReview)->count())->toBe(1)
        ->and(MemberPreRegistration::query()->where('status', MemberPreRegistrationStatus::RequiresReview)->sole()->reviews()->count())->toBe(0);
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
