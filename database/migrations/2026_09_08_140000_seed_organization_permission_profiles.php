<?php

use App\Models\OrganizationMembership;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = ['dashboard.view', 'members.view', 'members.manage', 'attendance.view', 'attendance.register', 'payments.view', 'payments.manage', 'incidents.manage', 'settings.manage', 'team.manage'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $profiles = [
            'owner' => $permissions,
            'admin' => $permissions,
            'trainer' => ['dashboard.view', 'members.view', 'members.manage', 'attendance.view', 'attendance.register', 'payments.view', 'payments.manage', 'incidents.manage'],
            'receptionist' => ['members.view', 'members.manage', 'attendance.view', 'attendance.register', 'payments.view', 'payments.manage'],
            'staff' => ['members.view', 'attendance.view'],
            'client' => [],
        ];

        OrganizationMembership::query()->with('organization')->each(function (OrganizationMembership $membership) use ($profiles): void {
            app(PermissionRegistrar::class)->setPermissionsTeamId($membership->organization_id);
            $role = Role::findOrCreate($membership->role->value, 'web');
            $role->syncPermissions($profiles[$membership->role->value] ?? []);
            DB::table('model_has_roles')->updateOrInsert([
                'organization_id' => $membership->organization_id,
                'role_id' => $role->id,
                'model_type' => $membership->user::class,
                'model_id' => $membership->user_id,
            ]);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void {}
};
