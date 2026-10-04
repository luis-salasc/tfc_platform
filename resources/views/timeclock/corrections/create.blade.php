<x-layouts::app title="Solicitar corrección">
    <div class="tfc-platform-page space-y-7"><div><p class="tfc-kicker">Registro horario</p><h1 class="tfc-title">Solicitar corrección</h1></div>
        <form method="POST" action="{{ route('timeclock.corrections.store') }}" class="tfc-card tfc-form space-y-4">@csrf
            <label>Fecha local<input type="date" name="local_date" value="{{ old('local_date', $date) }}" required></label>
            <label>Tipo<select name="correction_type" required><option value="add">Añadir fichaje</option><option value="correct">Corregir fichaje</option><option value="annul">Anular fichaje</option></select></label>
            <label>Fichaje original (para corregir o anular)<select name="original_event_id"><option value="">—</option>@foreach($events as $event)<option value="{{ $event->id }}">{{ $event->occurred_at->setTimezone($location->timezone)->format('d/m/Y H:i:s') }} · {{ $event->event_type->label() }}</option>@endforeach</select></label>
            <label>Evento propuesto<select name="proposed_event_type"><option value="">—</option>@foreach(\App\Enums\TimeclockEventType::cases() as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select></label>
            <label>Fecha y hora propuesta<input type="datetime-local" name="proposed_occurred_at" value="{{ old('proposed_occurred_at') }}"></label>
            <label>Motivo<textarea name="reason" rows="4" required>{{ old('reason') }}</textarea></label>
            @error('*')<p class="tfc-error">{{ $message }}</p>@enderror
            <button class="tfc-button">Enviar solicitud</button>
        </form>
    </div>
</x-layouts::app>
