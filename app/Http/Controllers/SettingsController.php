<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    use InteractsWithOrganization;

    public function edit(Request $request)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);

        return view('settings.club', ['organization' => $this->organization($request)]);
    }

    public function update(Request $request)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255'], 'current_password' => ['nullable', 'current_password'], 'password' => ['nullable', 'confirmed', 'min:8', 'max:255']]);
        $request->user()->fill(['name' => $data['name'], 'email' => $data['email']]);
        if (! empty($data['password'])) {
            $request->user()->password = Hash::make($data['password']);
        }
        $request->user()->save();

        return back()->with('status', 'Configuración actualizada.');
    }
}
