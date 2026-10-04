<?php

namespace App\Services;

use App\Enums\TimeclockEventType;
use App\Exceptions\TimeclockConfigurationException;
use App\Models\Location;
use App\Models\Organization;
use App\Models\TimeclockEvent;
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

        return $query->get();
    }

    /** @return Collection<int, TimeclockEvent> */
    private function eventsForDate(User $user, Organization $organization, Location $location, string $date): Collection
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
