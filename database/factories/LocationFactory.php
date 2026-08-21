<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->city().' Training Center';

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('#####'),
            'code' => strtoupper(fake()->unique()->bothify('CTR-###??')),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('+34 9## ### ###'),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => null,
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country_code' => 'ES',
            'timezone' => 'Europe/Madrid',
            'is_active' => true,
            'settings' => [],
        ];
    }
}
