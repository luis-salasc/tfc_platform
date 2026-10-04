<?php

namespace App\Console\Commands;

use App\Enums\MembershipStatus;
use App\Enums\MemberStatus;
use App\Enums\OrganizationRole;
use App\Models\Member;
use App\Models\MemberAttendance;
use App\Models\MemberPayment;
use App\Models\MemberProgressEntry;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyTfc extends Command
{
    protected $signature = 'tfc:import-legacy {--database=tfc_database : Base de datos heredada} {--dry-run : Solo informa, no escribe}';

    protected $description = 'Importa de forma idempotente los datos de la aplicación PHP heredada.';

    public function handle(): int
    {
        $legacy = DB::connection('legacy');
        $database = (string) $this->option('database');
        $legacy->statement('USE `'.str_replace('`', '``', $database).'`');
        $organization = Organization::query()->where('slug', 'the-fitness-club')->firstOrFail();
        $location = $organization->locations()->where('is_active', true)->first();
        $dryRun = (bool) $this->option('dry-run');
        $users = collect($legacy->table('tfc_administracion')->get())->keyBy('id');
        $this->info("Encontrados {$users->count()} usuarios heredados.");
        if ($dryRun) {
            $this->warn('Simulación: no se modificará nada.');

            return self::SUCCESS;
        }

        $userMap = [];
        foreach ($users as $legacyUser) {
            $user = User::query()->firstOrNew(['email' => $legacyUser->email]);
            if (! $user->exists) {
                $user->name = $legacyUser->nombre;
                $user->password = $legacyUser->password_hash;
                $user->email_verified_at = now();
                $user->save();
            } elseif ($user->email !== config('tfc.development.owner_email')) {
                $user->forceFill(['name' => $legacyUser->nombre, 'password' => $legacyUser->password_hash])->save();
            }
            $role = match ($legacyUser->rol) {
                'admin' => OrganizationRole::Admin, 'entrenador' => OrganizationRole::Trainer, 'recepcionista' => OrganizationRole::Receptionist, default => OrganizationRole::Staff
            };
            OrganizationMembership::query()->updateOrCreate(['organization_id' => $organization->id, 'user_id' => $user->id], ['role' => $role, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
            $userMap[$legacyUser->id] = $user->id;
        }
        $members = $legacy->table('miembros')->orderBy('id')->get();
        $memberMap = [];
        foreach ($members as $legacyMember) {
            $status = match (mb_strtolower((string) $legacyMember->status)) {
                'activo' => MemberStatus::Active, 'congelado' => MemberStatus::Frozen, default => MemberStatus::Inactive
            };
            $intake = collect((array) $legacyMember)->except(['id', 'usuario_id', 'nombre', 'apellidos', 'dni', 'email', 'telefono_completo', 'fecha_nacimiento', 'direccion', 'localidad', 'tipo_cliente', 'status', 'fecha_inicio', 'sesiones_restantes', 'peso_inicial_kg', 'altura_cm', 'medida_cintura_cm', 'medida_brazo_cm', 'medida_pierna_cm', 'medida_cadera_cm', 'medida_pecho_cm', 'porcentaje_grasa', 'masa_muscular_kg', 'porcentaje_agua', 'grasa_visceral', 'edad_metabolica', 'consentimiento_informado_aceptado', 'asuncion_riesgos_aceptada', 'fecha_consentimiento', 'created_at', 'updated_at'])->filter(fn ($value) => $value !== null && $value !== '')->all();
            $metrics = collect((array) $legacyMember)->only(['peso_inicial_kg', 'altura_cm', 'medida_cintura_cm', 'medida_brazo_cm', 'medida_pierna_cm', 'medida_cadera_cm', 'medida_pecho_cm', 'porcentaje_grasa', 'masa_muscular_kg', 'porcentaje_agua', 'grasa_visceral', 'edad_metabolica'])->filter(fn ($value) => $value !== null)->all();
            $startedOn = $legacyMember->fecha_inicio ?: (data_get($legacyMember, 'created_at') ?: now()->toDateString());
            $member = Member::query()->updateOrCreate(['organization_id' => $organization->id, 'legacy_id' => $legacyMember->id], ['location_id' => $location?->id, 'created_by_user_id' => $userMap[$legacyMember->usuario_id] ?? null, 'first_name' => $legacyMember->nombre, 'last_name' => $legacyMember->apellidos, 'national_id' => $legacyMember->dni, 'email' => $legacyMember->email, 'phone' => $legacyMember->telefono_completo, 'birth_date' => $legacyMember->fecha_nacimiento, 'address' => $legacyMember->direccion, 'city' => $legacyMember->localidad, 'client_type' => $legacyMember->tipo_cliente, 'status' => $status, 'started_on' => $startedOn, 'sessions_remaining' => max(0, (int) $legacyMember->sesiones_restantes), 'intake' => $intake, 'initial_metrics' => $metrics, 'informed_consent_accepted' => (bool) $legacyMember->consentimiento_informado_aceptado, 'risk_assumption_accepted' => (bool) $legacyMember->asuncion_riesgos_aceptada, 'consent_date' => $legacyMember->fecha_consentimiento]);
            $memberMap[$legacyMember->id] = $member;
        }
        foreach ($legacy->table('progresos')->get() as $row) {
            if (isset($memberMap[$row->miembro_id])) {
                MemberProgressEntry::query()->updateOrCreate(['member_id' => $memberMap[$row->miembro_id]->id, 'legacy_id' => $row->id], ['recorded_on' => $row->fecha_registro, 'metrics' => collect((array) $row)->only(['peso_kg', 'porcentaje_grasa', 'masa_muscular_kg', 'medida_cintura_cm', 'medida_cadera_cm', 'medida_pecho_cm', 'medida_brazo_cm', 'medida_pierna_cm', 'porcentaje_agua', 'grasa_visceral', 'edad_metabolica'])->filter(fn ($v) => $v !== null)->all(), 'notes' => $row->comentarios]);
            }
        }
        foreach ($legacy->table('historial_pagos')->get() as $row) {
            if (isset($memberMap[$row->miembro_id])) {
                MemberPayment::query()->updateOrCreate(['organization_id' => $organization->id, 'legacy_id' => $row->id], ['location_id' => $location?->id, 'member_id' => $memberMap[$row->miembro_id]->id, 'received_by_user_id' => $userMap[$row->usuario_id] ?? null, 'sessions_purchased' => $row->sesiones_compradas, 'amount' => $row->monto_pagado, 'notes' => $row->comentarios, 'paid_at' => $row->fecha_pago]);
            }
        }
        foreach ($legacy->table('asistencia')->get() as $row) {
            if (isset($memberMap[$row->miembro_id])) {
                MemberAttendance::query()->updateOrCreate(['organization_id' => $organization->id, 'legacy_id' => $row->id], ['location_id' => $location?->id, 'member_id' => $memberMap[$row->miembro_id]->id, 'checked_in_by_user_id' => $userMap[$row->usuario_id] ?? null, 'checked_in_at' => $row->fecha_checkin]);
            }
        }
        $this->info("Importados {$members->count()} miembros y sus históricos.");

        return self::SUCCESS;
    }
}
