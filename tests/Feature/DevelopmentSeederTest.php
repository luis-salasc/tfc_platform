<?php

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\OrganizationMembership;
use App\Models\User;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Support\Facades\Hash;

test('el seeder de desarrollo es repetible y crea el acceso propietario', function () {
    config()->set([
        'tfc.development.owner_name' => 'Juanma',
        'tfc.development.owner_email' => 'juanma@tfc.test',
        'tfc.development.owner_password' => 'PasswordLocal!2026',
    ]);

    $this->seed(DevelopmentSeeder::class);
    $this->seed(DevelopmentSeeder::class);

    $this->assertDatabaseCount('organizations', 1);
    $this->assertDatabaseCount('locations', 1);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('organization_memberships', 1);

    $owner = User::query()
        ->where('email', 'juanma@tfc.test')
        ->firstOrFail();

    $membership = OrganizationMembership::query()
        ->where('user_id', $owner->id)
        ->firstOrFail();

    expect($owner->name)->toBe('Juanma')
        ->and(Hash::check('PasswordLocal!2026', $owner->password))->toBeTrue()
        ->and($membership->role)->toBe(OrganizationRole::Owner)
        ->and($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->organization->slug)->toBe('the-fitness-club');
});
