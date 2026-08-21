<?php

use App\Models\Organization;

test('una organización puede guardarse correctamente', function () {
    $organization = Organization::factory()->create([
        'name' => 'The Fitness Club',
        'slug' => 'the-fitness-club',
        'settings' => [
            'receipt_prefix' => 'TFC',
        ],
    ]);

    expect($organization->name)->toBe('The Fitness Club')
        ->and($organization->slug)->toBe('the-fitness-club')
        ->and($organization->is_active)->toBeTrue()
        ->and($organization->settings)->toBe([
            'receipt_prefix' => 'TFC',
        ]);

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'slug' => 'the-fitness-club',
    ]);
});

test('una organización se elimina de forma recuperable', function () {
    $organization = Organization::factory()->create();

    $organization->delete();

    $this->assertSoftDeleted('organizations', [
        'id' => $organization->id,
    ]);

    expect(Organization::find($organization->id))->toBeNull();
});
