<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = collect([
            'timeclock.request_correction',
            'timeclock.view',
            'timeclock.resolve_corrections',
        ])->mapWithKeys(fn (string $name) => [$name => Permission::findOrCreate($name, 'web')]);

        foreach (['owner', 'admin', 'trainer', 'receptionist', 'staff'] as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->givePermissionTo($permissions['timeclock.request_correction']);
        }
        foreach (['owner', 'admin'] as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->givePermissionTo($permissions['timeclock.view']);
            $role->givePermissionTo($permissions['timeclock.resolve_corrections']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', ['timeclock.request_correction', 'timeclock.view', 'timeclock.resolve_corrections'])->where('guard_name', 'web')->delete();
    }
};
