<?php

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\TimeclockCorrectionStatus;
use App\Enums\TimeclockEventType;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\TimeclockCorrectionRequest;
use App\Models\TimeclockEvent;
use App\Models\User;
use App\Services\TimeclockService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->location = Location::factory()->for($this->organization)->create(['timezone' => 'Europe/Madrid']);
    $this->employee = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($this->employee)->create(['role' => OrganizationRole::Owner, 'status' => MembershipStatus::Active]);
    $this->resolver = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($this->resolver)->create(['role' => OrganizationRole::Admin, 'status' => MembershipStatus::Active]);
});

function managementEvent(Organization $organization, Location $location, User $user, TimeclockEventType $type, string $at): TimeclockEvent
{
    return TimeclockEvent::create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'user_id' => $user->id,
        'event_type' => $type,
        'occurred_at' => CarbonImmutable::parse($at, 'Europe/Madrid')->utc(),
        'source' => 'web',
    ]);
}

function managementCorrection(Organization $organization, Location $location, User $employee, string $reason, TimeclockCorrectionStatus $status = TimeclockCorrectionStatus::Pending): TimeclockCorrectionRequest
{
    return TimeclockCorrectionRequest::create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'employee_user_id' => $employee->id,
        'requested_by_user_id' => $employee->id,
        'local_date' => '2026-10-04',
        'correction_type' => 'add',
        'proposed_event_type' => 'clock_in',
        'proposed_occurred_at' => CarbonImmutable::parse('2026-10-04 09:00', 'Europe/Madrid')->utc(),
        'reason' => $reason,
        'status' => $status,
        'resolved_by_user_id' => $status === TimeclockCorrectionStatus::Pending ? null : $employee->id,
        'resolved_at' => $status === TimeclockCorrectionStatus::Pending ? null : now(),
        'resolution_comment' => $status === TimeclockCorrectionStatus::Pending ? null : 'Resuelta para la prueba.',
    ]);
}

test('employee can submit an ADD correction with a mandatory reason', function () {
    $this->actingAs($this->employee);
    $response = $this->post(route('timeclock.corrections.store'), [
        'local_date' => '2026-10-04', 'correction_type' => 'add', 'proposed_event_type' => 'clock_in',
        'proposed_occurred_at' => '2026-10-04T09:00', 'reason' => 'Olvidé fichar la entrada.',
    ]);

    $response->assertRedirect(route('timeclock.corrections.index'));
    expect(TimeclockCorrectionRequest::first())->toMatchArray(['correction_type' => 'add', 'status' => 'pending']);
});

test('CORRECT and ANNUL require an original event', function () {
    $this->actingAs($this->employee);
    foreach (['correct', 'annul'] as $type) {
        $this->post(route('timeclock.corrections.store'), ['local_date' => '2026-10-04', 'correction_type' => $type, 'reason' => 'Motivo'])->assertSessionHasErrors('original_event_id');
    }
});

test('a CORRECT request preserves the original and changes the effective day after approval', function () {
    $originalIn = managementEvent($this->organization, $this->location, $this->employee, TimeclockEventType::ClockIn, '2026-10-04 09:00');
    $originalOut = managementEvent($this->organization, $this->location, $this->employee, TimeclockEventType::ClockOut, '2026-10-04 18:00');
    $this->actingAs($this->employee)->post(route('timeclock.corrections.store'), [
        'local_date' => '2026-10-04', 'correction_type' => 'correct', 'original_event_id' => $originalOut->id,
        'proposed_event_type' => 'clock_out', 'proposed_occurred_at' => '2026-10-04T17:00', 'reason' => 'Hora incorrecta.',
    ]);
    $correction = TimeclockCorrectionRequest::firstOrFail();
    $this->actingAs($this->resolver)->post(route('timeclock.management.corrections.approve', $correction))->assertRedirect();

    expect($originalOut->fresh()->occurred_at->setTimezone('Europe/Madrid')->format('H:i'))->toBe('18:00')
        ->and(app(TimeclockService::class)->day($this->employee, $this->organization, '2026-10-04')['summary']['last_exit'])->toBe('17:00')
        ->and($originalIn->exists)->toBeTrue();
});

