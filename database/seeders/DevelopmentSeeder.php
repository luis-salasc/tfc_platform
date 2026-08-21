<?php

namespace Database\Seeders;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException(
                'DevelopmentSeeder solo puede ejecutarse en local o testing.'
            );
        }

        $ownerName = (string) config('tfc.development.owner_name');
        $ownerEmail = (string) config('tfc.development.owner_email');
        $ownerPassword = config('tfc.development.owner_password');

        if (! filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new LogicException('El correo del propietario no es válido.');
        }

        if (! is_string($ownerPassword) || strlen($ownerPassword) < 12) {
            throw new LogicException(
                'TFC_DEVELOPMENT_OWNER_PASSWORD debe tener al menos 12 caracteres.'
            );
        }

        DB::transaction(function () use ($ownerName, $ownerEmail, $ownerPassword): void {
            $organization = Organization::query()->updateOrCreate(
                ['slug' => 'the-fitness-club'],
                [
                    'name' => 'The Fitness Club',
                    'legal_name' => null,
                    'tax_identifier' => null,
                    'email' => null,
                    'phone' => null,
                    'country_code' => 'ES',
                    'timezone' => 'Europe/Madrid',
                    'locale' => 'es',
                    'currency' => 'EUR',
                    'is_active' => true,
                    'settings' => [
                        'receipt_prefix' => 'TFC',
                    ],
                ],
            );

            Location::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => 'MAIN',
                ],
                [
                    'name' => 'Centro principal',
                    'slug' => 'centro-principal',
                    'email' => null,
                    'phone' => null,
                    'address_line_1' => null,
                    'address_line_2' => null,
                    'city' => null,
                    'state' => null,
                    'postal_code' => null,
                    'country_code' => 'ES',
                    'timezone' => 'Europe/Madrid',
                    'is_active' => true,
                    'settings' => [],
                ],
            );

            $owner = User::query()->updateOrCreate(
                ['email' => $ownerEmail],
                [
                    'name' => $ownerName,
                    'email_verified_at' => now(),
                    'password' => Hash::make($ownerPassword),
                ],
            );

            OrganizationMembership::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $owner->id,
                ],
                [
                    'invited_by_user_id' => null,
                    'role' => OrganizationRole::Owner,
                    'status' => MembershipStatus::Active,
                    'invited_at' => null,
                    'joined_at' => now(),
                    'suspended_at' => null,
                    'revoked_at' => null,
                ],
            );
        });

        $this->command?->info(
            "Datos locales preparados para {$ownerEmail}."
        );
    }
}
