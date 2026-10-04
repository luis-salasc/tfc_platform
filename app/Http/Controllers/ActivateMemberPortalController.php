<?php

namespace App\Http\Controllers;

use App\Models\MemberPortalAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ActivateMemberPortalController extends Controller
{
    public function create(string $token)
    {
        $access = $this->access($token);

        return view('portal.activate', compact('token', 'access'));
    }

    public function store(Request $request, string $token)
    {
        $access = $this->access($token);
        $data = $request->validate(['password' => ['required', 'confirmed', 'min:12']]);
        $access->user->update(['password' => Hash::make($data['password']), 'email_verified_at' => now()]);
        $access->update(['status' => 'active', 'accepted_at' => now(), 'invite_token_hash' => null, 'invite_expires_at' => null]);

        return to_route('login')->with('status', 'Acceso activado. Ya puedes iniciar sesion.');
    }

    private function access(string $token): MemberPortalAccess
    {
        return MemberPortalAccess::with('user')->where('invite_token_hash', hash('sha256', $token))->where('status', 'invited')->where('invite_expires_at', '>', now())->firstOrFail();
    }
}
