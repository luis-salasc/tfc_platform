<?php

use App\Models\Location;
use App\Models\Organization;

test('un centro pertenece a una organización', function () {
    $organization = Organization::factory()->create();

    $location = Location::factory()
        ->for($organization)
        ->create([
            'name' => 'Centro principal',
            'slug' => 'centro-principal',
            'code' => 'MAIN',
        ]);

    expect($location->organization->is($organization))->toBeTrue()
        ->and($organization->locations->contains($location))->toBeTrue();

    $this->assertDatabaseHas('locations', [
        'id' => $location->id,
        'organization_id' => $organization->id,
        'code' => 'MAIN',
    ]);
});

test('dos organizaciones pueden utilizar el mismo código de centro', function () {
    $firstOrganization = Organization::factory()->create();
    $secondOrganization = Organization::factory()->create();

    Location::factory()->for($firstOrganization)->create([
        'slug' => 'centro-principal',
        'code' => 'MAIN',
    ]);

    Location::factory()->for($secondOrganization)->create([
        'slug' => 'centro-principal',
        'code' => 'MAIN',
    ]);

    expect(Location::query()->where('code', 'MAIN')->count())->toBe(2);
});
