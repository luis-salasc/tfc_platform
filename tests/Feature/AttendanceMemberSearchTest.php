<?php

use App\Enums\MembershipStatus;
use App\Enums\MemberStatus;
use App\Enums\OrganizationRole;
use App\Enums\SessionMovementType;
use App\Models\Member;
use App\Models\MemberAttendance;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SessionMovement;
use App\Models\User;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($this->user)->create([
        'role' => OrganizationRole::Owner,
        'status' => MembershipStatus::Active,
    ]);
    $this->actingAs($this->user);
});

function attendanceSearchMember(Organization $organization, array $attributes = []): Member
{
    return Member::create([
        'organization_id' => $organization->id,
        'first_name' => 'Ana',
        'last_name' => 'Ruiz',
        'national_id' => '12345678A',
        'phone' => '600123456',
        'status' => MemberStatus::Active,
        'started_on' => today(),
        'sessions_remaining' => 0,
        'intake' => ['document_type' => 'DNI'],
        ...$attributes,
    ]);
}

test('attendance member search requires an authenticated attendance operator', function () {
    auth()->logout();

    $this->getJson(route('attendance.members.search', ['q' => 'An']))->assertUnauthorized();

    $staff = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($staff)->create([
        'role' => OrganizationRole::Staff,
        'status' => MembershipStatus::Active,
    ]);

    $this->actingAs($staff)
        ->getJson(route('attendance.members.search', ['q' => 'An']))
        ->assertForbidden();
});

test('attendance member search is organization scoped and returns only eligible members', function () {
    $visible = attendanceSearchMember($this->organization, ['first_name' => 'Ana', 'last_name' => 'Visible']);
    attendanceSearchMember($this->organization, ['first_name' => 'Ana', 'last_name' => 'Inactiva', 'status' => MemberStatus::Inactive]);
    $otherOrganization = Organization::factory()->create();
    attendanceSearchMember($otherOrganization, ['first_name' => 'Ana', 'last_name' => 'Ajena']);

    $this->getJson(route('attendance.members.search', ['q' => 'Ana']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->id)
        ->assertJsonPath('data.0.document', 'DNI · 12345678A')
        ->assertJsonPath('data.0.status', 'Activo');
});

test('attendance member search supports name surname document and phone', function () {
    $member = attendanceSearchMember($this->organization, [
        'first_name' => 'Lucía',
        'last_name' => 'Martín López',
        'national_id' => 'X1234567Z',
        'phone' => '699888777',
    ]);

    foreach (['Lucía', 'Martín', 'Lucía Martín', 'X1234567Z', '888777'] as $term) {
        $this->getJson(route('attendance.members.search', ['q' => $term]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $member->id);
    }
});

test('attendance member search does not enumerate empty or short queries and caps results', function () {
    foreach (range(1, 12) as $number) {
        attendanceSearchMember($this->organization, [
            'first_name' => "Ana{$number}",
            'national_id' => "9000000{$number}A",
        ]);
    }

    $this->getJson(route('attendance.members.search'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->getJson(route('attendance.members.search', ['q' => 'A']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->getJson(route('attendance.members.search', ['q' => 'Ana']))
        ->assertOk()
        ->assertJsonCount(10, 'data');
});

test('the attendance screen uses server side member search instead of preloading a member select', function () {
    attendanceSearchMember($this->organization);

    $this->get(route('attendance.index'))
        ->assertOk()
        ->assertSee('data-attendance-member-search', false)
        ->assertDontSee('<select name="code"', false);
});

test('a selected registered member checks in and becomes active', function () {
    $member = attendanceSearchMember($this->organization, ['status' => MemberStatus::Registered]);

    $this->post(route('attendance.store'), ['code' => (string) $member->id])->assertRedirect();

    expect($member->refresh()->status)->toBe(MemberStatus::Active)
        ->and(MemberAttendance::count())->toBe(1);
});

test('a selected member with sessions consumes one and a member without sessions is pending', function () {
    $withSessions = attendanceSearchMember($this->organization, ['national_id' => '11111111A']);
    SessionMovement::create([
        'member_id' => $withSessions->id,
        'quantity' => 1,
        'type' => SessionMovementType::Payment,
        'occurred_at' => now(),
    ]);

    $this->post(route('attendance.store'), ['code' => (string) $withSessions->id])->assertRedirect();
    expect($withSessions->attendances()->sole()->requires_settlement)->toBeFalse()
        ->and($withSessions->refresh()->sessions_remaining)->toBe(0);

    $withoutSessions = attendanceSearchMember($this->organization, ['national_id' => '22222222A']);
    $this->post(route('attendance.store'), ['code' => (string) $withoutSessions->id])->assertRedirect();
    expect($withoutSessions->attendances()->sole()->requires_settlement)->toBeTrue()
        ->and($withoutSessions->refresh()->sessions_remaining)->toBe(0);
});

test('selected inactive or frozen members are blocked and duplicate attendance is prevented', function () {
    foreach ([MemberStatus::Inactive, MemberStatus::Frozen] as $status) {
        $member = attendanceSearchMember($this->organization, ['status' => $status, 'national_id' => "blocked-{$status->value}"]);
        $this->post(route('attendance.store'), ['code' => (string) $member->id])
            ->assertSessionHasErrors('code');
    }

    $member = attendanceSearchMember($this->organization, ['national_id' => '33333333A']);
    $this->post(route('attendance.store'), ['code' => (string) $member->id])->assertRedirect();
    $this->post(route('attendance.store'), ['code' => (string) $member->id])->assertRedirect();

    expect($member->attendances()->count())->toBe(1);
});
