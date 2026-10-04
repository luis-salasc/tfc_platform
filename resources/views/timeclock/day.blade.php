<x-layouts::app title="Detalle de fichaje">
    <div class="tfc-platform-page space-y-7">
        <div>
            <p class="tfc-kicker">Mi registro</p>
            <h1 class="tfc-title">{{ $summary['display_date'] }}</h1>
            <p class="tfc-subtitle">{{ $location->name }} · {{ $location->timezone }}</p>
        </div>
        <section class="tfc-card tfc-table-card">
            <div class="tfc-table-wrap"><table class="tfc-table"><thead><tr><th>Hora</th><th>Evento</th><th>Origen</th></tr></thead><tbody>
                @forelse($events as $event)
                    <tr><td>{{ $event->occurred_at->setTimezone($location->timezone)->format('H:i:s') }}</td><td>{{ $event->event_type->label() }}</td><td>{{ $event->source }}</td></tr>
                @empty
                    <tr><td class="tfc-empty" colspan="3">No hay eventos para este día.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
        <a class="tfc-text-button" href="{{ route('timeclock.index') }}">← Volver a Mi fichaje</a>
    </div>
</x-layouts::app>
