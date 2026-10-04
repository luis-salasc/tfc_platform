<?php

namespace App\Http\Middleware;

use App\Models\OrganizationMembership;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $membership = $request->user()?->organizationMemberships()
            ->active()->with(['organization', 'organization.locations'])->first();

        abort_unless($membership instanceof OrganizationMembership, 403, 'No tienes acceso a una organización activa.');

        $request->attributes->set('organizationMembership', $membership);
        $request->attributes->set('organization', $membership->organization);
        app(PermissionRegistrar::class)->setPermissionsTeamId($membership->organization_id);
        if (Schema::hasColumn('model_has_roles', 'organization_id') && $request->user()->roles()->doesntExist()) {
            $role = Role::findOrCreate($membership->role->value, 'web');
            DB::table('model_has_roles')->updateOrInsert([
                'organization_id' => $membership->organization_id,
                'role_id' => $role->id,
                'model_type' => $request->user()::class,
                'model_id' => $request->user()->id,
            ]);
        }
        // La relación ya viene cargada arriba; consultar de nuevo aquí añadía una
        // tercera consulta a cada navegación del panel.
        $request->attributes->set('location', $membership->organization->locations->firstWhere('is_active', true));

        return $next($request);
    }
}
