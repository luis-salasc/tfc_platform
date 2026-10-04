<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantPlatformAdmin extends Command
{
    protected $signature = 'platform:grant-admin {email : Email del usuario existente} {--force : Confirma el cambio de privilegio}';

    protected $description = 'Concede acceso de superadministrador de plataforma a un usuario existente';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Este cambio requiere --force.');

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No existe un usuario con ese email.');

            return self::FAILURE;
        }

        $user->update(['is_platform_admin' => true]);
        $this->info("Acceso de plataforma concedido a {$user->email}.");

        return self::SUCCESS;
    }
}
