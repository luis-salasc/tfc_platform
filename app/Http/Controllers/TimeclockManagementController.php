<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Models\OrganizationMembership;
use App\Models\TimeclockCorrectionRequest;
use App\Services\TimeclockService;
use Illuminate\Http\Request;

class TimeclockManagementController extends Controller
{
    use InteractsWithOrganization;

    public function __construct(private readonly TimeclockService $timeclock) {}

    public function index(Request $request)
    {
        $this->requirePermission($request, 'timeclock.view');
        $organization = $this->organization($request);
        $location = $this->timeclock->activeLocation($organization);
        $data = $request->validate([
            'employee_user_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $employees = OrganizationMembership::query()->with('user')->where('organization_id', $organization->id)->active()->orderBy('user_id')->get();
        $report = collect();
        $totalWorked = 0;
        if (! empty($data['employee_user_id']) && ! empty($data['date_from']) && ! empty($data['date_to'])) {
            $membership = $employees->firstWhere('user_id', (int) $data['employee_user_id']);
            abort_unless($membership, 404);
            $report = $this->timeclock->rangeReport($membership->user, $organization, $location, $data['date_from'], $data['date_to']);
            $totalWorked = $report->sum('worked_seconds');
        }
        $pendingCorrections = TimeclockCorrectionRequest::query()
            ->with('employee')
            ->where('organization_id', $organization->id)
            ->where('status', 'pending')
            ->latest('created_at')
            ->limit(5)
            ->get();

        return view('timeclock.management.index', compact('employees', 'report', 'totalWorked', 'pendingCorrections'));
    }

    public function corrections(Request $request)
    {
        $this->requirePermission($request, 'timeclock.view');
        $corrections = TimeclockCorrectionRequest::query()->with(['employee', 'originalEvent'])->where('organization_id', $this->organization($request)->id)->where('status', 'pending')->latest('created_at')->paginate(20);

        return view('timeclock.management.corrections', compact('corrections'));
    }

    public function correction(Request $request, TimeclockCorrectionRequest $correction)
    {
        $this->requirePermission($request, 'timeclock.view');
        abort_unless($correction->organization_id === $this->organization($request)->id, 404);
        $correction->load(['employee', 'originalEvent', 'requestedBy', 'resolvedBy', 'location']);

        return view('timeclock.management.correction', compact('correction'));
    }
}
