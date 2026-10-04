<?php

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\TimeclockEventType;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\TimeclockEvent;
use App\Models\User;
use App\Services\TimeclockService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->location = Location::factory()->for($this->organization)->create(['timezone' => 'Europe/Madrid']);
    $this->user = User::factory()->create();
    $this->membership = OrganizationMembership::factory()->for($this->organization)->for($this->user)->create([
        'role' => OrganizationRole::Owner,
        'status' => MembershipStatus::Active,
    ]);
    $this->actingAs($this->user);
});

function timeclockEvent(Organization $organization, Location $location, User $user, TimeclockEventType $type, CarbonImmutable $at): TimeclockEvent
{
    return TimeclockEvent::create([
        'organization_id' => $organization->id,
        'location_id' => $location->id,
        'user_id' => $user->id,
        'event_type' => $type,
        'occurred_at' => $at->utc(),
        'source' => 'web',
    ]);
}

test('timeclock requires authentication and an active organization membership', function () {
    auth()->logout();
    $this->get(route('timeclock.index'))->assertRedirect(route('login'));

    $user = User::factory()->create();
    $this->actingAs($user)->get(route('timeclock.index'))->assertForbidden();
});

test('an owner without a materialized Spatie role can access and sees the timeclock link', function () {
    expect($this->user->roles)->toBeEmpty();

    $this->get(route('timeclock.index'))
        ->assertOk()
        ->assertSee('Mi fichaje');
});

test('a client membership cannot access timeclock merely because the route exists', function () {
    $client = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($client)->create([
        'role' => OrganizationRole::Client,
        'status' => MembershipStatus::Active,
    ]);

    $this->actingAs($client)->get(route('timeclock.index'))->assertForbidden();
});

test('timeclock authorization uses the existing Spatie permission system', function () {
    $staff = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($staff)->create([
        'role' => OrganizationRole::Staff,
        'status' => MembershipStatus::Active,
    ]);
    $role = Role::findOrCreate('staff', 'web');
    $role->givePermissionTo(Permission::findOrCreate('timeclock.view_own', 'web'));
    $staff->assignRole($role);

    $this->actingAs($staff)->get(route('timeclock.index'))->assertOk();
});

test('timeclock is organization scoped and never trusts client identity fields', function () {
    $otherOrganization = Organization::factory()->create();
    $otherLocation = Location::factory()->for($otherOrganization)->create();
    timeclockEvent($otherOrganization, $otherLocation, $this->user, TimeclockEventType::ClockIn, CarbonImmutable::now());

    $otherUser = User::factory()->create();
    $this->post(route('timeclock.events.store'), [
        'event_type' => 'clock_in',
        'user_id' => $otherUser->id,
        'organization_id' => $otherOrganization->id,
        'location_id' => $otherLocation->id,
        'occurred_at' => '2000-01-01 00:00:00',
    ])->assertRedirect(route('timeclock.index'));

    $event = TimeclockEvent::query()->where('organization_id', $this->organization->id)->sole();
    expect($event->user_id)->toBe($this->user->id)
        ->and($event->location_id)->toBe($this->location->id)
        ->and($event->source)->toBe('web')
        ->and(TimeclockEvent::query()->where('organization_id', $otherOrganization->id)->count())->toBe(1);
});

test('timeclock blocks zero or multiple active locations', function () {
    $this->location->update(['is_active' => false]);
    $this->post(route('timeclock.events.store'), ['event_type' => 'clock_in'])
        ->assertSessionHasErrors('timeclock');
    expect(TimeclockEvent::count())->toBe(0);

    $this->location->update(['is_active' => true]);
    Location::factory()->for($this->organization)->create();
    $this->post(route('timeclock.events.store'), ['event_type' => 'clock_in'])
        ->assertSessionHasErrors('timeclock');
    expect(TimeclockEvent::count())->toBe(0);
});

test('timeclock records the valid state machine and rejects invalid transitions', function () {
    $this->post(route('timeclock.events.store'), ['event_type' => 'clock_in'])->assertRedirect();
    $this->post(route('timeclock.events.store'), ['event_type' => 'clock_in'])->assertSessionHasErrors('timeclock');
    $this->post(route('timeclock.events.store'), ['event_type' => 'break_start'])->assertRedirect();
    $this->post(route('timeclock.events.store'), ['event_type' => 'break_end'])->assertRedirect();
    $this->post(route('timeclock.events.store'), ['event_type' => 'break_start'])->assertRedirect();
    $this->post(route('timeclock.events.store'), ['event_type' => 'break_end'])->assertRedirect();
    $this->post(route('timeclock.events.store'), ['event_type' => 'clock_out'])->assertRedirect();
    $this->post(route('timeclock.events.store'), ['event_type' => 'break_start'])->assertSessionHasErrors('timeclock');

    expect(TimeclockEvent::query()->where('user_id', $this->user->id)->count())->toBe(6);
});

