<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Models\Member;
use App\Models\MemberPortalAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MemberPortalInvitationController extends Controller
{
    use InteractsWithOrganization;

    public function store(Request $request, Member $member)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);
        $member = $this->member($request, $member);
        if (! $member->email) {
            return back()->withErrors(['portal' => 'El miembro necesita un email para recibir la invitacion.']);
        }

        $user = User::firstOrCreate(['email' => $member->email], [
            'name' => trim("{$member->first_name} {$member->last_name}"),
            'password' => Hash::make(Str::random(48)),
        ]);
        $token = Str::random(64);
        $access = MemberPortalAccess::updateOrCreate([
            'member_id' => $member->id,
            'user_id' => $user->id,
        ], [
            'organization_id' => $member->organization_id,
            'relationship' => 'holder',
            'status' => 'invited',
            'permissions' => ['profile.view', 'attendance.view', 'payments.view'],
            'invite_token_hash' => hash('sha256', $token),
            'invite_expires_at' => now()->addHours(48),
            'accepted_at' => null,
            'revoked_at' => null,
            'granted_by_user_id' => $request->user()->id,
        ]);

        $url = route('portal.activate', $token);
        Mail::raw("Hola {$member->first_name}, activa tu acceso a The Fitness Club en: {$url}\n\nEste enlace caduca en 48 horas.", function ($message) use ($user): void {
            $message->to($user->email)->subject('Activa tu acceso a The Fitness Club');
        });

        return back()->with('status', "Invitacion enviada a {$user->email}. Caduca el {$access->invite_expires_at->format('d/m/Y H:i')}.");
    }
}
