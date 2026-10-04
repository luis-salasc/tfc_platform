<?php

namespace App\Http\Controllers;

use App\Enums\TimeclockEventType;
use App\Exceptions\TimeclockConfigurationException;
use App\Http\Controllers\Concerns\InteractsWithOrganization;
use App\Services\TimeclockService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TimeclockController extends Controller
{
    use InteractsWithOrganization;

    public function __construct(private readonly TimeclockService $timeclock) {}

    public function index(Request $request)
    {
        $this->requirePermission($request, 'timeclock.view_own');
        try {
            $summary = $this->timeclock->today($request->user(), $this->organization($request));
        } catch (TimeclockConfigurationException $exception) {
            abort(409, $exception->getMessage());
        }

        return view('timeclock.index', [
            ...$summary,
            'history' => $this->timeclock->dailyHistory($request->user(), $this->organization($request), $summary['location']),
            'nextEvents' => $this->timeclock->allowedNextEvents($summary['state']),
            'timeTrackingRequired' => $this->membership($request)->time_tracking_required,
        ]);
    }

    public function showDay(Request $request, string $date)
    {
        $this->requirePermission($request, 'timeclock.view_own');
        $validated = validator(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->validate();
        $day = $this->timeclock->day($request->user(), $this->organization($request), $validated['date']);

        return view('timeclock.day', $day);
    }

    public function store(Request $request)
    {
        $this->requirePermission($request, 'timeclock.clock');
        $data = $request->validate(['event_type' => ['required', 'string']]);

        try {
            $event = $this->timeclock->record(
                $request->user(),
                $this->organization($request),
                TimeclockEventType::tryFrom($data['event_type']) ?? throw new InvalidArgumentException('Evento no válido.'),
            );
        } catch (TimeclockConfigurationException|InvalidArgumentException $exception) {
            return back()->withErrors(['timeclock' => $exception->getMessage()]);
        }

        return to_route('timeclock.index')->with('status', "{$event->event_type->label()} registrada.");
    }
}
