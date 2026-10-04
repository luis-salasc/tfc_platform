<x-layouts::app title="Fichajes">
    <div class="tfc-platform-page space-y-7">
        <x-status-message />

        <div>
            <p class="tfc-kicker">Gestión de personal</p>
            <h1 class="tfc-title">Fichajes</h1>
        </div>

        <form method="GET" class="tfc-card tfc-form grid gap-4 md:grid-cols-4">
            <label>Empleado
                <select name="employee_user_id">
                    <option value="">Selecciona</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->user_id }}" @selected(request('employee_user_id') == $employee->user_id)>{{ $employee->user->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Desde<input type="date" name="date_from" value="{{ request('date_from') }}" required></label>
            <label>Hasta<input type="date" name="date_to" value="{{ request('date_to') }}" required></label>
            <button class="tfc-button self-end">Consultar</button>
        </form>

        @if($report->isNotEmpty())
            <p class="tfc-section-title">Total trabajado: {{ intdiv($totalWorked, 3600) }} h {{ intdiv($totalWorked % 3600, 60) }} min</p>
        @endif

        <section class="tfc-card tfc-table-card">
            <div class="tfc-table-wrap">
                <table class="tfc-table">
                    <thead><tr><th>Empleado</th><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Pausas</th><th>Trabajado</th><th>Estado</th><th>Corrección</th></tr></thead>
                    <tbody>
                        @forelse($report as $day)
                            <tr>
                                <td>{{ $day['employee'] }}</td><td>{{ $day['display_date'] }}</td><td>{{ $day['first_entry'] ?? '—' }}</td><td>{{ $day['last_exit'] ?? '—' }}</td>
                                <td>{{ intdiv($day['break_seconds'], 60) }} min</td><td>{{ intdiv($day['worked_seconds'], 3600) }} h {{ intdiv($day['worked_seconds'] % 3600, 60) }} min</td>
                                <td>{{ $day['status'] }}</td><td>{{ $day['has_corrections'] ? 'Sí' : 'No' }}</td>
                            </tr>
                        @empty
                            <tr><td class="tfc-empty" colspan="8">Selecciona un empleado y un rango.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="tfc-card tfc-table-card space-y-4">
            <div class="flex items-center justify-between gap-4">
                <h2 class="tfc-section-title">Correcciones pendientes</h2>
                <a class="tfc-text-button" href="{{ route('timeclock.management.corrections') }}">Ver todas</a>
            </div>
            <div class="tfc-table-wrap">
                <table class="tfc-table">
                    <thead><tr><th>Empleado</th><th>Fecha</th><th>Tipo</th><th>Motivo</th><th></th></tr></thead>
                    <tbody>
                        @forelse($pendingCorrections as $correction)
                            <tr>
                                <td>{{ $correction->employee->name }}</td>
                                <td>{{ $correction->local_date->format('d/m/Y') }}</td>
                                <td>{{ $correction->correction_type->label() }}</td>
                                <td>{{ $correction->reason }}</td>
                                <td><a class="tfc-text-button" href="{{ route('timeclock.management.correction', $correction) }}">Ver detalle</a></td>
                            </tr>
                        @empty
                            <tr><td class="tfc-empty" colspan="5">No hay correcciones pendientes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
