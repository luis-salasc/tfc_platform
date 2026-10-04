<x-layouts::app :title="'Panel de '.$organization->name">
    @php
        $cards = [
            'asistencias' => ['kicker' => 'Actividad diaria', 'title' => 'Asistencias de hoy', 'value' => $stats['asistencias_hoy'], 'copy' => 'Registro diario de entradas y control de aforo.', 'action' => route('attendance.index', ['date' => now()->toDateString()]), 'label' => 'Abrir asistencia'],
            'activos' => ['kicker' => 'Base activa', 'title' => 'Miembros activos', 'value' => $stats['miembros_activos'], 'copy' => 'Miembros con estado activo y seguimiento vigente.', 'action' => route('miembros.index', ['status' => 'active']), 'label' => 'Ver miembros activos'],
            'nuevos' => ['kicker' => 'Altas del mes', 'title' => 'Nuevos miembros', 'value' => $stats['altas_mes'], 'copy' => 'Altas registradas durante el mes en curso.', 'action' => route('miembros.index', ['new_this_month' => 1]), 'label' => 'Ver altas del mes'],
            'ingresos' => ['kicker' => 'Cobros', 'title' => 'Ingresos del mes', 'value' => number_format((float) $stats['ingresos_mes'], 2, ',', '.').' €', 'copy' => 'Suma de cobros confirmados en el periodo actual.', 'action' => route('payments.index', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]), 'label' => 'Ver pagos'],
            'sesiones' => ['kicker' => 'Avisos', 'title' => 'Sesiones bajas', 'value' => $stats['sesiones_bajas'], 'copy' => 'Miembros activos con tres sesiones o menos.', 'action' => route('miembros.index', ['low_sessions' => 1, 'status' => 'active']), 'label' => 'Revisar miembros'],
            'incidencias' => ['kicker' => 'Seguimiento', 'title' => 'Incidencias abiertas', 'value' => $stats['incidencias_abiertas'], 'copy' => 'Entradas, pagos y situaciones que requieren seguimiento.', 'action' => route('miembros.index'), 'label' => 'Abrir fichas'],
        ];
    @endphp

    <div class="tfc-platform-page">
        <x-status-message />
        <div class="tfc-page-heading">
            <div>
                <p class="tfc-kicker">Operaciones · {{ now()->translatedFormat('l d F') }}</p>
                <h1 class="tfc-title">Dashboard</h1>
                <p class="tfc-subtitle">Actividad, membresías e ingresos de {{ $organization->name }} en un único lugar.</p>
            </div>
            <a class="tfc-button" href="{{ route('miembros.create') }}">Nuevo miembro →</a>
        </div>

        <div class="tfc-dashboard-cards mt-8" role="tablist">
            @foreach($cards as $key => $card)
                <button type="button" class="tfc-card tfc-stat tfc-dashboard-card {{ $loop->first ? 'is-active' : '' }}" data-dashboard-card="{{ $key }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                    <span class="tfc-stat__label">{{ $card['kicker'] }}</span>
                    <strong class="tfc-stat__value">{{ $card['value'] }}</strong>
                    <small>{{ $card['title'] }}</small>
                </button>
            @endforeach
        </div>

        @foreach($cards as $key => $card)
            <section class="tfc-card tfc-dashboard-detail {{ $loop->first ? '' : 'hidden' }}" data-dashboard-panel="{{ $key }}">
                <div>
                    <p class="tfc-kicker">{{ $card['kicker'] }}</p>
                    <h2 class="tfc-section-title">{{ $card['title'] }}</h2>
                    <p class="tfc-subtitle">{{ $card['copy'] }}</p>
                </div>
                <div class="tfc-detail-list">
                    @if($key === 'asistencias')
                        @forelse($todayAttendances as $attendance)
                            <div><strong>{{ $attendance->checked_in_at->format('H:i') }}</strong><span>{{ $attendance->member->first_name }} {{ $attendance->member->last_name }} · {{ $attendance->checkedInBy?->name ?? 'Kiosko' }}</span></div>
                        @empty
                            <div><span>No hay asistencias registradas hoy.</span></div>
                        @endforelse
                    @elseif($key === 'ingresos')
                        @forelse($monthPayments as $payment)
                            <div><strong>{{ number_format((float) $payment->amount, 2, ',', '.') }} €</strong><span>{{ $payment->member->first_name }} {{ $payment->member->last_name }} · {{ $payment->sessions_purchased }} sesiones</span></div>
                        @empty
                            <div><span>No hay pagos registrados este mes.</span></div>
                        @endforelse
                    @elseif($key === 'sesiones')
                        @forelse($lowSessions as $member)
                            <div><strong>{{ $member->sessions_remaining }} sesiones</strong><span>{{ $member->first_name }} {{ $member->last_name }}</span></div>
                        @empty
                            <div><span>No hay alertas de sesiones.</span></div>
                        @endforelse
                    @elseif($key === 'incidencias')
                        @forelse($openIncidents as $incident)
                            <div><strong>{{ $incident->occurred_at->format('d/m H:i') }}</strong><span>@if($incident->member)<a href="{{ route('miembros.show', $incident->member) }}">{{ $incident->member->first_name }} {{ $incident->member->last_name }}</a>@else Miembro eliminado @endif · {{ $incident->title }}</span></div>
                        @empty
                            <div><span>No hay incidencias abiertas.</span></div>
                        @endforelse
                    @else
                        <div><span>Utiliza el acceso para consultar el listado completo con los filtros ya aplicados.</span></div>
                    @endif
                </div>
                <a class="tfc-button tfc-button--small" href="{{ $card['action'] }}">{{ $card['label'] }} →</a>
            </section>
        @endforeach
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-dashboard-card]').forEach(button => button.addEventListener('click', () => {
                const key = button.dataset.dashboardCard;
                document.querySelectorAll('[data-dashboard-card]').forEach(item => {
                    item.classList.toggle('is-active', item === button);
                    item.setAttribute('aria-selected', item === button ? 'true' : 'false');
                });
                document.querySelectorAll('[data-dashboard-panel]').forEach(panel => panel.classList.toggle('hidden', panel.dataset.dashboardPanel !== key));
            }));
        });
    </script>
</x-layouts::app>
