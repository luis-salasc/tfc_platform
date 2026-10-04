<?php

namespace App\Http\Controllers;

use App\Enums\TimeclockCorrectionType;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Models\TimeclockCorrectionRequest;
use App\Services\TimeclockService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TimeclockCorrectionController extends Controller
{
    use InteractsWithOrganization;

    public function __construct(private readonly TimeclockService $timeclock) {}

    public function index(Request $request)
    {
        $this->requirePermission($request, 'timeclock.request_correction');
        $corrections = TimeclockCorrectionRequest::query()
            ->with(['originalEvent', 'resolvedBy'])
            ->where('organization_id', $this->organization($request)->id)
            ->where('employee_user_id', $request->user()->id)
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('timeclock.corrections.index', compact('corrections'));
    }

    public function create(Request $request)
    {
        $this->requirePermission($request, 'timeclock.request_correction');
        $location = $this->timeclock->activeLocation($this->organization($request));
        $date = validator(['date' => $request->query('date', now($location->timezone)->format('Y-m-d'))], ['date' => ['required', 'date_format:Y-m-d']])->validate()['date'];
        $events = $this->timeclock->eventsForCorrectionDate($request->user(), $this->organization($request), $location, $date);

        return view('timeclock.corrections.create', compact('events', 'location', 'date'));
    }

    public function store(Request $request)
    {
        $this->requirePermission($request, 'timeclock.request_correction');
        $data = $request->validate([
            'local_date' => ['required', 'date_format:Y-m-d'],
            'correction_type' => ['required', 'in:add,correct,annul'],
            'original_event_id' => ['nullable', 'integer'],
            'proposed_event_type' => ['nullable', 'in:clock_in,break_start,break_end,clock_out'],
            'proposed_occurred_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:3000'],
        ]);
        $type = TimeclockCorrectionType::from($data['correction_type']);
        if ($type !== TimeclockCorrectionType::Add && empty($data['original_event_id'])) {
            return back()->withErrors(['original_event_id' => 'Selecciona el fichaje original.'])->withInput();
        }
        if ($type !== TimeclockCorrectionType::Annul && (empty($data['proposed_event_type']) || empty($data['proposed_occurred_at']))) {
            return back()->withErrors(['proposed_event_type' => 'Indica el evento y la fecha/hora propuesta.'])->withInput();
        }

        $location = $this->timeclock->activeLocation($this->organization($request));
        $this->timeclock->createCorrectionRequest($request->user(), $this->organization($request), $location, $data);

        return to_route('timeclock.corrections.index')->with('status', 'Solicitud de corrección enviada.');
    }

    public function show(Request $request, TimeclockCorrectionRequest $correction)
    {
        $this->requirePermission($request, 'timeclock.request_correction');
        abort_unless($correction->organization_id === $this->organization($request)->id && $correction->employee_user_id === $request->user()->id, 404);

        return view('timeclock.corrections.show', compact('correction'));
    }

    public function approve(Request $request, TimeclockCorrectionRequest $correction)
    {
        $this->requirePermission($request, 'timeclock.resolve_corrections');
        $this->assertOrganization($request, $correction);
        $data = $request->validate(['resolution_comment' => ['nullable', 'string', 'max:3000']]);
        try {
            $this->timeclock->resolveCorrection($correction, $request->user(), true, $data['resolution_comment'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return to_route('timeclock.management.index')->with('status', $exception->getMessage());
        }

        return to_route('timeclock.management.index')->with('status', 'Corrección aprobada correctamente.');
    }

    public function reject(Request $request, TimeclockCorrectionRequest $correction)
    {
        $this->requirePermission($request, 'timeclock.resolve_corrections');
        $this->assertOrganization($request, $correction);
        $data = $request->validate(['resolution_comment' => ['required', 'string', 'max:3000']]);
        try {
            $this->timeclock->resolveCorrection($correction, $request->user(), false, $data['resolution_comment']);
        } catch (InvalidArgumentException $exception) {
            return to_route('timeclock.management.index')->with('status', $exception->getMessage());
        }

        return to_route('timeclock.management.index')->with('status', 'Corrección rechazada correctamente.');
    }

    private function assertOrganization(Request $request, TimeclockCorrectionRequest $correction): void
    {
        abort_unless($correction->organization_id === $this->organization($request)->id, 404);
    }
}
