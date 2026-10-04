<?php

namespace App\Http\Controllers;

use App\Models\OrganizationMembership;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PlatformController extends Controller
{
    public function __invoke(Request $request)
    {
        $membership = $request->user()->organizationMemberships()
            ->active()
            ->with(['organization', 'organization.locations'])
            ->first();

        if ($membership instanceof OrganizationMembership) {
            $request->attributes->set('organizationMembership', $membership);
            $request->attributes->set('organization', $membership->organization);
            $request->attributes->set('location', $membership->organization->locations->firstWhere('is_active', true));
        }

        return view('platform.index', [
            'permissions' => Permission::query()->orderBy('name')->get(),
            'roles' => Role::query()->whereNull('organization_id')->orderBy('name')->get()->keyBy('name'),
        ]);
    }

    public function updateProfile(Request $request, string $role)
    {
        abort_unless(in_array($role, ['admin', 'trainer', 'receptionist', 'staff', 'client'], true), 404);
        $data = $request->validate(['permissions' => ['nullable', 'array'], 'permissions.*' => [Rule::exists('permissions', 'name')]]);
        $profile = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $profile->syncPermissions($data['permissions'] ?? []);

        return back()->with('status', "Perfil {$role} actualizado.");
    }
}