test('an ANNUL request removes only the effective event', function () {
    $in = managementEvent($this->organization, $this->location, $this->employee, TimeclockEventType::ClockIn, '2026-10-04 09:00');
    $out = managementEvent($this->organization, $this->location, $this->employee, TimeclockEventType::ClockOut, '2026-10-04 18:00');
    $this->actingAs($this->employee)->post(route('timeclock.corrections.store'), ['local_date' => '2026-10-04', 'correction_type' => 'annul', 'original_event_id' => $out->id, 'reason' => 'Fichaje accidental.']);
    $correction = TimeclockCorrectionRequest::firstOrFail();
    $this->actingAs($this->resolver)->post(route('timeclock.management.corrections.approve', $correction))->assertRedirect();

    $day = app(TimeclockService::class)->day($this->employee, $this->organization, '2026-10-04');
    expect($day['events'])->toHaveCount(1)->and($day['events']->first()->id)->toBe($in->id)->and($out->fresh()->exists)->toBeTrue();
});

test('approval rejects an effective sequence that is invalid', function () {
    $this->actingAs($this->employee)->post(route('timeclock.corrections.store'), [
        'local_date' => '2026-10-04', 'correction_type' => 'add', 'proposed_event_type' => 'clock_out',
        'proposed_occurred_at' => '2026-10-04T18:00', 'reason' => 'Falta entrada.',
    ]);
    $correction = TimeclockCorrectionRequest::firstOrFail();
    $this->actingAs($this->resolver)->post(route('timeclock.management.corrections.approve', $correction))->assertStatus(422);
    expect($correction->refresh()->status->value)->toBe('pending');
});

test('rejected requests do not change the effective day and resolution is immutable', function () {
    $in = managementEvent($this->organization, $this->location, $this->employee, TimeclockEventType::ClockIn, '2026-10-04 09:00');
    $this->actingAs($this->employee)->post(route('timeclock.corrections.store'), ['local_date' => '2026-10-04', 'correction_type' => 'annul', 'original_event_id' => $in->id, 'reason' => 'Error.']);
    $correction = TimeclockCorrectionRequest::firstOrFail();
    $this->actingAs($this->resolver)->post(route('timeclock.management.corrections.reject', $correction), ['resolution_comment' => 'No procede.'])->assertRedirect();
    $this->post(route('timeclock.management.corrections.approve', $correction))
        ->assertRedirect(route('timeclock.management.index'))
        ->assertSessionHas('status', 'Esta solicitud ya fue resuelta.');
    expect(app(TimeclockService::class)->day($this->employee, $this->organization, '2026-10-04')['events'])->toHaveCount(1);
});

test('Trainer cannot resolve corrections through the role bypass', function () {
    $trainer = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($trainer)->create(['role' => OrganizationRole::Trainer, 'status' => MembershipStatus::Active]);
    $this->actingAs($this->employee)->post(route('timeclock.corrections.store'), ['local_date' => '2026-10-04', 'correction_type' => 'add', 'proposed_event_type' => 'clock_in', 'proposed_occurred_at' => '2026-10-04T09:00', 'reason' => 'Olvido.']);
    $correction = TimeclockCorrectionRequest::firstOrFail();
    $this->actingAs($trainer)->post(route('timeclock.management.corrections.approve', $correction))->assertForbidden();
});

