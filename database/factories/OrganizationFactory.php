<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('#####'),
            'legal_name' => $name,
            'tax_identifier' => null,
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('+34 6## ### ###'),
            'country_code' => 'ES',
            'timezone' => 'Europe/Madrid',
            'locale' => 'es',
            'currency' => 'EUR',
            'is_active' => true,
            'settings' => [],
        ];
    }
}