test('time tracking required is independent configuration and does not create events', function () {
    $this->membership->update(['time_tracking_required' => true]);

    $this->get(route('timeclock.index'))->assertOk()->assertSee('El fichaje horario está requerido');
    expect(TimeclockEvent::count())->toBe(0);
});

test('timeclock calculates work excluding pauses in the location timezone', function () {
    $day = CarbonImmutable::now('Europe/Madrid')->startOfDay()->addHours(9);
    timeclockEvent($this->organization, $this->location, $this->user, TimeclockEventType::ClockIn, $day);
    timeclockEvent($this->organization, $this->location, $this->user, TimeclockEventType::BreakStart, $day->addHours(3));
    timeclockEvent($this->organization, $this->location, $this->user, TimeclockEventType::BreakEnd, $day->addHours(3)->addMinutes(30));
    timeclockEvent($this->organization, $this->location, $this->user, TimeclockEventType::ClockOut, $day->addHours(8));

    $summary = app(TimeclockService::class)->today($this->user, $this->organization);
    expect($summary['worked_seconds'])->toBe(27000)
        ->and($summary['location']->id)->toBe($this->location->id);
});

test('timeclock shows Sin iniciar with no events', function () {
    $this->get(route('timeclock.index'))->assertSee('Sin iniciar');
});

test('timeclock shows Trabajando after clock in', function () {
    $this->post(route('timeclock.events.store'), ['event_type' => 'clock_in']);

    $this->get(route('timeclock.index'))->assertSee('Trabajando');
});

test('timeclock shows En pausa after break start', function () {
    $this->post(route('timeclock.events.store'), ['event_type' => 'clock_in']);
    $this->post(route('timeclock.events.store'), ['event_type' => 'break_start']);

    $this->get(route('timeclock.index'))->assertSee('En pausa');
});

test('timeclock shows Trabajando after break end', function () {
    $this->post(route('timeclock.events.store'), ['event_type' => 'clock_in']);
    $this->post(route('timeclock.events.store'), ['event_type' => 'break_start']);
    $this->post(route('timeclock.events.store'), ['event_type' => 'break_end']);

    $this->get(route('timeclock.index'))->assertSee('Trabajando');
});

test('timeclock shows Jornada finalizada after clock out', function () {
    $this->post(route('timeclock.events.store'), ['event_type' => 'clock_in']);
    $this->post(route('timeclock.events.store'), ['event_type' => 'clock_out']);

    $this->get(route('timeclock.index'))->assertSee('Jornada finalizada');
});

test('daily history returns one complete row for a simple workday', function () {
    $day = CarbonImmutable::now($this->location->timezone)->startOfDay()->subDay()->addHours(9);
    timeclockEvent($this->organization, $this->location, $this->user, TimeclockEventType::ClockIn, $day);
    timeclockEvent($this->organization, $this->location, $this->user, TimeclockEventType::ClockOut, $day->addHours(8));

    $history = app(TimeclockService::class)->dailyHistory($this->user, $this->organization, $this->location);
    expect($history->total())->toBe(1)
        ->and($history->first())->toMatchArray(['first_entry' => '09:00', 'last_exit' => '17:00', 'worked_seconds' => 28800, 'break_seconds' => 0, 'status' => 'Completa']);
});

test('daily history sums pauses and multiple work blocks into one row', function () {
    $day = CarbonImmutable::now($this->location->timezone)->startOfDay()->subDay()->addHours(8);
    foreach ([
        [TimeclockEventType::ClockIn, $day],
        [TimeclockEventType::BreakStart, $day->addHours(2)],
        [TimeclockEventType::BreakEnd, $day->addHours(3)],
        [TimeclockEventType::ClockOut, $day->addHours(6)],
        [TimeclockEventType::ClockIn, $day->addHours(8)],
        [TimeclockEventType::ClockOut, $day->addHours(12)],
    ] as [$type, $at]) {
        timeclockEvent($this->organization, $this->location, $this->user, $type, $at);
    }

    $row = app(TimeclockService::class)->dailyHistory($this->user, $this->organization, $this->location)->first();
    expect($row['first_entry'])->toBe('08:00')
        ->and($row['last_exit'])->toBe('20:00')
        ->and($row['break_seconds'])->toBe(3600)
        ->and($row['worked_seconds'])->toBe(32400)
        ->and($row['status'])->toBe('Completa');
});

