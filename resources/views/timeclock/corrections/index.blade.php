<x-layouts::app title="Mis correcciones">
    <div class="tfc-platform-page space-y-7">
        <x-status-message />
        <div><p class="tfc-kicker">Registro horario</p><h1 class="tfc-title">Mis solicitudes de corrección</h1></div>
        <section class="tfc-card tfc-table-card"><div class="tfc-table-wrap"><table class="tfc-table"><thead><tr><th>Fecha</th><th>Tipo</th><th>Motivo</th><th>Estado</th><th></th></tr></thead><tbody>
            @forelse($corrections as $correction)
                <tr><td>{{ $correction->local_date->format('d/m/Y') }}</td><td>{{ $correction->correction_type->label() }}</td><td>{{ $correction->reason }}</td><td>{{ $correction->status->label() }}</td><td><a class="tfc-text-button" href="{{ route('timeclock.corrections.show', $correction) }}">Ver detalle</a></td></tr>
            @empty
                <tr><td class="tfc-empty" colspan="5">No has enviado solicitudes.</td></tr>
            @endforelse
        </tbody></table></div><div class="mt-5">{{ $corrections->links() }}</div></section>
        <a class="tfc-button" href="{{ route('timeclock.corrections.create') }}">Solicitar corrección</a>
    </div>
</x-layouts::app>