test('authorized users can approve their own request with complete audit trail', function () {
    managementEvent($this->organization, $this->location, $this->employee, TimeclockEventType::ClockIn, '2026-10-04 09:00');
    $out = managementEvent($this->organization, $this->location, $this->employee, TimeclockEventType::ClockOut, '2026-10-04 18:00');
    $this->actingAs($this->employee)->post(route('timeclock.corrections.store'), [
        'local_date' => '2026-10-04', 'correction_type' => 'correct', 'original_event_id' => $out->id,
        'proposed_event_type' => 'clock_out', 'proposed_occurred_at' => '2026-10-04T17:00', 'reason' => 'Hora incorrecta.',
    ]);
    $correction = TimeclockCorrectionRequest::firstOrFail();

    $this->post(route('timeclock.management.corrections.approve', $correction))->assertRedirect();
    $correction->refresh();

    expect($correction->status->value)->toBe('approved')
        ->and($correction->requested_by_user_id)->toBe($this->employee->id)
        ->and($correction->resolved_by_user_id)->toBe($this->employee->id)
        ->and(app(TimeclockService::class)->day($this->employee, $this->organization, '2026-10-04')['summary']['last_exit'])->toBe('17:00');
});

test('authorized users can reject their own request with complete audit trail', function () {
    $in = managementEvent($this->organization, $this->location, $this->employee, TimeclockEventType::ClockIn, '2026-10-04 09:00');
    $this->actingAs($this->employee)->post(route('timeclock.corrections.store'), ['local_date' => '2026-10-04', 'correction_type' => 'annul', 'original_event_id' => $in->id, 'reason' => 'Error.']);
    $correction = TimeclockCorrectionRequest::firstOrFail();

    $this->post(route('timeclock.management.corrections.reject', $correction), ['resolution_comment' => 'No procede.'])->assertRedirect();
    $correction->refresh();

    expect($correction->status->value)->toBe('rejected')
        ->and($correction->requested_by_user_id)->toBe($this->employee->id)
        ->and($correction->resolved_by_user_id)->toBe($this->employee->id)
        ->and(app(TimeclockService::class)->day($this->employee, $this->organization, '2026-10-04')['events'])->toHaveCount(1);
});

test('employees see only their own requests and users without resolve permission cannot resolve', function () {
    $other = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($other)->create(['role' => OrganizationRole::Owner, 'status' => MembershipStatus::Active]);
    $staff = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($staff)->create(['role' => OrganizationRole::Staff, 'status' => MembershipStatus::Active]);
    $this->actingAs($this->employee)->post(route('timeclock.corrections.store'), ['local_date' => '2026-10-04', 'correction_type' => 'add', 'proposed_event_type' => 'clock_in', 'proposed_occurred_at' => '2026-10-04T09:00', 'reason' => 'Olvido.']);
    $correction = TimeclockCorrectionRequest::firstOrFail();
    $this->actingAs($other)->get(route('timeclock.corrections.show', $correction))->assertNotFound();
    $this->actingAs($staff)->post(route('timeclock.management.corrections.approve', $correction))->assertForbidden();
    $this->actingAs($staff)->post(route('timeclock.management.corrections.reject', $correction), ['resolution_comment' => 'No autorizado.'])->assertForbidden();
    $this->actingAs($this->employee)->get(route('timeclock.corrections.index'))->assertSee('Olvido.');
});

test('management pending views include only pending corrections and expose their detail', function () {
    managementCorrection($this->organization, $this->location, $this->employee, 'Pendiente visible.');
    managementCorrection($this->organization, $this->location, $this->employee, 'Aprobada oculta.', TimeclockCorrectionStatus::Approved);
    managementCorrection($this->organization, $this->location, $this->employee, 'Rechazada oculta.', TimeclockCorrectionStatus::Rejected);

    $this->actingAs($this->resolver)
        ->get(route('timeclock.management.index'))
        ->assertSee('Correcciones pendientes')
        ->assertSee('Pendiente visible.')
        ->assertDontSee('Aprobada oculta.')
        ->assertDontSee('Rechazada oculta.');

    $this->get(route('timeclock.management.corrections'))
        ->assertSee('Pendiente visible.')
        ->assertDontSee('Aprobada oculta.')
        ->assertDontSee('Rechazada oculta.')
        ->assertSee('Volver a Fichajes');
});