test('daily history marks current open day En curso and past open day Incompleta', function () {
    $past = CarbonImmutable::now($this->location->timezone)->startOfDay()->subDay()->addHours(9);
    timeclockEvent($this->organization, $this->location, $this->user, TimeclockEventType::ClockIn, $past);
    $current = CarbonImmutable::now($this->location->timezone)->startOfDay()->addHours(9);
    timeclockEvent($this->organization, $this->location, $this->user, TimeclockEventType::ClockIn, $current);

    $rows = app(TimeclockService::class)->dailyHistory($this->user, $this->organization, $this->location)->getCollection();
    expect($rows->first()['status'])->toBe('En curso')
        ->and($rows->last()['status'])->toBe('Incompleta')
        ->and($rows->last()['worked_seconds'])->toBe(0);
});

test('daily history groups by location timezone, paginates and isolates the authenticated user organization', function () {
    $local = CarbonImmutable::now($this->location->timezone)->startOfDay()->subDays(2)->addMinutes(30);
    timeclockEvent($this->organization, $this->location, $this->user, TimeclockEventType::ClockIn, $local);
    timeclockEvent($this->organization, $this->location, $this->user, TimeclockEventType::ClockOut, $local->addHour());
    $otherOrganization = Organization::factory()->create();
    $otherLocation = Location::factory()->for($otherOrganization)->create(['timezone' => $this->location->timezone]);
    timeclockEvent($otherOrganization, $otherLocation, $this->user, TimeclockEventType::ClockIn, $local);

    foreach (range(1, 16) as $daysAgo) {
        $day = CarbonImmutable::now($this->location->timezone)->startOfDay()->subDays($daysAgo + 3)->addHours(8);
        timeclockEvent($this->organization, $this->location, $this->user, TimeclockEventType::ClockIn, $day);
        timeclockEvent($this->organization, $this->location, $this->user, TimeclockEventType::ClockOut, $day->addHours(8));
    }

    $history = app(TimeclockService::class)->dailyHistory($this->user, $this->organization, $this->location);
    expect($history->total())->toBe(17)->and($history->count())->toBe(15)->and($history->last()['date'])->not->toBeNull();
    $this->get(route('timeclock.day', ['date' => $local->toDateString()]))
        ->assertOk()
        ->assertSee('Fichar entrada')
        ->assertDontSee('other');
});

test('daily history pagination does not use a grouped count projection', function () {
    DB::enableQueryLog();
    app(TimeclockService::class)->dailyHistory($this->user, $this->organization, $this->location);

    $queries = collect(DB::getQueryLog())->pluck('query');
    $countQueries = $queries
        ->filter(fn (string $query): bool => str_contains(strtolower($query), 'count('));

    expect($countQueries->every(fn (string $query): bool => ! str_contains(strtolower($query), 'group by')))->toBeTrue();
    expect($queries->every(fn (string $query): bool => ! str_contains(strtolower($query), 'convert_tz')))->toBeTrue();
});

test('daily history resolves local dates with Carbon across winter summer and DST boundaries', function () {
    $cases = [
        ['2026-01-15 23:30:00', '2026-01-16'],
        ['2026-07-15 22:30:00', '2026-07-16'],
        ['2026-03-29 22:30:00', '2026-03-30'],
        ['2026-10-25 23:30:00', '2026-10-26'],
    ];

    foreach ($cases as [$utc, $localDate]) {
        timeclockEvent(
            $this->organization,
            $this->location,
            $this->user,
            TimeclockEventType::ClockIn,
            CarbonImmutable::parse($utc, 'UTC'),
        );
    }

    $dates = app(TimeclockService::class)
        ->dailyHistory($this->user, $this->organization, $this->location)
        ->getCollection()
        ->pluck('date');

    expect($dates->all())->toEqualCanonicalizing(collect($cases)->pluck(1)->all());
});

test('timeclock history presents local dates in Spanish format', function () {
    timeclockEvent(
        $this->organization,
        $this->location,
        $this->user,
        TimeclockEventType::ClockIn,
        CarbonImmutable::parse('2026-01-15 23:30:00', 'UTC'),
    );

    $this->get(route('timeclock.index'))
        ->assertOk()
        ->assertSee('16/01/2026');
});

test('timeclock events are append-only through the exposed routes', function () {
    $this->get(route('timeclock.index'))->assertOk()->assertSee('Fichar entrada');
    expect(collect(Route::getRoutes()->getRoutes())->where('uri', 'mi-fichaje/eventos')->pluck('methods')->flatten()->all())->toContain('POST');
    expect(collect(Route::getRoutes()->getRoutes())->where('uri', 'mi-fichaje/eventos')->pluck('methods')->flatten()->all())->not->toContain('PUT');
});
