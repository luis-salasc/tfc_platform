<?php

namespace App\Services;

use App\Enums\TimeclockEventType;
use App\Enums\TimeclockCorrectionStatus;
use App\Enums\TimeclockCorrectionType;
use App\Exceptions\TimeclockConfigurationException;
use App\Models\Location;
use App\Models\Organization;
use App\Models\TimeclockEvent;
use App\Models\TimeclockCorrectionRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TimeclockService
{
    /** @return array{location: Location, events: Collection<int, TimeclockEvent>, state: ?TimeclockEventType, worked_seconds: int} */
    public function today(User $user, Organization $organization): array
    {
        $location = $this->activeLocation($organization);
        $now = CarbonImmutable::now($location->timezone);
        $events = $this->eventsForDay($user, $organization, $now, lock: false);

        return [
            'location' => $location,
            'events' => $events,
            'state' => $this->state($events),
            'worked_seconds' => $this->workedSeconds($events, $now),
        ];
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function dailyHistory(User $user, Organization $organization, Location $location, int $perPage = 15): LengthAwarePaginator
    {
        $range = TimeclockEvent::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->selectRaw('MIN(occurred_at) AS first_occurred_at, MAX(occurred_at) AS last_occurred_at')
            ->first();

        if (! $range?->first_occurred_at) {
            return new Paginator([], 0, $perPage, Paginator::resolveCurrentPage('days'), [
                'path' => request()->url(),
                'pageName' => 'days',
            ]);
        }

        $firstDate = CarbonImmutable::parse($range->first_occurred_at, 'UTC')->setTimezone($location->timezone)->startOfDay();
        $date = CarbonImmutable::parse($range->last_occurred_at, 'UTC')->setTimezone($location->timezone)->startOfDay();
        $page = Paginator::resolveCurrentPage('days');
        $firstItem = ($page - 1) * $perPage;
        $activeDays = 0;
        $selectedDates = [];

        for (; $date->greaterThanOrEqualTo($firstDate); $date = $date->subDay()) {
            $dateString = $date->toDateString();
            if (! $this->hasEventsForDate($user, $organization, $location, $dateString)) {
                continue;
            }

            if ($activeDays >= $firstItem && count($selectedDates) < $perPage) {
                $selectedDates[] = $dateString;
            }
            $activeDays++;
        }

        $summaries = collect($selectedDates)->map(function (string $date) use ($user, $organization, $location): array {
            $events = $this->eventsForDate($user, $organization, $location, $date);

            return $this->summarize($events, $location, $date);
        });

        return new Paginator($summaries, $activeDays, $perPage, $page, [
            'path' => request()->url(),
            'pageName' => 'days',
        ]);
    }

    /** @return array{location: Location, events: Collection<int, TimeclockEvent>, summary: array<string, mixed>} */
    public function day(User $user, Organization $organization, string $date): array
    {
        $location = $this->activeLocation($organization);
        $events = $this->eventsForDate($user, $organization, $location, $date);

        return ['location' => $location, 'events' => $events, 'summary' => $this->summarize($events, $location, $date)];
    }

    public function createCorrectionRequest(User $employee, Organization $organization, Location $location, array $data): TimeclockCorrectionRequest
    {
        $type = TimeclockCorrectionType::from($data['correction_type']);
        $original = null;
        if ($type !== TimeclockCorrectionType::Add) {
            $original = TimeclockEvent::query()
                ->where('id', $data['original_event_id'])
                ->where('organization_id', $organization->id)
                ->where('user_id', $employee->id)
                ->firstOrFail();
            abort_unless($this->eventBelongsToDate($original, $location, $data['local_date']), 422);
        }

        $proposedAt = null;
        if ($type !== TimeclockCorrectionType::Annul) {
            $localProposedAt = CarbonImmutable::parse($data['proposed_occurred_at'], $location->timezone);
            abort_unless($localProposedAt->toDateString() === $data['local_date'], 422);
            $proposedAt = $localProposedAt->utc();
        }

        return TimeclockCorrectionRequest::create([
            'organization_id' => $organization->id,
            'location_id' => $location->id,
            'employee_user_id' => $employee->id,
            'requested_by_user_id' => $employee->id,
            'local_date' => $data['local_date'],
            'correction_type' => $type,
            'original_event_id' => $original?->id,
            'proposed_event_type' => $data['proposed_event_type'] ?? null,
            'proposed_occurred_at' => $proposedAt,
            'reason' => $data['reason'],
            'status' => TimeclockCorrectionStatus::Pending,
        ]);
    }

    public function resolveCorrection(TimeclockCorrectionRequest $correction, User $resolver, bool $approve, ?string $comment): TimeclockCorrectionRequest
    {
        return DB::transaction(function () use ($correction, $resolver, $approve, $comment): TimeclockCorrectionRequest {
            $correction = TimeclockCorrectionRequest::query()->whereKey($correction->id)->lockForUpdate()->firstOrFail();
            if ($correction->status !== TimeclockCorrectionStatus::Pending) {
                throw new InvalidArgumentException('Esta solicitud ya fue resuelta.');
            }

            if (! $approve) {
                $correction->update(['status' => TimeclockCorrectionStatus::Rejected, 'resolved_by_user_id' => $resolver->id, 'resolved_at' => now('UTC'), 'resolution_comment' => $comment]);

                return $correction->refresh();
            }

            if ($correction->original_event_id && TimeclockCorrectionRequest::query()
                ->where('original_event_id', $correction->original_event_id)
                ->where('status', TimeclockCorrectionStatus::Approved)
                ->exists()) {
                abort(422, 'El evento ya tiene una corrección aprobada.');
            }

            $location = $correction->location()->firstOrFail();
            $organization = $correction->organization()->firstOrFail();
            $originals = $this->originalEventsForDate($correction->employee, $organization, $location, $correction->local_date->toDateString());
            $approved = TimeclockCorrectionRequest::query()
                ->where('organization_id', $correction->organization_id)
                ->where('employee_user_id', $correction->employee_user_id)
                ->where('local_date', $correction->local_date)
                ->where('status', TimeclockCorrectionStatus::Approved)
                ->get()
                ->push($correction);
            abort_unless($this->validSequence($this->applyCorrections($originals, $approved)), 422, 'La corrección produciría una secuencia de fichaje inválida.');

            $correction->update(['status' => TimeclockCorrectionStatus::Approved, 'resolved_by_user_id' => $resolver->id, 'resolved_at' => now('UTC'), 'resolution_comment' => $comment]);
            if ($correction->original_event_id) {
                TimeclockCorrectionRequest::query()
                    ->where('original_event_id', $correction->original_event_id)
                    ->where('status', TimeclockCorrectionStatus::Pending)
                    ->where('id', '<>', $correction->id)
                    ->update(['status' => TimeclockCorrectionStatus::Rejected, 'resolved_by_user_id' => $resolver->id, 'resolved_at' => now('UTC'), 'resolution_comment' => 'Solicitud incompatible con otra corrección aprobada.']);
            }

            return $correction->refresh();
        });
    }

    /** @return Collection<int, TimeclockEvent> */
    public function effectiveEventsForDate(User $user, Organization $organization, Location $location, string $date): Collection
    {
        $originals = $this->originalEventsForDate($user, $organization, $location, $date);
        $corrections = TimeclockCorrectionRequest::query()
            ->where('organization_id', $organization->id)
            ->where('employee_user_id', $user->id)
            ->where('local_date', $date)
            ->where('status', TimeclockCorrectionStatus::Approved)
            ->get();

        return $this->applyCorrections($originals, $corrections);
    }

    /** @return Collection<int, TimeclockEvent> */
    public function eventsForCorrectionForm(User $user, Organization $organization, Location $location): Collection
    {
        return $this->originalEventsForDate($user, $organization, $location, CarbonImmutable::now($location->timezone)->toDateString());
    }

    /** @return Collection<int, TimeclockEvent> */
    public function eventsForCorrectionDate(User $user, Organization $organization, Location $location, string $date): Collection
    {
        return $this->originalEventsForDate($user, $organization, $location, $date);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function rangeReport(User $user, Organization $organization, Location $location, string $from, string $to): Collection
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $to, $location->timezone);
        $first = CarbonImmutable::createFromFormat('!Y-m-d', $from, $location->timezone);
        $rows = collect();
        for (; $date->greaterThanOrEqualTo($first); $date = $date->subDay()) {
            $dateString = $date->toDateString();
            $events = $this->effectiveEventsForDate($user, $organization, $location, $dateString);
            if ($events->isNotEmpty()) {
                $rows->push($this->summarize($events, $location, $dateString) + ['employee' => $user->name, 'has_corrections' => $events->contains(fn (TimeclockEvent $event): bool => $event->source === 'correction')]);
            }
        }

        return $rows;
    }

    public function record(User $user, Organization $organization, TimeclockEventType $requestedType): TimeclockEvent
    {
        $location = $this->activeLocation($organization);

        return DB::transaction(function () use ($user, $organization, $requestedType, $location): TimeclockEvent {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $events = $this->eventsForDay($user, $organization, CarbonImmutable::now($location->timezone), lock: true);
            if (! in_array($requestedType, $this->allowedNextEvents($this->state($events)), true)) {
                throw new InvalidArgumentException('La secuencia de fichaje no es válida.');
            }

            return TimeclockEvent::create([
                'organization_id' => $organization->id,
                'location_id' => $location->id,
                'user_id' => $user->id,
                'event_type' => $requestedType,
                'occurred_at' => CarbonImmutable::now('UTC'),
                'source' => 'web',
            ]);
        });
    }

    public function activeLocation(Organization $organization): Location
    {
        $locations = $organization->locations()->where('is_active', true)->get();
        if ($locations->count() !== 1) {
            throw new TimeclockConfigurationException('El fichaje requiere exactamente una sede activa configurada.');
        }

        return $locations->sole();
    }

    /** @return Collection<int, TimeclockEvent> */
    private function eventsForDay(User $user, Organization $organization, CarbonImmutable $localNow, bool $lock = false): Collection
    {
        [$start, $end] = $this->utcBoundsForDate($localNow->toDateString(), $localNow->getTimezone()->getName());
        $query = TimeclockEvent::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->whereBetween('occurred_at', [$start, $end])
            ->orderBy('occurred_at')
            ->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        $originals = $query->get();
        $corrections = TimeclockCorrectionRequest::query()
            ->where('organization_id', $organization->id)
            ->where('employee_user_id', $user->id)
            ->where('local_date', $localNow->toDateString())
            ->where('status', TimeclockCorrectionStatus::Approved)
            ->get();

        return $this->applyCorrections($originals, $corrections);
    }

    /** @return Collection<int, TimeclockEvent> */
    private function eventsForDate(User $user, Organization $organization, Location $location, string $date): Collection
    {
        $originals = $this->originalEventsForDate($user, $organization, $location, $date);
        $corrections = TimeclockCorrectionRequest::query()
            ->where('organization_id', $organization->id)
            ->where('employee_user_id', $user->id)
            ->where('local_date', $date)
            ->where('status', TimeclockCorrectionStatus::Approved)
            ->get();

        return $this->applyCorrections($originals, $corrections);
    }

    /** @return Collection<int, TimeclockEvent> */
    private function originalEventsForDate(User $user, Organization $organization, Location $location, string $date): Collection
    {
        [$start, $end] = $this->utcBoundsForDate($date, $location->timezone);

        return TimeclockEvent::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, TimeclockEvent> */
    private function applyCorrections(Collection $originals, Collection $corrections): Collection
    {
        $events = $originals->keyBy('id');
        foreach ($corrections as $correction) {
            if ($correction->original_event_id) {
                $events->forget($correction->original_event_id);
            }
        }

        foreach ($corrections as $correction) {
            if ($correction->correction_type === TimeclockCorrectionType::Annul) {
                continue;
            }
            $virtual = new TimeclockEvent([
                'id' => -$correction->id,
                'organization_id' => $correction->organization_id,
                'location_id' => $correction->location_id,
                'user_id' => $correction->employee_user_id,
                'event_type' => $correction->proposed_event_type,
                'occurred_at' => $correction->proposed_occurred_at,
                'source' => 'correction',
            ]);
            $events->put($virtual->id, $virtual);
        }

        return $events->sortBy(fn (TimeclockEvent $event) => [$event->occurred_at->getTimestamp(), $event->id])->values();
    }

    private function validSequence(Collection $events): bool
    {
        $state = null;
        foreach ($events as $event) {
            if (! in_array($event->event_type, $this->allowedNextEvents($state), true)) {
                return false;
            }
            $state = $event->event_type;
        }

        return true;
    }

    private function eventBelongsToDate(TimeclockEvent $event, Location $location, string $date): bool
    {
        [$start, $end] = $this->utcBoundsForDate($date, $location->timezone);

        return $event->occurred_at->greaterThanOrEqualTo($start) && $event->occurred_at->lessThan($end);
    }

    private function hasEventsForDate(User $user, Organization $organization, Location $location, string $date): bool
    {
        [$start, $end] = $this->utcBoundsForDate($date, $location->timezone);

        return TimeclockEvent::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->exists();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function utcBoundsForDate(string $date, string $timezone): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone)->startOfDay();

        return [$start->utc(), $start->addDay()->utc()];
    }

    private function state(Collection $events): ?TimeclockEventType
    {
        return $events->last()?->event_type;
    }

    /** @return array{date: string, display_date: string, first_entry: ?string, last_exit: ?string, break_seconds: int, worked_seconds: int, status: string} */
    private function summarize(Collection $events, Location $location, string $date): array
    {
        $worked = 0;
        $breakSeconds = 0;
        $workStartedAt = null;
        $breakStartedAt = null;
        $firstEntry = null;
        $lastExit = null;
        $state = null;
        $valid = true;

        foreach ($events as $event) {
            $occurredAt = CarbonImmutable::parse($event->occurred_at)->utc();
            match ($event->event_type) {
                TimeclockEventType::ClockIn => $this->summarizeClockIn($occurredAt, $state, $workStartedAt, $firstEntry, $valid),
                TimeclockEventType::BreakStart => $this->summarizeBreakStart($occurredAt, $state, $workStartedAt, $breakStartedAt, $worked, $valid),
                TimeclockEventType::BreakEnd => $this->summarizeBreakEnd($occurredAt, $state, $workStartedAt, $breakStartedAt, $breakSeconds, $valid),
                TimeclockEventType::ClockOut => $this->summarizeClockOut($occurredAt, $state, $workStartedAt, $lastExit, $worked, $valid),
            };
        }

        $localToday = CarbonImmutable::now($location->timezone)->toDateString() === $date;
        $open = $state !== null && $state !== TimeclockEventType::ClockOut;
        if ($open && $localToday && $workStartedAt) {
            $worked += $workStartedAt->diffInSeconds(CarbonImmutable::now('UTC'));
        }

        return [
            'date' => $date,
            'display_date' => CarbonImmutable::createFromFormat('!Y-m-d', $date, $location->timezone)->format('d/m/Y'),
            'first_entry' => $firstEntry?->setTimezone($location->timezone)->format('H:i'),
            'last_exit' => $lastExit?->setTimezone($location->timezone)->format('H:i'),
            'break_seconds' => (int) $breakSeconds,
            'worked_seconds' => (int) $worked,
            'status' => ! $valid ? 'Incompleta' : ($open ? ($localToday ? 'En curso' : 'Incompleta') : 'Completa'),
        ];
    }

    private function summarizeClockIn(CarbonImmutable $occurredAt, ?TimeclockEventType &$state, ?CarbonImmutable &$workStartedAt, ?CarbonImmutable &$firstEntry, bool &$valid): void
    {
        if ($state !== null && $state !== TimeclockEventType::ClockOut) {
            $valid = false;

            return;
        }
        $firstEntry ??= $occurredAt;
        $workStartedAt = $occurredAt;
        $state = TimeclockEventType::ClockIn;
    }

    private function summarizeBreakStart(CarbonImmutable $occurredAt, ?TimeclockEventType &$state, ?CarbonImmutable &$workStartedAt, ?CarbonImmutable &$breakStartedAt, int &$worked, bool &$valid): void
    {
        if (! in_array($state, [TimeclockEventType::ClockIn, TimeclockEventType::BreakEnd], true) || ! $workStartedAt) {
            $valid = false;

            return;
        }
        $worked += $workStartedAt->diffInSeconds($occurredAt);
        $workStartedAt = null;
        $breakStartedAt = $occurredAt;
        $state = TimeclockEventType::BreakStart;
    }

    private function summarizeBreakEnd(CarbonImmutable $occurredAt, ?TimeclockEventType &$state, ?CarbonImmutable &$workStartedAt, ?CarbonImmutable &$breakStartedAt, int &$breakSeconds, bool &$valid): void
    {
        if ($state !== TimeclockEventType::BreakStart || ! $breakStartedAt) {
            $valid = false;

            return;
        }
        $breakSeconds += $breakStartedAt->diffInSeconds($occurredAt);
        $breakStartedAt = null;
        $workStartedAt = $occurredAt;
        $state = TimeclockEventType::BreakEnd;
    }

    private function summarizeClockOut(CarbonImmutable $occurredAt, ?TimeclockEventType &$state, ?CarbonImmutable &$workStartedAt, ?CarbonImmutable &$lastExit, int &$worked, bool &$valid): void
    {
        if (! in_array($state, [TimeclockEventType::ClockIn, TimeclockEventType::BreakEnd], true) || ! $workStartedAt) {
            $valid = false;

            return;
        }
        $worked += $workStartedAt->diffInSeconds($occurredAt);
        $workStartedAt = null;
        $lastExit = $occurredAt;
        $state = TimeclockEventType::ClockOut;
    }

    /** @return array<int, TimeclockEventType> */
    public function allowedNextEvents(?TimeclockEventType $state): array
    {
        return match ($state) {
            null, TimeclockEventType::ClockOut => [TimeclockEventType::ClockIn],
            TimeclockEventType::ClockIn, TimeclockEventType::BreakEnd => [TimeclockEventType::BreakStart, TimeclockEventType::ClockOut],
            TimeclockEventType::BreakStart => [TimeclockEventType::BreakEnd],
        };
    }

    private function workedSeconds(Collection $events, CarbonImmutable $now): int
    {
        $worked = 0;
        $workStartedAt = null;
        foreach ($events as $event) {
            $occurredAt = CarbonImmutable::parse($event->occurred_at)->utc();
            match ($event->event_type) {
                TimeclockEventType::ClockIn, TimeclockEventType::BreakEnd => $workStartedAt = $occurredAt,
                TimeclockEventType::BreakStart, TimeclockEventType::ClockOut => (function () use (&$worked, &$workStartedAt, $occurredAt): void {
                    if ($workStartedAt) {
                        $worked += $workStartedAt->diffInSeconds($occurredAt);
                        $workStartedAt = null;
                    }
                })(),
            };
        }
        if ($workStartedAt) {
            $worked += $workStartedAt->diffInSeconds($now->utc());
        }

        return $worked;
    }
}
