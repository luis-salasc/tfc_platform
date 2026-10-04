<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = collect(['timeclock.clock', 'timeclock.view_own'])
            ->mapWithKeys(fn (string $name) => [$name => Permission::findOrCreate($name, 'web')]);

        foreach (['owner', 'admin', 'trainer', 'receptionist', 'staff'] as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');
            foreach ($permissions as $permission) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', ['timeclock.clock', 'timeclock.view_own'])->where('guard_name', 'web')->delete();
    }
};