test('resolved corrections disappear from pending views and their detail is not actionable', function () {
    $pending = managementCorrection($this->organization, $this->location, $this->employee, 'Pendiente.');
    $approved = managementCorrection($this->organization, $this->location, $this->employee, 'Aprobada.', TimeclockCorrectionStatus::Approved);
    $rejected = managementCorrection($this->organization, $this->location, $this->employee, 'Rechazada.', TimeclockCorrectionStatus::Rejected);

    $this->actingAs($this->resolver)
        ->get(route('timeclock.management.correction', $pending))
        ->assertSee('Aprobar')
        ->assertSee('Rechazar');

    $this->get(route('timeclock.management.correction', $approved))
        ->assertSee('Aprobada')
        ->assertDontSee('>Aprobar<', false)
        ->assertDontSee('>Rechazar<', false);

    $this->get(route('timeclock.management.correction', $rejected))
        ->assertSee('Rechazada')
        ->assertDontSee('>Aprobar<', false)
        ->assertDontSee('>Rechazar<', false);
});

test('a resolved correction redirects safely and cannot be applied again', function () {
    managementEvent($this->organization, $this->location, $this->employee, TimeclockEventType::ClockIn, '2026-10-04 09:00');
    $correction = managementCorrection($this->organization, $this->location, $this->employee, 'Salida pendiente.');
    $correction->update([
        'proposed_event_type' => TimeclockEventType::ClockOut,
        'proposed_occurred_at' => CarbonImmutable::parse('2026-10-04 18:00', 'Europe/Madrid')->utc(),
    ]);

    $this->actingAs($this->resolver)
        ->post(route('timeclock.management.corrections.approve', $correction))
        ->assertRedirect(route('timeclock.management.index'))
        ->assertSessionHas('status', 'Corrección aprobada correctamente.');

    $workedAfterApproval = app(TimeclockService::class)->day($this->employee, $this->organization, '2026-10-04')['summary']['worked_seconds'];

    $this->post(route('timeclock.management.corrections.reject', $correction), ['resolution_comment' => 'No debe aplicarse.'])
        ->assertRedirect(route('timeclock.management.index'))
        ->assertSessionHas('status', 'Esta solicitud ya fue resuelta.');

    expect($correction->refresh()->status)->toBe(TimeclockCorrectionStatus::Approved)
        ->and(app(TimeclockService::class)->day($this->employee, $this->organization, '2026-10-04')['summary']['worked_seconds'])->toBe($workedAfterApproval);
});

test('timeclock sidebar keeps personal and management contexts mutually exclusive', function () {
    $personalHref = preg_quote(route('timeclock.index'), '/');
    $managementHref = preg_quote(route('timeclock.management.index'), '/');
    $correction = managementCorrection($this->organization, $this->location, $this->employee, 'Pendiente para navegación.');

    $this->actingAs($this->employee);
    foreach ([route('timeclock.index'), route('timeclock.corrections.index')] as $route) {
        $personal = $this->get($route)->getContent();
        expect($personal)->toMatch('/<a href="'.$personalHref.'" class="is-active">Mi fichaje<\\/a>/')
            ->not->toMatch('/<a href="'.$managementHref.'" class="is-active">Fichajes<\\/a>/');
    }

    $this->actingAs($this->resolver);
    foreach ([
        route('timeclock.management.index'),
        route('timeclock.management.corrections'),
        route('timeclock.management.correction', $correction),
    ] as $route) {
        $management = $this->get($route)->getContent();
        expect($management)->toMatch('/<a href="'.$managementHref.'" class="is-active">Fichajes<\\/a>/')
            ->not->toMatch('/<a href="'.$personalHref.'" class="is-active">Mi fichaje<\\/a>/');
    }
});
