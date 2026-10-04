<x-layouts::app title="Mi fichaje">
    <div class="tfc-platform-page space-y-7">
        <x-status-message />
        @error('timeclock')<div class="tfc-error">{{ $message }}</div>@enderror
        <div>
            <p class="tfc-kicker">Registro horario</p>
            <h1 class="tfc-title">Mi fichaje</h1>
            <p class="tfc-subtitle">{{ $location->name }} · {{ $location->timezone }} · {{ now($location->timezone)->format('d/m/Y') }}</p>
        </div>
        <section class="tfc-card space-y-4">
            <p class="tfc-muted">Estado actual: <strong>{{ $state?->stateLabel() ?? 'Sin iniciar' }}</strong></p>
            <p class="tfc-section-title">Tiempo trabajado hoy: {{ intdiv($worked_seconds, 3600) }} h {{ intdiv($worked_seconds % 3600, 60) }} min</p>
            @if($timeTrackingRequired)
                <p class="tfc-muted">El fichaje horario está requerido para este usuario.</p>
            @endif
            @if($nextEvents)
                <div class="flex flex-wrap gap-3">
                    @foreach($nextEvents as $nextEvent)
                        <form method="POST" action="{{ route('timeclock.events.store') }}">
                            @csrf
                            <input type="hidden" name="event_type" value="{{ $nextEvent->value }}">
                            <button class="tfc-button">{{ $nextEvent->label() }}</button>
                        </form>
                    @endforeach
                </div>
            @else
                <p class="tfc-muted">La jornada de hoy está cerrada.</p>
            @endif
        </section>
        <section class="tfc-card tfc-table-card">
            <h2 class="tfc-section-title mb-3">Eventos de hoy</h2>
            <div class="tfc-table-wrap"><table class="tfc-table"><thead><tr><th>Hora</th><th>Evento</th><th>Origen</th></tr></thead><tbody>
                @forelse($events as $event)
                    <tr><td>{{ $event->occurred_at->setTimezone($location->timezone)->format('H:i:s') }}</td><td>{{ $event->event_type->label() }}</td><td>{{ $event->source }}</td></tr>
                @empty
                    <tr><td class="tfc-empty" colspan="3">No hay eventos registrados hoy.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
        <section class="tfc-card tfc-table-card">
            <h2 class="tfc-section-title mb-3">Mi registro</h2>
            <div class="tfc-table-wrap"><table class="tfc-table"><thead><tr><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Pausas</th><th>Trabajado</th><th>Estado</th><th></th></tr></thead><tbody>
                @forelse($history as $day)
                    <tr><td>{{ $day['display_date'] }}</td><td>{{ $day['first_entry'] ?? '—' }}</td><td>{{ $day['last_exit'] ?? '—' }}</td><td>{{ intdiv($day['break_seconds'], 60) }} min</td><td>{{ intdiv($day['worked_seconds'], 3600) }} h {{ intdiv($day['worked_seconds'] % 3600, 60) }} min</td><td>{{ $day['status'] }}</td><td><a class="tfc-text-button" href="{{ route('timeclock.day', $day['date']) }}">Detalle</a></td></tr>
                @empty
                    <tr><td class="tfc-empty" colspan="7">No hay días registrados todavía.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="mt-5">{{ $history->links() }}</div>
        </section>
        @if(request()->attributes->get('organizationMembership') && auth()->user()->canWithinOrganization('timeclock.request_correction', request()->attributes->get('organizationMembership')))<a class="tfc-text-button" href="{{ route('timeclock.corrections.index') }}">Mis solicitudes de corrección</a>@endif
    </div>
</x-layouts::app>
