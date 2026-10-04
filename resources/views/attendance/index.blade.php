<x-layouts::app title="Asistencias">
    <div class="tfc-platform-page space-y-7">
        <x-status-message />

        <div>
            <p class="tfc-kicker">Actividad del club</p>
            <h1 class="tfc-title">Asistencia</h1>
            <p class="tfc-subtitle">Check-in por QR, DNI o búsqueda manual, con control de duplicados diario.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <form method="POST" action="{{ route('attendance.store') }}" class="tfc-card tfc-form">
                @csrf
                <h2 class="tfc-section-title">Registro rápido</h2>
                <p class="tfc-muted">Escanea el QR USB o introduce el DNI / código de miembro.</p>
                <label>Código de check-in<input name="code" placeholder="QR, DNI o ID" required autofocus></label>
                <button class="tfc-button">Registrar entrada →</button>
            </form>

            <form method="POST" action="{{ route('attendance.store') }}" class="tfc-card tfc-form" data-attendance-member-search>
                @csrf
                <h2 class="tfc-section-title">Búsqueda manual</h2>
                <p class="tfc-muted">Busca por nombre, apellidos, documento o teléfono.</p>
                <label>Buscar miembro<input type="search" data-attendance-search-input placeholder="Escribe al menos 2 caracteres" autocomplete="off" maxlength="100"></label>
                <input type="hidden" name="code" data-attendance-member-code required>
                <p class="tfc-muted" data-attendance-search-state>Escribe al menos 2 caracteres para buscar.</p>
                <div class="space-y-2" data-attendance-search-results hidden></div>
                <div class="tfc-card" data-attendance-selected-member hidden></div>
                <button class="tfc-button" data-attendance-search-submit disabled>Registrar entrada →</button>
            </form>
        </div>

        @error('code')
            <div class="tfc-error">{{ $message }}</div>
        @enderror

        <section>
            <h2 class="tfc-section-title mb-3">Asistencias registradas</h2>
            <form class="tfc-filter-bar tfc-filter-card" method="GET" data-live-filter>
                <input type="date" name="date" value="{{ request('date') }}">
                <input name="q" value="{{ request('q') }}" placeholder="Miembro, DNI o teléfono">
                <input name="user_q" value="{{ request('user_q') }}" placeholder="Usuario que registró">
                <button class="tfc-button tfc-button--small">Filtrar</button>
                @if(request()->query())
                    <a class="tfc-reset-link" href="{{ route('attendance.index') }}">Limpiar</a>
                @endif
            </form>
            <div class="tfc-card tfc-table-card">
                <div class="tfc-table-wrap">
                    <table class="tfc-table">
                        <thead><tr><th>Fecha</th><th>Hora</th><th>Miembro</th><th>Registrado por</th></tr></thead>
                        <tbody>
                            @forelse($attendances as $attendance)
                                <tr>
                                    <td>{{ $attendance->checked_in_at->format('d/m/Y') }}</td>
                                    <td>{{ $attendance->checked_in_at->format('H:i') }}</td>
                                    <td><a href="{{ route('miembros.show', $attendance->member) }}">{{ $attendance->member->first_name }} {{ $attendance->member->last_name }}</a></td>
                                    <td>{{ $attendance->checkedInBy?->name ?? 'Kiosko' }}</td>
                                </tr>
                            @empty
                                <tr><td class="tfc-empty" colspan="4">No hay registros para los filtros indicados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-5">{{ $attendances->links() }}</div>
            </div>
        </section>
    </div>

    <script>
        (() => {
            const form = document.querySelector('[data-attendance-member-search]');
            if (!form) return;

            const input = form.querySelector('[data-attendance-search-input]');
            const code = form.querySelector('[data-attendance-member-code]');
            const state = form.querySelector('[data-attendance-search-state]');
            const results = form.querySelector('[data-attendance-search-results]');
            const selected = form.querySelector('[data-attendance-selected-member]');
            const submit = form.querySelector('[data-attendance-search-submit]');
            const endpoint = @json(route('attendance.members.search'));
            let timer;
            let requestId = 0;

            const resetSelection = () => {
                code.value = '';
                submit.disabled = true;
                selected.hidden = true;
                selected.replaceChildren();
            };

            const selectMember = (member) => {
                code.value = member.id;
                submit.disabled = false;
                selected.hidden = false;
                selected.replaceChildren();

                const summary = document.createElement('p');
                summary.textContent = `${member.name}${member.document ? ` · ${member.document}` : ''} · ${member.status} · ${member.available_sessions} sesiones disponibles`;
                selected.append(summary);

                if (member.available_sessions <= 0) {
                    const warning = document.createElement('p');
                    warning.className = 'tfc-error';
                    warning.textContent = 'No tiene sesiones disponibles. La entrada quedará pendiente de regularización.';
                    selected.append(warning);
                }

                results.hidden = true;
                results.replaceChildren();
                state.textContent = 'Miembro seleccionado.';
            };

            const renderResults = (members) => {
                results.replaceChildren();

                if (!members.length) {
                    results.hidden = true;
                    state.textContent = 'No se han encontrado miembros activos o registrados.';

                    return;
                }

                members.forEach((member) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'tfc-button tfc-button--small';
                    button.textContent = `${member.name}${member.document ? ` · ${member.document}` : ''}${member.phone ? ` · ${member.phone}` : ''}`;
                    button.addEventListener('click', () => selectMember(member));
                    results.append(button);
                });

                results.hidden = false;
                state.textContent = 'Selecciona un miembro.';
            };

            input.addEventListener('input', () => {
                clearTimeout(timer);
                resetSelection();
                results.hidden = true;
                results.replaceChildren();
                const query = input.value.trim();
                const currentRequest = ++requestId;

                if (query.length < 2) {
                    state.textContent = 'Escribe al menos 2 caracteres para buscar.';

                    return;
                }

                timer = setTimeout(async () => {
                    state.textContent = 'Buscando miembros…';

                    try {
                        const response = await fetch(`${endpoint}?q=${encodeURIComponent(query)}`, {headers: {Accept: 'application/json'}});
                        if (!response.ok) throw new Error('Search failed');
                        const payload = await response.json();
                        if (currentRequest !== requestId) return;
                        renderResults(payload.data);
                    } catch (error) {
                        if (currentRequest !== requestId) return;
                        state.textContent = 'No se pudo realizar la búsqueda. Inténtalo de nuevo.';
                    }
                }, 250);
            });
        })();
    </script>
</x-layouts::app>
