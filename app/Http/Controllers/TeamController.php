<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class TeamController extends Controller
{
    use InteractsWithOrganization;

    public function index(Request $request)
    {
        $this->requirePermission($request, 'team.manage');

        return view('team.index', ['memberships' => OrganizationMembership::with('user')->where('organization_id', $this->organization($request)->id)->orderBy('created_at')->get(), 'roles' => OrganizationRole::cases()]);
    }

    public function store(Request $request)
    {
        $this->requirePermission($request, 'team.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', 'min:8'], 'role' => ['required', Rule::enum(OrganizationRole::class)]]);
        if ($data['role'] === OrganizationRole::Owner->value) {
            return back()->withErrors(['role' => 'No se puede crear otro propietario desde esta pantalla.']);
        }
        $organization = $this->organization($request);
        DB::transaction(function () use ($data, $organization, $request): void {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'email_verified_at' => now()]);
            $membership = OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'invited_by_user_id' => $request->user()->id, 'role' => $data['role'], 'status' => MembershipStatus::Active, 'invited_at' => now(), 'joined_at' => now()]);
            $this->syncProfile($membership);
        });

        return back()->with('status', 'Usuario añadido al equipo.');
    }

    public function update(Request $request, OrganizationMembership $membership)
    {
        $this->requirePermission($request, 'team.manage');
        abort_unless($membership->organization_id === $this->organization($request)->id, 404);
        $data = $request->validate(['role' => ['required', Rule::enum(OrganizationRole::class)], 'status' => ['required', Rule::enum(MembershipStatus::class)]]);
        if ($membership->role === OrganizationRole::Owner || $data['role'] === OrganizationRole::Owner->value) {
            return back()->withErrors(['role' => 'El propietario no se modifica desde esta pantalla.']);
        }
        $membership->update($data);
        $this->syncProfile($membership);

        return back()->with('status', 'Permisos actualizados.');
    }

    private function syncProfile(OrganizationMembership $membership): void
    {
        if (! Schema::hasColumn('model_has_roles', 'organization_id')) {
            return;
        }

        $role = Role::where('name', $membership->role->value)
            ->where(function ($query) use ($membership): void {
                $query->where('organization_id', $membership->organization_id)->orWhereNull('organization_id');
            })
            ->firstOrFail();
        DB::table('model_has_roles')->where('organization_id', $membership->organization_id)->where('model_type', User::class)->where('model_id', $membership->user_id)->delete();
        DB::table('model_has_roles')->insert(['organization_id' => $membership->organization_id, 'role_id' => $role->id, 'model_type' => User::class, 'model_id' => $membership->user_id]);
    }
}
