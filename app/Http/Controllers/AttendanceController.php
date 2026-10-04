<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Enums\OrganizationRole;
use App\Enums\SessionMovementType;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Models\Member;
use App\Models\MemberAttendance;
use App\Models\SessionMovement;
use App\Services\AttendanceWithoutSessionsIncident;
use App\Services\SessionLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    use InteractsWithOrganization;

    public function __construct(private readonly AttendanceWithoutSessionsIncident $incidents, private readonly SessionLedger $ledger) {}

    public function index(Request $request)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);

        $query = MemberAttendance::with(['member', 'checkedInBy'])->where('organization_id', $this->organization($request)->id);
        if ($request->filled('date')) {
            $query->whereDate('checked_in_at', $request->string('date'));
        }
        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->whereHas('member', fn ($members) => $members->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('national_id', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"));
        }
        if ($request->filled('user_q')) {
            $term = $request->string('user_q');
            $query->whereHas('checkedInBy', fn ($users) => $users->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        }

        return view('attendance.index', ['attendances' => $query->latest('checked_in_at')->paginate(25)->withQueryString()]);
    }

    public function search(Request $request)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);

        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim($data['q'] ?? '');

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $members = Member::forOrganization($this->organization($request))
            ->whereIn('status', [MemberStatus::Registered, MemberStatus::Active])
            ->where(function ($query) use ($term): void {
                foreach (preg_split('/\s+/', $term) as $token) {
                    $like = "%{$token}%";
                    $query->where(fn ($member) => $member
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('national_id', 'like', $like)
                        ->orWhere('phone', 'like', $like));
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'national_id', 'phone', 'status', 'intake']);

        return response()->json([
            'data' => $members->map(fn (Member $member) => [
                'id' => $member->id,
                'name' => trim("{$member->first_name} {$member->last_name}"),
                'document' => trim(implode(' · ', array_filter([data_get($member->intake, 'document_type'), $member->national_id]))),
                'phone' => $member->phone,
                'status' => $member->status->label(),
                'available_sessions' => max($this->ledger->mathematicalBalance($member), 0),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer, OrganizationRole::Receptionist);
        $data = $request->validate(['code' => ['required', 'string', 'max:100']]);
        $organization = $this->organization($request);
        $member = Member::forOrganization($organization)->where(fn ($query) => $query->where('check_in_token', $data['code'])->orWhere('national_id', $data['code'])->orWhere('id', $data['code']))->first();
        if (! $member) {
            return back()->withErrors(['code' => 'No se ha encontrado el miembro.']);
        }
        if (! in_array($member->status, [MemberStatus::Registered, MemberStatus::Active], true)) {
            return back()->withErrors(['code' => 'El miembro no está activo.']);
        }

        $result = DB::transaction(function () use ($request, $organization, $member) {
            $member = Member::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            $alreadyCheckedIn = $member->attendances()->where('checked_in_at', '>=', now()->startOfDay())->lockForUpdate()->exists();
            if ($alreadyCheckedIn) {
                return 'Este miembro ya registró su entrada hoy.';
            }
            $requiresSettlement = $this->ledger->mathematicalBalance($member) <= 0;
            $attendance = $member->attendances()->create(['organization_id' => $organization->id, 'location_id' => $this->locationId($request), 'checked_in_by_user_id' => $request->user()->id, 'checked_in_at' => now(), 'requires_settlement' => $requiresSettlement]);
            SessionMovement::create([
                'member_id' => $member->id,
                'quantity' => -1,
                'type' => SessionMovementType::Attendance,
                'source_type' => MemberAttendance::class,
                'source_id' => $attendance->id,
                'occurred_at' => $attendance->checked_in_at,
                'created_by_user_id' => $request->user()->id,
            ]);
            $member->update(['sessions_remaining' => max($this->ledger->mathematicalBalance($member), 0)]);
            if ($requiresSettlement) {
                $this->incidents->create($organization, $member, $attendance, $request->user()->id);
            }
            if ($member->status === MemberStatus::Registered) {
                $member->update(['status' => MemberStatus::Active]);
            }

            return "Entrada registrada para {$member->first_name}. Sesiones restantes: {$member->refresh()->sessions_remaining}.";
        });

        return back()->with('status', $result);
    }
}
