<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Models\Member;
use App\Models\MemberAttendance;
use App\Models\MemberIncident;
use App\Models\MemberPayment;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use InteractsWithOrganization;

    public function __invoke(Request $request)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        $organization = $this->organization($request);
        $today = now()->startOfDay();
        $month = now()->startOfMonth();

        return view('dashboard', [
            'organization' => $organization,
            'stats' => [
                'asistencias_hoy' => MemberAttendance::where('organization_id', $organization->id)->where('checked_in_at', '>=', $today)->count(),
                'miembros_activos' => Member::forOrganization($organization)->where('status', MemberStatus::Active)->count(),
                'altas_mes' => Member::forOrganization($organization)->where('created_at', '>=', $month)->count(),
                'ingresos_mes' => MemberPayment::where('organization_id', $organization->id)->where('paid_at', '>=', $month)->sum('amount'),
                'sesiones_bajas' => Member::forOrganization($organization)->where('status', MemberStatus::Active)->where('sessions_remaining', '<=', 3)->count(),
                'incidencias_abiertas' => MemberIncident::where('organization_id', $organization->id)->where('status', 'open')->count(),
            ],
            'lowSessions' => Member::forOrganization($organization)->where('status', MemberStatus::Active)->where('sessions_remaining', '<=', 3)->orderBy('sessions_remaining')->limit(8)->get(),
            'todayAttendances' => MemberAttendance::with(['member', 'checkedInBy'])->where('organization_id', $organization->id)->where('checked_in_at', '>=', $today)->latest('checked_in_at')->limit(8)->get(),
            'monthPayments' => MemberPayment::with(['member', 'receivedBy'])->where('organization_id', $organization->id)->where('paid_at', '>=', $month)->latest('paid_at')->limit(8)->get(),
            'openIncidents' => MemberIncident::with('member')->where('organization_id', $organization->id)->where('status', 'open')->latest('occurred_at')->limit(8)->get(),
        ]);
    }
}
