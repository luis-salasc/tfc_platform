<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Models\Member;
use App\Services\MemberQrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class MemberCardController extends Controller
{
    use InteractsWithOrganization;

    public function show(Request $request, Member $member, MemberQrCode $qrCode)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);
        $member = $this->member($request, $member)->load('organization');

        return $this->cardView($member, $qrCode, false);
    }

    public function publicCard(Request $request, Member $member, string $credential, MemberQrCode $qrCode)
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless(hash_equals((string) $member->check_in_token, $credential), 404);
        abort_unless($member->organization?->is_active, 404);

        return $this->cardView($member, $qrCode, true);
    }

    public function regenerate(Request $request, Member $member)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);
        $member = $this->member($request, $member);
        $member->forceFill([
            'check_in_token' => (string) Str::uuid(),
            'check_in_token_rotated_by_user_id' => $request->user()->id,
            'check_in_token_rotated_at' => now(),
        ])->save();

        return to_route('members.card.show', $member)->with('status', 'Código regenerado. El QR y los enlaces anteriores ya no son válidos.');
    }

    private function cardView(Member $member, MemberQrCode $qrCode, bool $public): mixed
    {
        $publicUrl = URL::temporarySignedRoute('members.card.public', now()->addDays(7), [
            'member' => $member,
            'credential' => $member->check_in_token,
        ]);
        $digits = preg_replace('/\D+/', '', (string) $member->phone);
        if (strlen($digits) === 9) {
            $digits = '34'.$digits;
        }
        $message = rawurlencode("Hola {$member->first_name}, aquí tienes tu carnet de acceso a {$member->organization->name}: {$publicUrl}\n\nGuárdalo en tu móvil y presenta el QR en el Kiosko.");

        return view('members.card', [
            'member' => $member,
            'organization' => $member->organization,
            'qrSvg' => $qrCode->svg((string) $member->check_in_token),
            'publicUrl' => $publicUrl,
            'whatsappUrl' => $digits ? "https://wa.me/{$digits}?text={$message}" : null,
            'isPublic' => $public,
        ]);
    }
}
