<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Models\Member;
use App\Models\MemberPayment;
use Illuminate\Http\Request;

class PaymentsController extends Controller
{
    use InteractsWithOrganization;

    public function index(Request $request)
    {
        $this->requireRole($request, OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Trainer);
        $organization = $this->organization($request);
        $query = MemberPayment::with(['member', 'receivedBy'])->where('organization_id', $organization->id);

        if ($request->filled('from')) {
            $query->whereDate('paid_at', '>=', $request->string('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('paid_at', '<=', $request->string('to'));
        }
        if ($request->filled('member_id')) {
            $query->where('member_id', $request->integer('member_id'));
        }
        if ($search = trim((string) $request->string('q'))) {
            $query->whereHas('member', fn ($members) => $members->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('national_id', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
        }

        return view('payments.index', [
            'payments' => $query->latest('paid_at')->paginate(25)->withQueryString(),
            'members' => Member::forOrganization($organization)->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'total' => (clone $query)->sum('amount'),
        ]);
    }
}
