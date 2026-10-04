<x-layouts::app title="Correcciones pendientes">
    <div class="tfc-platform-page space-y-7">
        <a class="tfc-text-button" href="{{ route('timeclock.management.index') }}">← Volver a Fichajes</a>

        <div>
            <p class="tfc-kicker">Fichajes</p>
            <h1 class="tfc-title">Correcciones pendientes</h1>
        </div>

        <section class="tfc-card tfc-table-card">
            <div class="tfc-table-wrap">
                <table class="tfc-table">
                    <thead><tr><th>Empleado</th><th>Fecha</th><th>Tipo</th><th>Motivo</th><th></th></tr></thead>
                    <tbody>
                        @forelse($corrections as $correction)
                            <tr>
                                <td>{{ $correction->employee->name }}</td>
                                <td>{{ $correction->local_date->format('d/m/Y') }}</td>
                                <td>{{ $correction->correction_type->label() }}</td>
                                <td>{{ $correction->reason }}</td>
                                <td><a class="tfc-text-button" href="{{ route('timeclock.management.correction', $correction) }}">Revisar</a></td>
                            </tr>
                        @empty
                            <tr><td class="tfc-empty" colspan="5">No hay correcciones pendientes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-5">{{ $corrections->links() }}</div>
        </section>
    </div>
</x-layouts::app>
