<x-layouts::app :title="$member->first_name.' '.$member->last_name">
@php
    $intake = $member->intake ?? []; $metrics = $member->initial_metrics ?? [];
    $field = fn (...$keys) => collect($keys)->map(fn ($key) => data_get($intake, $key) ?? data_get($metrics, $key))->first(fn ($value) => $value !== null && $value !== '');
    $yesNo = fn ($value) => $value === null || $value === '' ? '—' : ($value === true || $value === 1 || $value === '1' || mb_strtolower((string) $value) === 'si' || mb_strtolower((string) $value) === 'sí' ? 'Sí' : 'No');
    $objectives = $field('objectives', 'objetivos'); if (is_string($objectives)) { $objectives = json_decode($objectives, true) ?: [$objectives]; } $objectives = (array) $objectives;
    $tabs = ['resumen' => 'Resumen', 'entrenamiento' => 'Entrenamiento', 'seguimiento' => 'Seguimiento', 'asistencias' => 'Asistencias', 'gestion' => 'Gestión', 'ficha' => 'Ficha y documentos'];
@endphp
<div class="tfc-platform-page"><x-status-message />
    <div class="tfc-page-heading"><div><p class="tfc-kicker">Ficha completa de miembro</p><h1 class="tfc-title">{{ $member->first_name }} {{ $member->last_name }}</h1><p class="tfc-subtitle"><span class="tfc-status tfc-status--{{ $member->status->value }}">{{ $member->status->label() }}</span> · {{ $sessionSummary['available_sessions'] }} sesiones disponibles</p><div class="tfc-member-contact-summary" aria-label="Contacto del miembro"><span>{{ $member->email ?: 'Sin correo electrónico' }}</span><span>{{ $member->phone ?: 'Sin teléfono' }}</span>@if($member->city)<span>{{ $member->city }}</span>@endif</div></div>@if($canManage)<a class="tfc-button" href="{{ route('miembros.edit', $member) }}">Editar ficha →</a>@endif</div>
    <section class="tfc-member-context mt-7" aria-label="Estado operativo del miembro">
        <article><span>Sesiones disponibles</span><strong>{{ $sessionSummary['available_sessions'] }}</strong></article>
        <article @class(['has-alert' => $sessionSummary['pending_sessions'] > 0])><span>Sesiones pendientes</span><strong>{{ $sessionSummary['pending_sessions'] }}</strong></article>
        <article><span>Última asistencia</span><strong>{{ $member->attendances->first()?->checked_in_at?->format('d/m/Y') ?? 'Sin registros' }}</strong></article>
        <article><span>Plan actual</span><strong>{{ $member->trainingPlans->firstWhere('status', 'active')?->title ?? 'Sin asignar' }}</strong></article>
        <article @class(['has-alert' => $member->incidents->where('status', 'open')->isNotEmpty()])><span>Incidencias abiertas</span><strong>{{ $member->incidents->where('status', 'open')->count() }}</strong></article>
        <div class="tfc-member-context-actions">@if($canManage)<button type="button" class="tfc-text-button" data-member-quick-tab="progresos">Registrar progreso</button><button type="button" class="tfc-text-button" data-member-quick-tab="pagos">Registrar pago</button>@endif</div>
    </section>
    <section class="tfc-card tfc-member-content mt-5"><nav class="tfc-member-tabs" role="tablist">@foreach($tabs as $key => $label)<button type="button" data-member-tab="{{ $key }}" @class(['is-active' => $loop->first])>{{ $label }}</button>@endforeach</nav>
            <div class="tfc-member-panels">
                <section data-member-panel="resumen"><div class="tfc-member-grid"><article><h2>Objetivos</h2>@forelse($objectives as $objective)<p>{{ is_array($objective) ? implode(', ', $objective) : $objective }}</p>@empty<p>Sin objetivos registrados.</p>@endforelse@if($field('other_objective','objetivos_otros_detalle'))<p>Otro: {{ $field('other_objective','objetivos_otros_detalle') }}</p>@endif</article><article><h2>Resumen físico inicial</h2><dl class="tfc-data-list"><div><dt>Peso</dt><dd>{{ $field('weight_kg','peso_inicial_kg') ?: '—' }} kg</dd></div><div><dt>Grasa</dt><dd>{{ $field('body_fat_percentage','porcentaje_grasa') ?: '—' }} %</dd></div><div><dt>Masa muscular</dt><dd>{{ $field('muscle_mass_kg','masa_muscular_kg') ?: '—' }} kg</dd></div></dl></article></div></section>
                <section data-member-panel="progresos" class="hidden"><div class="tfc-member-grid"><article><h2>Registrar progreso</h2>@if($canManage)<form method="POST" action="{{ route('members.progress.store', $member) }}" class="tfc-form">@csrf<label>Fecha<input type="date" name="recorded_on" value="{{ now()->format('Y-m-d') }}" required></label><div class="tfc-form-grid">@foreach(['weight_kg'=>'Peso (kg)','body_fat_percentage'=>'Grasa (%)','muscle_mass_kg'=>'Músculo (kg)','waist_cm'=>'Cintura (cm)','hip_cm'=>'Cadera (cm)','chest_cm'=>'Pecho (cm)','arm_cm'=>'Brazo (cm)','leg_cm'=>'Pierna (cm)','water_percentage'=>'Agua (%)','visceral_fat'=>'Grasa visceral','metabolic_age'=>'Edad metabólica'] as $name => $label)<label>{{ $label }}<input type="number" step="0.1" name="{{ $name }}"></label>@endforeach</div><label>Comentarios<textarea name="notes"></textarea></label><button class="tfc-button">Guardar progreso →</button></form>@else<p>Solo personal autorizado puede registrar progreso.</p>@endif</article><article><h2>Último registro</h2>@if($entry = $member->progressEntries->first())<dl class="tfc-data-list">@foreach($entry->metrics ?? [] as $label => $value)<div><dt>{{ str_replace('_', ' ', $label) }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>@else<p>Sin progresos registrados.</p>@endif</article></div></section>
                <section data-member-panel="historial" class="hidden"><h2>Historial de progresos</h2><div class="tfc-table-wrap"><table class="tfc-table"><thead><tr><th>Fecha</th><th>Peso</th><th>Grasa</th><th>Músculo</th><th>Comentarios</th></tr></thead><tbody>@forelse($member->progressEntries as $entry)<tr><td>{{ $entry->recorded_on->format('d/m/Y') }}</td><td>{{ data_get($entry->metrics,'weight_kg','—') }} kg</td><td>{{ data_get($entry->metrics,'body_fat_percentage','—') }} %</td><td>{{ data_get($entry->metrics,'muscle_mass_kg','—') }} kg</td><td>{{ $entry->notes ?: '—' }}</td></tr>@empty<tr><td colspan="5" class="tfc-empty">No hay registros de progreso.</td></tr>@endforelse</tbody></table></div></section>
                <section data-member-panel="asistencias" class="hidden"><h2>Historial de asistencias</h2><div class="tfc-table-wrap"><table class="tfc-table"><thead><tr><th>Fecha</th><th>Hora</th><th>Registrado por</th></tr></thead><tbody>@forelse($member->attendances as $attendance)<tr><td>{{ $attendance->checked_in_at->format('d/m/Y') }}</td><td>{{ $attendance->checked_in_at->format('H:i') }}</td><td>{{ $attendance->checkedInBy?->name ?? 'Kiosco' }}</td></tr>@empty<tr><td colspan="3" class="tfc-empty">Este miembro no tiene asistencias.</td></tr>@endforelse</tbody></table></div></section>
                <section data-member-panel="pagos" class="hidden"><div class="tfc-member-grid"><article><h2>Registrar pago</h2>@if($canManage)<form method="POST" action="{{ route('members.payments.store', $member) }}" class="tfc-form">@csrf<label>Sesiones compradas<input type="number" min="1" name="sessions_purchased" required></label><label>Importe (€)<input type="number" min="0" step="0.01" name="amount" required></label><label>Forma de pago<select name="payment_method" required><option value="cash">Efectivo</option><option value="card">Tarjeta</option><option value="transfer">Transferencia</option><option value="bizum">Bizum</option><option value="other">Otro</option></select></label><label>Referencia / detalle<input name="payment_method_detail" placeholder="Opcional"></label><fieldset class="tfc-delivery-options"><legend>Entregar justificante</legend><label><input type="checkbox" name="delivery_methods[]" value="email"> Enviar por correo</label><label><input type="checkbox" name="delivery_methods[]" value="whatsapp"> Preparar WhatsApp</label><label><input type="checkbox" name="delivery_methods[]" value="print"> Imprimir</label></fieldset><label>Comentarios<textarea name="notes"></textarea></label><button class="tfc-button">Añadir pago y sesiones →</button></form>@endif</article><article><h2>Historial de pagos</h2><div class="tfc-table-wrap"><table class="tfc-table"><thead><tr><th>Fecha</th><th>Forma</th><th>Sesiones</th><th>Importe</th><th>Usuario</th></tr></thead><tbody>@forelse($member->payments as $payment)<tr><td>{{ $payment->paid_at->format('d/m/Y H:i') }}</td><td>{{ ucfirst($payment->payment_method) }}</td><td>+{{ $payment->sessions_purchased }}</td><td>{{ number_format((float)$payment->amount,2,',','.') }} €</td><td>{{ $payment->receivedBy?->name ?? '—' }}</td></tr>@empty<tr><td colspan="5" class="tfc-empty">No hay pagos.</td></tr>@endforelse</tbody></table></div></article></div></section>
                <section data-member-panel="kyc" class="hidden"><div class="tfc-member-grid"><article><h2>Objetivos y captación</h2><dl class="tfc-data-list"><div><dt>Nos conoció por</dt><dd>{{ $field('discovery_channel','como_nos_conociste') ?: '—' }}</dd></div><div><dt>Detalle</dt><dd>{{ $field('discovery_detail') ?: '—' }}</dd></div><div><dt>Fecha objetivo</dt><dd>{{ $field('goal_date','fecha_objetivo') ?: '—' }}</dd></div></dl></article><article><h2>Hábitos de ejercicio</h2><dl class="tfc-data-list"><div><dt>Ejercicio regular</dt><dd>{{ $yesNo($field('current_exercise','ejercicio_regular')) }}</dd></div><div><dt>Tipo actual</dt><dd>{{ $field('current_exercise_type','ejercicio_actual_tipo') ?: '—' }}</dd></div><div><dt>Frecuencia</dt><dd>{{ $field('current_exercise_frequency','ejercicio_actual_frecuencia') ?: '—' }} veces/semana</dd></div><div><dt>Resultados</dt><dd>{{ $yesNo($field('current_exercise_results','ejercicio_actual_resultados')) }}</dd></div></dl></article></div></section>
                <section data-member-panel="salud" class="hidden"><h2>Cuestionario de salud</h2><p class="tfc-member-note">{{ $field('medical_notes','lesiones_descripcion') ?: 'Sin lesiones o notas médicas registradas.' }}</p><div class="tfc-member-grid mt-4"><article><dl class="tfc-data-list">@foreach(['pregnancy'=>'Embarazo','caaf.heart_condition'=>'Afección cardíaca','caaf.chest_activity'=>'Dolor pecho actividad','caaf.chest_rest'=>'Dolor pecho reposo'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ $yesNo($field($key, str_replace('caaf.','caaf_q', $key))) }}</dd></div>@endforeach</dl></article><article><dl class="tfc-data-list">@foreach(['caaf.dizziness'=>'Mareos','caaf.bones_joints'=>'Huesos / articulaciones','caaf.medication'=>'Medicación presión / corazón','caaf.other_reason'=>'Otra razón médica'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ $yesNo($field($key, str_replace('caaf.','caaf_q', $key))) }}</dd></div>@endforeach</dl></article></div></section>
                <section data-member-panel="medidas" class="hidden"><div class="tfc-member-grid"><article><h2>Composición corporal inicial</h2><dl class="tfc-data-list">@foreach(['weight_kg'=>'Peso (kg)','height_cm'=>'Altura (cm)','body_fat_percentage'=>'Grasa corporal (%)','muscle_mass_kg'=>'Masa muscular (kg)'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ $field($key, match($key){'weight_kg'=>'peso_inicial_kg','height_cm'=>'altura_cm','body_fat_percentage'=>'porcentaje_grasa','muscle_mass_kg'=>'masa_muscular_kg'}) ?: '—' }}</dd></div>@endforeach</dl></article><article><h2>Circunferencias y bioimpedancia</h2><dl class="tfc-data-list">@foreach(['waist_cm'=>'Cintura','hip_cm'=>'Cadera','chest_cm'=>'Pecho','arm_cm'=>'Brazo','leg_cm'=>'Pierna','water_percentage'=>'Agua corporal','visceral_fat'=>'Grasa visceral','metabolic_age'=>'Edad metabólica'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ $field($key) ?: '—' }}</dd></div>@endforeach</dl></article></div></section>
                <section data-member-panel="fotos" class="hidden"><h2>Fotos iniciales</h2><div class="tfc-photo-grid">@foreach(['photo_front'=>'Frontal','photo_side'=>'Perfil','photo_back'=>'Espalda'] as $key=>$label)<article><h3>{{ $label }}</h3>@if($photo = data_get($intake,"photos.{$key}"))<a href="{{ Storage::url($photo) }}" target="_blank"><img src="{{ Storage::url($photo) }}" alt="Foto {{ $label }}"></a>@if($canManage)<form method="POST" action="{{ route('members.photos.destroy', [$member, $key]) }}">@csrf @method('DELETE')<button class="tfc-text-button">Eliminar</button></form>@endif@else<p>Sin foto.</p>@endif</article>@endforeach</div></section>
                <section data-member-panel="legal" class="hidden"><h2>Consentimientos</h2><dl class="tfc-data-list"><div><dt>Fecha de aceptación</dt><dd>{{ $member->consent_date?->format('d/m/Y') ?: '—' }}</dd></div><div><dt>Consentimiento informado</dt><dd>{{ $yesNo($member->informed_consent_accepted) }}</dd></div><div><dt>Asunción de riesgos</dt><dd>{{ $yesNo($member->risk_assumption_accepted) }}</dd></div></dl></section>
                <section data-member-panel="qr" class="hidden"><div class="tfc-member-grid"><article><h2>Carnet de acceso</h2><p class="tfc-muted">Genera el QR para guardarlo en el móvil, imprimirlo o compartirlo por WhatsApp.</p>@if($canIssueCard)<a class="tfc-button tfc-button--small mt-4" href="{{ route('members.card.show', $member) }}">Abrir carnet →</a>@else<p class="tfc-member-note">Solicita a un responsable que genere el carnet.</p>@endif</article><article><h2>Seguridad</h2><p class="tfc-muted">El código es personal. Si se pierde o se comparte por error, puede regenerarse y el anterior dejará de funcionar.</p>@if($member->check_in_token_rotated_at)<p class="tfc-muted mt-4">Regenerado el {{ $member->check_in_token_rotated_at->format('d/m/Y H:i') }}.</p>@endif</article></div></section>
                @include('members.partials.training-plan')
                @include('members.partials.incidents')
                @if (config('member-portal.enabled') === true)
                    @include('members.partials.portal-access')
                @endif
            </div>
    </section>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('[data-member-tab]').forEach(button=>button.addEventListener('click',()=>{const tab=button.dataset.memberTab;document.querySelectorAll('[data-member-tab]').forEach(item=>item.classList.toggle('is-active',item===button));document.querySelectorAll('[data-member-panel]').forEach(panel=>panel.classList.toggle('hidden',panel.dataset.memberPanel!==tab));}));document.querySelector('[data-copy-token]')?.addEventListener('click',e=>navigator.clipboard.writeText(document.querySelector('.tfc-qr-token').textContent).then(()=>e.currentTarget.textContent='Código copiado'))});</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const buttons = [...document.querySelectorAll('[data-member-tab]')];
    const panels = [...document.querySelectorAll('[data-member-panel]')];
    const groups = {
        resumen: ['resumen'],
        entrenamiento: ['entrenamiento'],
        seguimiento: ['progresos', 'historial'],
        asistencias: ['asistencias'],
        gestion: ['pagos', 'incidencias', 'portal'],
        ficha: ['kyc', 'salud', 'medidas', 'fotos', 'legal', 'qr'],
    };
    const activate = tab => {
        if (!buttons.some(button => button.dataset.memberTab === tab)) return;
        buttons.forEach(button => {
            const active = button.dataset.memberTab === tab;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(panel => panel.classList.toggle('hidden', !groups[tab]?.includes(panel.dataset.memberPanel)));
        history.replaceState(null, '', `#${tab}`);
    };
    buttons.forEach(button => button.addEventListener('click', () => activate(button.dataset.memberTab)));
    const quickTabs = { progresos: 'seguimiento', pagos: 'gestion' };
    document.querySelectorAll('[data-member-quick-tab]').forEach(button => button.addEventListener('click', () => {
        activate(quickTabs[button.dataset.memberQuickTab]);
        document.querySelector(`[data-member-panel="${button.dataset.memberQuickTab}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));
    const requested = @json(old('_member_section')) || location.hash.slice(1);
    if (requested) activate(requested);

    const list = document.querySelector('[data-exercise-list]');
    const template = document.getElementById('training-exercise-template');
    const renumber = () => list?.querySelectorAll('[data-exercise-row]').forEach((row, index) => row.querySelectorAll('[data-name]').forEach(input => input.name = `items[${index}][${input.dataset.name}]`));
    document.querySelector('[data-add-exercise]')?.addEventListener('click', () => {
        list.append(template.content.cloneNode(true));
        renumber();
        list.lastElementChild.querySelector('[data-name="exercise"]').focus();
    });
    list?.addEventListener('click', event => {
        const remove = event.target.closest('[data-remove-exercise]');
        if (!remove) return;
        if (list.children.length === 1) list.querySelectorAll('input').forEach(input => input.value = '');
        else remove.closest('[data-exercise-row]').remove();
        renumber();
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const paymentForm = document.querySelector('form[action$="/pagos"]');
    if (!paymentForm) return;

    const summary = @json($sessionSummary);
    const key = document.createElement('input');
    key.type = 'hidden';
    key.name = 'idempotency_key';
    key.value = crypto.randomUUID();
    paymentForm.append(key);

    const sessions = paymentForm.querySelector('[name="sessions_purchased"]');
    const preview = document.createElement('div');
    preview.className = 'tfc-member-note';
    preview.innerHTML = '<strong>Previsualización</strong><dl class="tfc-data-list"><div><dt>Disponibles actuales</dt><dd data-available></dd></div><div><dt>Pendientes actuales</dt><dd data-pending></dd></div><div><dt>Se regularizarán</dt><dd data-regularized></dd></div><div><dt>Disponibles después</dt><dd data-after></dd></div></dl>';
    sessions.closest('label').after(preview);

    const updatePreview = () => {
        const purchased = Math.max(Number.parseInt(sessions.value, 10) || 0, 0);
        const regularized = Math.min(summary.pending_sessions, purchased);
        preview.querySelector('[data-available]').textContent = summary.available_sessions;
        preview.querySelector('[data-pending]').textContent = summary.pending_sessions;
        preview.querySelector('[data-regularized]').textContent = regularized;
        preview.querySelector('[data-after]').textContent = Math.max(summary.mathematical_balance + purchased, 0);
    };

    sessions.addEventListener('input', updatePreview);
    updatePreview();
});
</script>
</x-layouts::app>
