<x-layouts::app title="Revisar corrección">
    <div class="tfc-platform-page registration-wizard space-y-7" data-timeclock-resolution-container>
        <div class="registration-submit-overlay" data-timeclock-resolution-overlay hidden role="status" aria-live="assertive" tabindex="-1">
            <div>
                <span class="registration-submit-spinner" aria-hidden="true"></span>
                <strong>Procesando corrección...</strong>
                <p>Por favor, espera.</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-4">
            <a class="tfc-text-button" href="{{ route('timeclock.management.corrections') }}">← Volver a correcciones pendientes</a>
            <a class="tfc-text-button" href="{{ route('timeclock.management.index') }}">← Volver a Fichajes</a>
        </div>

        <div>
            <p class="tfc-kicker">Fichajes</p>
            <h1 class="tfc-title">Revisar corrección</h1>
        </div>

        <section class="tfc-card space-y-3">
            <p><strong>Empleado:</strong> {{ $correction->employee->name }}</p>
            <p><strong>Fecha:</strong> {{ $correction->local_date->format('d/m/Y') }}</p>
            <p><strong>Tipo:</strong> {{ $correction->correction_type->label() }}</p>
            <p><strong>Motivo:</strong> {{ $correction->reason }}</p>
            @if($correction->originalEvent)
                <p><strong>Original:</strong> {{ $correction->originalEvent->occurred_at->setTimezone($correction->location->timezone)->format('d/m/Y H:i:s') }} · {{ $correction->originalEvent->event_type->label() }}</p>
            @endif
            @if($correction->proposed_occurred_at)
                <p><strong>Propuesta:</strong> {{ $correction->proposed_occurred_at->setTimezone($correction->location->timezone)->format('d/m/Y H:i:s') }} · {{ $correction->proposed_event_type->label() }}</p>
            @endif
        </section>

        @if($correction->status->value === 'pending')
            <form method="POST" action="{{ route('timeclock.management.corrections.approve', $correction) }}" class="tfc-card tfc-form" data-timeclock-resolution-form>
                @csrf
                <label>Comentario<input name="resolution_comment"></label>
                <button class="tfc-button">Aprobar</button>
            </form>

            <form method="POST" action="{{ route('timeclock.management.corrections.reject', $correction) }}" class="tfc-card tfc-form" data-timeclock-resolution-form>
                @csrf
                <label>Motivo del rechazo<textarea name="resolution_comment" required></textarea></label>
                <button class="tfc-button">Rechazar</button>
            </form>
        @else
            <section class="tfc-card space-y-3">
                <p class="tfc-section-title">{{ $correction->status->label() }}</p>
                @if($correction->resolvedBy)
                    <p><strong>Resuelta por:</strong> {{ $correction->resolvedBy->name }}</p>
                @endif
                @if($correction->resolved_at)
                    <p><strong>Fecha de resolución:</strong> {{ $correction->resolved_at->setTimezone($correction->location->timezone)->format('d/m/Y · H:i') }}</p>
                @endif
                @if($correction->resolution_comment)
                    <p><strong>Comentario:</strong> {{ $correction->resolution_comment }}</p>
                @endif
            </section>
        @endif
    </div>

    @if($correction->status->value === 'pending')
        <script>
            (() => {
                const forms = document.querySelectorAll('[data-timeclock-resolution-form]');
                const container = document.querySelector('[data-timeclock-resolution-container]');
                const overlay = document.querySelector('[data-timeclock-resolution-overlay]');
                let submissionLocked = false;

                forms.forEach((form) => {
                    form.addEventListener('submit', (event) => {
                        if (submissionLocked) {
                            event.preventDefault();
                            return;
                        }

                        if (!form.checkValidity()) {
                            return;
                        }

                        event.preventDefault();
                        submissionLocked = true;
                        forms.forEach((actionForm) => actionForm.querySelectorAll('button').forEach((button) => button.disabled = true));
                        container.setAttribute('aria-busy', 'true');
                        overlay.hidden = false;
                        overlay.focus();

                        requestAnimationFrame(() => requestAnimationFrame(() => HTMLFormElement.prototype.submit.call(form)));
                    });
                });
            })();
        </script>
    @endif
</x-layouts::app>
