<?php

use App\Enums\MemberStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Location;
use App\Models\Member;
use App\Models\MemberPortalAccess;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    Location::factory()->for($this->organization)->create();
    $this->staff = User::factory()->create();
    OrganizationMembership::factory()->for($this->organization)->for($this->staff)->create([
        'role' => OrganizationRole::Trainer,
        'status' => MembershipStatus::Active,
    ]);
    $this->member = Member::create([
        'organization_id' => $this->organization->id,
        'first_name' => 'Ana',
        'last_name' => 'Garcia',
        'email' => 'ana@example.test',
        'status' => MemberStatus::Active,
        'started_on' => now()->toDateString(),
    ]);
});

test('member portal is disabled by default', function (): void {
    expect(config('member-portal.enabled'))->toBeFalse();
});

test('a direct invitation is not found and has no side effects', function (): void {
    Mail::fake();

    $this->actingAs($this->staff)
        ->post(route('members.portal-invitations.store', $this->member))
        ->assertNotFound();

    expect(MemberPortalAccess::query()->doesntExist())->toBeTrue()
        ->and(User::query()->where('email', $this->member->email)->doesntExist())->toBeTrue();
    Mail::assertNothingSent();
});

test('a blocked invitation cannot alter an internal user with the member email', function (): void {
    $password = Hash::make('existing-internal-password');
    $verifiedAt = now()->subDay()->startOfSecond();
    $internalUser = User::factory()->create([
        'email' => $this->member->email,
        'password' => $password,
        'email_verified_at' => $verifiedAt,
    ]);

    $this->actingAs($this->staff)
        ->post(route('members.portal-invitations.store', $this->member))
        ->assertNotFound();

    expect($internalUser->refresh()->id)->toBe($internalUser->id)
        ->and($internalUser->password)->toBe($password)
        ->and($internalUser->email_verified_at?->equalTo($verifiedAt))->toBeTrue()
        ->and(MemberPortalAccess::query()->doesntExist())->toBeTrue();
});

test('an existing invitation token cannot activate or alter its user', function (): void {
    $password = Hash::make('existing-portal-password');
    $portalUser = User::factory()->create([
        'password' => $password,
        'email_verified_at' => null,
    ]);
    $token = Str::random(64);
    $expiresAt = now()->addHours(48)->startOfSecond();
    $access = MemberPortalAccess::create([
        'organization_id' => $this->organization->id,
        'member_id' => $this->member->id,
        'user_id' => $portalUser->id,
        'relationship' => 'holder',
        'status' => 'invited',
        'invite_token_hash' => hash('sha256', $token),
        'invite_expires_at' => $expiresAt,
        'accepted_at' => null,
    ]);

    $this->get(route('portal.activate', $token))->assertNotFound();
    $this->post(route('portal.activate.store', $token), [
        'password' => 'replacement-password',
        'password_confirmation' => 'replacement-password',
    ])->assertNotFound();

    expect($portalUser->refresh()->password)->toBe($password)
        ->and($portalUser->email_verified_at)->toBeNull()
        ->and($access->refresh()->status)->toBe('invited')
        ->and($access->invite_token_hash)->toBe(hash('sha256', $token))
        ->and($access->invite_expires_at?->equalTo($expiresAt))->toBeTrue()
        ->and($access->accepted_at)->toBeNull();
});

test('the member profile hides portal information and invitations while disabled', function (): void {
    $this->actingAs($this->staff)
        ->get(route('miembros.show', $this->member))
        ->assertOk()
        ->assertDontSee('Acceso a plataforma')
        ->assertDontSee('Enviar invitaci')
        ->assertDontSee('Reenviar invitaci');
});

test('normal member operations continue while portal is disabled', function (): void {
    $this->actingAs($this->staff)
        ->post(route('members.progress.store', $this->member), [
            'recorded_on' => now()->toDateString(),
            'weight_kg' => 62.5,
        ])
        ->assertRedirect();

    expect($this->member->progressEntries()->count())->toBe(1);
});

test('enabling the feature lets the request reach the existing portal controller', function (): void {
    config()->set('member-portal.enabled', true);
    $this->member->update(['email' => null]);

    $this->actingAs($this->staff)
        ->post(route('members.portal-invitations.store', $this->member))
        ->assertRedirect()
        ->assertSessionHasErrors('portal');
});
