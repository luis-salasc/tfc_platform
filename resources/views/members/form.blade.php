<x-layouts::app :title="$member->exists ? ($preRegistrationMode ?? false ? 'Editar alta' : 'Editar miembro') : 'Nuevo miembro'">
@php
    $preRegistrationMode = $preRegistrationMode ?? false; $isEdit = $member->exists; $intake = $member->intake ?? [];
    $addressValue = old('address', $preRegistrationMode ? data_get($intake, 'address') : $member->address);
    $cityValue = old('city', $preRegistrationMode ? data_get($intake, 'city') : $member->city);
    $publicAliasValue = old('public_alias', $preRegistrationMode ? data_get($intake, 'public_alias') : $member->public_alias);
    $discoveryChannels = $preRegistrationMode
        ? ['recomendacion' => 'Recomendación', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'busqueda_internet' => 'Búsqueda en Internet', 'publicidad' => 'Publicidad', 'paso_por_el_centro' => 'Pasé por el centro', 'otro' => 'Otro']
        : ['folleto_calle' => 'Folleto por la calle', 'publicidad_buzoneo' => 'Publicidad buzoneo', 'busqueda_internet' => 'Búsqueda en internet', 'Facebook' => 'Facebook', 'Instagram' => 'Instagram', 'Twitter' => 'X / Twitter', 'recomendacion' => 'Recomendación', 'otro' => 'Otro'];
    $caaf = ['heart_condition' => '&iquest;Te ha dicho el m&eacute;dico que tienes una enfermedad de coraz&oacute;n y que debes hacer actividad solo bajo supervisi&oacute;n?', 'chest_activity' => '&iquest;Notas dolor en el pecho durante actividad f&iacute;sica?', 'chest_rest' => '&iquest;Has notado dolor en el pecho en reposo durante el &uacute;ltimo mes?', 'dizziness' => '&iquest;Has perdido la conciencia o el equilibrio tras una sensaci&oacute;n de mareo?', 'bones_joints' => '&iquest;Tienes un problema en huesos o articulaciones que podr&iacute;a empeorar con actividad?', 'medication' => '&iquest;Te han prescrito medicaci&oacute;n para tensi&oacute;n arterial o coraz&oacute;n?', 'other_reason' => '&iquest;Hay otra raz&oacute;n, propia o indicada por un m&eacute;dico, que te impida ejercitarte sin supervisi&oacute;n?'];
    $objectives = ['mejorar_salud'=>'Mejorar salud','subir_peso'=>'Subir de peso','bajar_peso'=>'Bajar de peso','disminuir_volumen'=>'Disminuir volumen','tonificar'=>'Tonificar / endurecer','mejorar_postura'=>'Mejorar postura','aumentar_resistencia'=>'Aumentar resistencia','reducir_grasa'=>'Reducir grasa corporal','sentirse_mejor'=>'Sentirse mejor','aumentar_masa_muscular'=>'Aumentar masa muscular','rehabilitacion'=>'Rehabilitaci&oacute;n','aliviar_dolores'=>'Aliviar dolores corporales','fortalecer'=>'Fortalecer','rendimiento_laboral'=>'Mejorar rendimiento laboral','dormir_mejor'=>'Dormir mejor','eliminar_celulitis'=>'Eliminar celulitis','reducir_estres'=>'Reducir estr&eacute;s','rendimiento_deportivo'=>'Mejorar rendimiento deportivo'];
@endphp
<div class="tfc-platform-page max-w-6xl space-y-7"><x-status-message />
    <header><p class="tfc-kicker">{{ $isEdit ? ($preRegistrationMode ? 'Continuar alta' : 'Ficha completa') : 'Nueva alta' }}</p><h1 class="tfc-title">{{ $isEdit ? ($preRegistrationMode ? 'Continuar alta' : 'Editar miembro') : 'Nuevo miembro' }}</h1><p class="tfc-subtitle">Cuatro hojas digitales, en el mismo orden que la entrevista presencial.</p></header>
    @if ($errors->any())<div class="border border-red-400 bg-red-950 p-4 text-red-100"><strong>Revisa los datos marcados.</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $preRegistrationMode ? ($isEdit ? route('pre-registrations.update', $member) : route('pre-registrations.store')) : ($isEdit ? route('miembros.update', $member) : route('miembros.store')) }}" class="tfc-form registration-wizard p-5 md:p-8" data-registration-wizard data-pre-registration="{{ $preRegistrationMode ? 'true' : 'false' }}" data-can-review="{{ ($canReview ?? false) ? 'true' : 'false' }}" data-employee-name="{{ auth()->user()->name }}" data-recorded-at="{{ now()->format('d/m/Y H:i') }}">@csrf @if($isEdit) @method('PUT') @endif
        <div class="registration-submit-overlay" data-submit-overlay hidden role="status" aria-live="assertive" tabindex="-1"><div><span class="registration-submit-spinner" aria-hidden="true"></span><strong>Guardando miembro...</strong><p>Por favor, espera.</p></div></div>
        <div data-registration-form-content>
        <div class="wizard-progress"><span data-progress></span></div><div class="wizard-steps"><span data-step-indicator="1">01 &middot; Acerca de ti</span><span data-step-indicator="2">02 &middot; Entrevista</span><span data-step-indicator="3">03 &middot; Salud</span><span data-step-indicator="4">04 &middot; {{ $preRegistrationMode ? 'Documentos y firma' : 'Consentimiento' }}</span></div>

        <section data-step="0" class="wizard-step space-y-6"><div><p class="tfc-kicker">Antes de empezar</p><h2 class="tfc-section-title">Tipo de alta</h2><p class="tfc-subtitle">Elige el servicio antes de recoger datos. Este valor queda registrado y no depende de lo que escriba el cliente.</p></div><fieldset class="tfc-fieldset"><legend>Tipo de miembro <span aria-hidden="true">*</span></legend><div class="grid gap-4 md:grid-cols-2"><label class="tfc-check"><input type="radio" name="client_type" value="presencial" @checked(old('client_type', $member->client_type) === 'presencial') required> Presencial: la ficha se completa en el centro.</label><label class="tfc-check"><input type="radio" name="client_type" value="online" @checked(old('client_type', $member->client_type) === 'online') required> Online: el cliente completar&aacute; la ficha desde una invitaci&oacute;n segura.</label></div></fieldset></section>

        <section data-step="1" class="wizard-step space-y-6"><div><p class="tfc-kicker">Hoja 1 de 4</p><h2 class="tfc-section-title">Acerca de ti</h2><p class="tfc-subtitle">Datos de contacto, motivaci&oacute;n y objetivo inicial.</p></div>
            <p class="tfc-kicker">Los campos con <span aria-hidden="true">*</span> son obligatorios.</p>
            <fieldset class="tfc-fieldset"><legend>Datos personales</legend><div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3"><label class="tfc-field">Nombre <span aria-hidden="true">*</span><input name="first_name" value="{{ old('first_name', $member->first_name) }}" maxlength="120" autocomplete="given-name" pattern="[^0-9]+" required></label><label class="tfc-field">Apellidos <span aria-hidden="true">*</span><input name="last_name" value="{{ old('last_name', $member->last_name) }}" maxlength="160" autocomplete="family-name" pattern="[^0-9]+" required></label><label class="tfc-field">Fecha de nacimiento <span aria-hidden="true">*</span><input type="date" name="birth_date" value="{{ old('birth_date', optional($member->birth_date)->format('Y-m-d')) }}" max="{{ now()->toDateString() }}" autocomplete="bday" required></label><label class="tfc-field">Sexo <span aria-hidden="true">*</span><select name="sex" required><option value="">Seleccionar</option>@foreach(['hombre' => 'Hombre', 'mujer' => 'Mujer', 'no_aplica' => 'No aplica'] as $value => $label)<option value="{{ $value }}" @selected(old('sex', data_get($intake, 'sex')) === $value)>{{ $label }}</option>@endforeach</select></label>@if ($preRegistrationMode)<label class="tfc-field">Tipo de documento <span aria-hidden="true">*</span><select name="document_type" required><option value="">Seleccionar</option>@foreach(['dni' => 'DNI', 'nie' => 'NIE', 'passport' => 'Pasaporte'] as $value => $label)<option value="{{ $value }}" @selected(old('document_type', $member->document_type) === $value)>{{ $label }}</option>@endforeach</select></label><label class="tfc-field">Número de documento <span aria-hidden="true">*</span><input name="document_number" value="{{ old('document_number', $member->document_number) }}" minlength="3" maxlength="30" inputmode="text" required></label>@else<label class="tfc-field">DNI / NIE<input name="national_id" value="{{ old('national_id', $member->national_id) }}" maxlength="30"></label>@endif<label class="tfc-field">Móvil <span aria-hidden="true">*</span><input type="tel" name="phone" value="{{ old('phone', $member->phone) }}" maxlength="40" inputmode="tel" autocomplete="tel" pattern="[+0-9 ()-]+" required></label><label class="tfc-field">Email <span aria-hidden="true">*</span><input type="email" name="email" value="{{ old('email', $member->email) }}" maxlength="150" autocomplete="email" required></label><label class="tfc-field">Dirección<input name="address" value="{{ $addressValue }}" maxlength="255" autocomplete="street-address"></label><label class="tfc-field">Localidad <span aria-hidden="true">*</span><input name="city" value="{{ $cityValue }}" maxlength="120" autocomplete="address-level2" pattern="[^0-9]+" required></label><label class="tfc-field">¿A qué te dedicas?<input name="occupation" value="{{ old('occupation', data_get($intake, 'occupation')) }}" maxlength="160" autocomplete="organization-title"></label>@unless ($preRegistrationMode)<label class="tfc-field">Alias público<input name="public_alias" value="{{ $publicAliasValue }}" maxlength="40" placeholder="Para Kiosco y retos"></label>@endunless</div></fieldset>
            <fieldset class="tfc-fieldset"><legend>¿Cómo nos has conocido?</legend><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">@foreach($discoveryChannels as $value => $label)<label class="tfc-check"><input type="radio" name="discovery_channel" value="{{ $value }}" @checked(old('discovery_channel', data_get($intake, 'discovery_channel')) === $value)> {{ $label }}</label>@endforeach</div>@if ($preRegistrationMode)<div class="hidden mt-4" data-discovery-detail><label class="tfc-field"><span data-discovery-detail-label></span><input name="discovery_detail" value="{{ old('discovery_detail', data_get($intake, 'discovery_detail')) }}" disabled></label></div>@else<label class="tfc-field mt-4">Detalle de recomendación u otro origen<input name="discovery_detail" value="{{ old('discovery_detail', data_get($intake, 'discovery_detail')) }}"></label>@endif</fieldset>
            <fieldset class="tfc-fieldset"><legend>&iquest;Qu&eacute; resultados quieres tener?</legend><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach($objectives as $value=>$label)<label class="tfc-check"><input type="checkbox" name="objectives[]" value="{{ $value }}" @checked(in_array($value, old('objectives', data_get($intake,'objectives', [])) ?: []))>{!! $label !!}</label>@endforeach</div><div class="mt-4 grid gap-4 md:grid-cols-2"><label class="tfc-field">Otro resultado<input name="other_objective" value="{{ old('other_objective', data_get($intake,'other_objective')) }}"></label><label class="tfc-field">Quiero conseguirlo antes de<input type="date" name="goal_date" value="{{ old('goal_date', data_get($intake,'goal_date')) }}"></label></div></fieldset>
            @if($isEdit)<p class="tfc-member-note">Fecha de registro: {{ $member->created_at->format('d/m/Y H:i') }}</p>@endif
        </section>

        <section data-step="2" class="wizard-step hidden space-y-6"><div><p class="tfc-kicker">Hoja 2 de 4</p><h2 class="tfc-section-title">Entrevista de inicio</h2><p class="tfc-subtitle">H&aacute;bitos, barreras y preferencias para planificar el entrenamiento.</p></div>
            <label class="tfc-field">¿Qué importancia tiene para ti alcanzar tus objetivos?<select name="objective_importance"><option value="">Seleccionar</option>@foreach(range(1, 10) as $importance)<option value="{{ $importance }}" @selected((string) old('objective_importance', data_get($intake,'objective_importance')) === (string) $importance)>{{ $importance }}</option>@endforeach</select><span class="text-sm text-zinc-400">1 = poca importancia · 10 = máxima importancia</span></label>
            <fieldset class="tfc-fieldset"><legend>&iquest;HACES EJERCICIO REGULARMENTE?</legend><label class="tfc-field">&iquest;Haces ejercicio regularmente?<select name="current_exercise" data-current-exercise><option value="">Seleccionar</option><option value="Si" @selected(old('current_exercise', data_get($intake,'current_exercise')) === 'Si')>S&iacute;</option><option value="No" @selected(old('current_exercise', data_get($intake,'current_exercise')) === 'No')>No</option></select></label><div class="mt-5 grid gap-4 hidden" data-exercise-current><label class="tfc-field">&iquest;Qu&eacute; tipo de ejercicio?<textarea name="current_exercise_type">{{ old('current_exercise_type', data_get($intake,'current_exercise_type')) }}</textarea></label><label class="tfc-field">&iquest;Cu&aacute;ntas veces por semana?<input type="number" min="1" max="7" name="current_exercise_frequency" value="{{ old('current_exercise_frequency', data_get($intake,'current_exercise_frequency')) }}"></label><label class="tfc-field">&iquest;Cu&aacute;nto tiempo llevas haci&eacute;ndolo de forma regular?<input name="current_exercise_duration" value="{{ old('current_exercise_duration', data_get($intake,'current_exercise_duration')) }}"></label><label class="tfc-field">&iquest;Est&aacute;s obteniendo resultados?<select name="current_exercise_results"><option value="">Seleccionar</option><option value="Si" @selected(old('current_exercise_results', data_get($intake,'current_exercise_results')) === 'Si')>S&iacute;</option><option value="No" @selected(old('current_exercise_results', data_get($intake,'current_exercise_results')) === 'No')>No</option></select></label></div><div class="mt-5 hidden" data-exercise-past><label class="tfc-field">&iquest;Has hecho alguna vez ejercicio de forma regular?<select name="past_exercise" data-past-exercise><option value="">Seleccionar</option><option value="Si" @selected(old('past_exercise', data_get($intake,'past_exercise')) === 'Si')>S&iacute;</option><option value="No" @selected(old('past_exercise', data_get($intake,'past_exercise')) === 'No')>No</option></select></label><div class="mt-5 grid gap-4 hidden" data-past-exercise-details><label class="tfc-field">Si has contestado s&iacute;, &iquest;qu&eacute; hac&iacute;as?<textarea name="past_exercise_type">{{ old('past_exercise_type', data_get($intake,'past_exercise_type')) }}</textarea></label><label class="tfc-field">&iquest;Cu&aacute;ntas veces por semana?<input type="number" min="1" max="7" name="past_exercise_frequency" value="{{ old('past_exercise_frequency', data_get($intake,'past_exercise_frequency')) }}"></label><label class="tfc-field">&iquest;Hace cu&aacute;nto tiempo?<input name="past_exercise_since" value="{{ old('past_exercise_since', data_get($intake,'past_exercise_since')) }}"></label><label class="tfc-field">&iquest;Durante cu&aacute;nto tiempo lo hiciste?<input name="past_exercise_duration" value="{{ old('past_exercise_duration', data_get($intake,'past_exercise_duration')) }}"></label><label class="tfc-field">&iquest;Obtuviste los resultados que quer&iacute;as?<select name="past_exercise_results"><option value="">Seleccionar</option><option value="Si" @selected(old('past_exercise_results', data_get($intake,'past_exercise_results')) === 'Si')>S&iacute;</option><option value="No" @selected(old('past_exercise_results', data_get($intake,'past_exercise_results')) === 'No')>No</option></select></label><label class="tfc-field">Si los conseguiste, &iquest;por qu&eacute; lo dejaste?<textarea name="past_exercise_reason">{{ old('past_exercise_reason', data_get($intake,'past_exercise_reason')) }}</textarea></label></div></div></fieldset>
            <fieldset class="tfc-fieldset"><legend>&iquest;Por qu&eacute; no has empezado antes?</legend><div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach(['compromisos_familiares'=>'Compromisos familiares','falta_motivacion'=>'Falta de motivaci&oacute;n','falta_tiempo'=>'Falta de tiempo','dejadez'=>'Dejadez','lesion_salud'=>'Lesi&oacute;n / problema de salud','trabajo'=>'Trabajo'] as $value=>$label)<label class="tfc-check"><input type="checkbox" name="barriers[]" value="{{ $value }}" @checked(in_array($value, old('barriers', data_get($intake,'barriers', [])) ?: []))>{!! $label !!}</label>@endforeach</div><div class="mt-4 grid gap-4 md:grid-cols-2"><div class="tfc-question"><p>&iquest;Sigue siendo un problema?</p><label><input type="radio" name="barriers_still_active" value="1" @checked((string) old('barriers_still_active', data_get($intake,'barriers_still_active')) === '1')> S&iacute;</label><label><input type="radio" name="barriers_still_active" value="0" @checked((string) old('barriers_still_active', data_get($intake,'barriers_still_active')) === '0')> No</label></div><div class="tfc-question"><p>&iquest;En qu&eacute; momento prefieres entrenar?</p><div class="grid gap-3"><label><input type="checkbox" name="preferred_training_times[]" value="m" data-training-time="m" @checked(in_array('m', old('preferred_training_times', data_get($intake,'preferred_training_times', [])) ?: []))> Ma&ntilde;ana</label><div class="hidden grid gap-2 sm:grid-cols-2" data-training-range="m"><label class="tfc-field">Desde<input type="time" name="preferred_training_ranges[m][from]" value="{{ old('preferred_training_ranges.m.from', data_get($intake,'preferred_training_ranges.m.from')) }}"></label><label class="tfc-field">Hasta<input type="time" name="preferred_training_ranges[m][to]" value="{{ old('preferred_training_ranges.m.to', data_get($intake,'preferred_training_ranges.m.to')) }}"></label></div><label><input type="checkbox" name="preferred_training_times[]" value="t" data-training-time="t" @checked(in_array('t', old('preferred_training_times', data_get($intake,'preferred_training_times', [])) ?: []))> Tarde</label><div class="hidden grid gap-2 sm:grid-cols-2" data-training-range="t"><label class="tfc-field">Desde<input type="time" name="preferred_training_ranges[t][from]" value="{{ old('preferred_training_ranges.t.from', data_get($intake,'preferred_training_ranges.t.from')) }}"></label><label class="tfc-field">Hasta<input type="time" name="preferred_training_ranges[t][to]" value="{{ old('preferred_training_ranges.t.to', data_get($intake,'preferred_training_ranges.t.to')) }}"></label></div></div></div></div></fieldset>
            <label class="tfc-field">Lesiones o zonas afectadas: describe zona, intensidad y limitaciones conocidas<textarea rows="5" name="injury_notes">{{ old('injury_notes', data_get($intake,'injury_notes')) }}</textarea></label>
        </section>

        <section data-step="3" class="wizard-step hidden space-y-6"><div><p class="tfc-kicker">Hoja 3 de 4</p><h2 class="tfc-section-title">Cuestionario de aptitud para la actividad f&iacute;sica</h2><p class="tfc-subtitle">C-AAF. No es un diagn&oacute;stico: una respuesta afirmativa requiere revisi&oacute;n profesional.</p></div><div class="tfc-caaf-alert hidden" data-caaf-alert>Hay una o m&aacute;s respuestas que requieren revisi&oacute;n del entrenador antes de aumentar la actividad.</div><div class="space-y-3">@foreach($caaf as $key=>$question)<div class="tfc-question"><p>{{ $loop->iteration }}. {!! $question !!}</p><label><input type="radio" name="caaf[{{ $key }}]" value="1" @checked((string) old("caaf.$key", data_get($intake,"caaf.$key")) === '1') required> S&iacute;</label><label><input type="radio" name="caaf[{{ $key }}]" value="0" @checked((string) old("caaf.$key", data_get($intake,"caaf.$key")) === '0') required> No</label></div>@endforeach</div><label class="tfc-field">Informaci&oacute;n m&eacute;dica adicional, dolores cr&oacute;nicos o cirug&iacute;as recientes<textarea rows="5" name="medical_notes">{{ old('medical_notes', data_get($intake,'medical_notes')) }}</textarea></label><div class="tfc-question"><p>&iquest;Est&aacute;s embarazada o crees que podr&iacute;as estarlo?</p><label><input type="radio" name="pregnancy" value="1" @checked((string) old('pregnancy', data_get($intake,'pregnancy')) === '1')> S&iacute;</label><label><input type="radio" name="pregnancy" value="0" @checked((string) old('pregnancy', data_get($intake,'pregnancy')) === '0')> No</label><label><input type="radio" name="pregnancy" value="" @checked(old('pregnancy', data_get($intake,'pregnancy')) === null)> No aplica / prefiero no responder</label></div></section>

        <section data-step="4" data-documents-step class="wizard-step hidden space-y-6"><div><p class="tfc-kicker">Hoja 4 de 4</p><h2 class="tfc-section-title">{{ $preRegistrationMode ? 'Documentos y firma' : 'Consentimiento informado' }}</h2><p class="tfc-subtitle">El miembro debe revisar y aceptar este contenido antes de finalizar el alta.</p></div><div class="tfc-consent"><h3>Consentimiento informado y asunci&oacute;n de riesgos</h3><p>Las pruebas, test y cuestionarios permiten valorar la condición física general y orientar el entrenamiento de fuerza y resistencia muscular.</p><p>La información será confidencial y se usará exclusivamente para seguimiento profesional. El miembro declara haber recibido explicación, haber podido plantear dudas y comprender los riesgos habituales de la actividad física.</p><p>El entrenamiento puede realizarse en el centro o, cuando se acuerde expresamente, en el domicilio u otro lugar pactado.</p></div>@if($preRegistrationMode)<div class="tfc-member-note"><strong>Tramitado por:</strong> {{ auth()->user()->name }} · {{ now()->format('d/m/Y H:i') }}</div>@else<div class="grid gap-4 md:grid-cols-2"><label class="tfc-field">Fecha de aceptación<input type="date" name="consent_date" value="{{ old('consent_date', optional($member->consent_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required></label><label class="tfc-field">Estado<select name="status">@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $member->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>@endforeach</select></label></div>@endif<label class="tfc-check"><input type="checkbox" name="informed_consent_accepted" value="1" @checked(old('informed_consent_accepted', $member->informed_consent_accepted)) required> El miembro acepta el consentimiento informado.</label><label class="tfc-check"><input type="checkbox" name="risk_assumption_accepted" value="1" @checked(old('risk_assumption_accepted', $member->risk_assumption_accepted)) required> El miembro acepta la asunción de riesgos y las condiciones del servicio.</label>@unless($preRegistrationMode)<label class="tfc-check"><input type="checkbox" name="trainer_intake_reviewed" value="1" @checked(old('trainer_intake_reviewed', data_get($intake,'trainer_intake_reviewed'))) required> He revisado la ficha con el miembro y registraré cualquier incidencia que requiera seguimiento.</label>@endunless@if($preRegistrationMode)<div class="tfc-signature-field"><p class="font-semibold">Firma del miembro <span aria-hidden="true">*</span></p><canvas data-member-signature width="720" height="240" tabindex="0" aria-label="Firma del miembro"></canvas><input type="hidden" name="member_signature" data-member-signature-input><div class="mt-3 flex gap-3"><button type="button" class="tfc-text-button" data-clear-member-signature>Limpiar firma</button><span class="hidden text-sm text-red-300" data-member-signature-error>La firma del miembro es obligatoria.</span></div></div>@endif<p class="text-sm text-zinc-400">Este registro guarda una aceptación digital con fecha y versión del texto. No sustituye una firma electrónica cualificada ni asesoramiento jurídico sobre el documento definitivo.</p></section>
        <article data-step="4" class="wizard-step tfc-consent space-y-4"><h3>INFORME CONSENTIMIENTO INFORMADO</h3><p>Las pruebas, test y cuestionarios realizados para evaluar su condici&oacute;n f&iacute;sica permitir&aacute;n obtener informaci&oacute;n sobre su estado general de salud.</p><p>Los entrenamientos a realizar son de fuerza y resistencia muscular. Usted podr&aacute; experimentar fatiga durante la realizaci&oacute;n de dichas pruebas y/o al finalizar las mismas.</p><p>Al firmar este documento, usted manifiesta expl&iacute;citamente que se le han explicado y ha entendido la descripci&oacute;n de las pruebas a realizar y sus posibles complicaciones. Adem&aacute;s, usted indica que cualquier duda que haya podido surgir con el proceso de evaluaci&oacute;n y sus potenciales riesgos ha sido respondida con claridad, quedando satisfecho con las explicaciones aportadas.</p><p>La informaci&oacute;n obtenida como consecuencia de dichas pruebas ser&aacute; considerada como confidencial, pudi&eacute;ndose utilizar &uacute;nicamente con fines cient&iacute;ficos, salvaguardando en cualquier caso su identidad. Para ello ser&aacute; necesario su expreso consentimiento mediante autorizaci&oacute;n por escrito.</p><p>Al firmar el presente documento usted acepta la completa responsabilidad de su propia salud, y reconoce que ha sido informado y ha entendido que esta responsabilidad no es asumida por los responsables de su programa de ejercicio f&iacute;sico.</p><h3>INFORME DE ASUNCI&Oacute;N DE RIESGOS</h3><p>Yo, {{ trim(old('first_name', $member->first_name).' '.old('last_name', $member->last_name)) ?: '________________________________' }}, asumo, acepto y eximo de cualquier responsabilidad al entrenador deportivo por las lesiones o accidentes que pudieran resultar de mi participaci&oacute;n en el citado programa.</p><p>Entiendo los procedimientos aplicados y manifiesto que he tenido la oportunidad de discutir mis necesidades espec&iacute;ficas en relaci&oacute;n con mi participaci&oacute;n en el citado programa; como resultado de ello, acepto las condiciones expuestas para la participaci&oacute;n en el citado programa.</p><p>Manifiesto conocer la existencia de riesgos asociados a la pr&aacute;ctica de actividad f&iacute;sica y estoy de acuerdo en aceptar las responsabilidades derivadas de mi participaci&oacute;n y del uso de las instalaciones y/o equipamiento espec&iacute;fico o gen&eacute;rico.</p><h4>ACUERDO:</h4><p>Yo, Don/Do&ntilde;a {{ trim(old('first_name', $member->first_name).' '.old('last_name', $member->last_name)) ?: '________________________________' }}, he solicitado este asesoramiento, mediante el cual autorizo al mencionado entrenador personal a dirigir mi entrenamiento en mi propio domicilio o en el lugar que de com&uacute;n acuerdo concertemos.</p></article>
        @if($preRegistrationMode)
            <section data-step="4" data-pre-registration-documents-step class="wizard-step hidden space-y-6">
                <div><p class="tfc-kicker">Hoja 4 de 4</p><h2 class="tfc-section-title">Documentos y firma</h2><p class="tfc-subtitle">El miembro debe revisar y aceptar cada documento antes de finalizar el alta.</p></div>
                <section class="tfc-consent space-y-4" data-informed-consent-document>
                    <h3>INFORME CONSENTIMIENTO INFORMADO</h3>
                    <p><strong>Nombre:</strong> <span data-member-document-name></span></p>
                    <p><strong>Documento:</strong> <span data-member-document></span></p>
                    <p>Las pruebas, test y cuestionarios realizados para evaluar su condición física permitirán obtener información sobre su estado general de salud.</p>
                    <p>Los entrenamientos a realizar son de fuerza y resistencia muscular. Usted podrá experimentar fatiga durante la realización de dichas pruebas y/o al finalizar las mismas.</p>
                    <p>Al firmar este documento, usted manifiesta explícitamente que se le han explicado y ha entendido la descripción de las pruebas a realizar y sus posibles complicaciones. Además, usted indica que cualquier duda que haya podido surgir con el proceso de evaluación y sus potenciales riesgos ha sido respondida con claridad, quedando satisfecho con las explicaciones aportadas.</p>
                    <p>La información obtenida como consecuencia de dichas pruebas será considerada como confidencial, pudiéndose utilizar únicamente con fines científicos, salvaguardando en cualquier caso su identidad. Para ello será necesario su expreso consentimiento mediante autorización por escrito.</p>
                    <p>Al firmar el presente documento usted acepta la completa responsabilidad de su propia salud, y reconoce que ha sido informado y ha entendido que esta responsabilidad no es asumida por los responsables de su programa de ejercicio físico.</p>
                    <label class="tfc-check"><input type="checkbox" name="informed_consent_accepted" value="1" required> El miembro acepta el consentimiento informado.</label>
                </section>
                <section class="tfc-consent space-y-4" data-risk-assumption-document>
                    <h3>INFORME DE ASUNCIÓN DE RIESGOS</h3>
                    <p>Yo, <span data-member-risk-name></span>, asumo, acepto y eximo de cualquier responsabilidad al entrenador deportivo por las lesiones o accidentes que pudieran resultar de mi participación en el citado programa.</p>
                    <p>Entiendo los procedimientos aplicados y manifiesto que he tenido la oportunidad de discutir mis necesidades específicas en relación con mi participación en el citado programa; como resultado de ello, acepto las condiciones expuestas para la participación en el citado programa.</p>
                    <p>Manifiesto conocer la existencia de riesgos asociados a la práctica de actividad física y estoy de acuerdo en aceptar las responsabilidades derivadas de mi participación y del uso de las instalaciones y/o equipamiento específico o genérico.</p>
                    <h4>ACUERDO:</h4>
                    <p>Yo, Don/Doña <span data-member-risk-name></span>, he solicitado este asesoramiento, mediante el cual autorizo al mencionado entrenador personal a dirigir mi entrenamiento en mi propio domicilio o en el lugar que de común acuerdo concertemos.</p>
                    <label class="tfc-check"><input type="checkbox" name="risk_assumption_accepted" value="1" required> El miembro acepta la asunción de riesgos y las condiciones del servicio.</label>
                </section>
                <div class="tfc-signature-field"><p class="font-semibold">Firma del miembro <span aria-hidden="true">*</span></p><canvas data-member-signature width="720" height="240" tabindex="0" aria-label="Firma del miembro"></canvas><input type="hidden" name="member_signature" data-member-signature-input><div class="mt-3 flex gap-3"><button type="button" class="tfc-text-button" data-clear-member-signature>Limpiar firma</button><span class="hidden text-sm text-red-300" data-member-signature-error>La firma del miembro es obligatoria.</span></div></div>
                <div class="tfc-member-note"><strong>Tramitado por:</strong> {{ auth()->user()->name }} · {{ now()->format('d/m/Y H:i') }}</div>
                <p class="text-sm text-zinc-400">Este registro guarda una aceptación digital con fecha y versión del texto. No sustituye una firma electrónica cualificada ni asesoramiento jurídico sobre el documento definitivo.</p>
            </section>
        @endif
        @unless($preRegistrationMode)<label data-step="4" class="wizard-step tfc-check mt-5"><input type="checkbox" name="send_consent_by_email" value="1" @checked(old('send_consent_by_email'))> Enviar una copia de estos documentos al correo del miembro.</label>@endunless
        <footer class="mt-8 flex items-center justify-between gap-3 border-t border-white/15 pt-5"><button type="button" class="tfc-wizard-button" data-previous disabled>&larr; Anterior</button><span class="text-xs font-bold tracking-widest text-zinc-400" data-step-label>01 / 04</span><button type="button" class="tfc-wizard-button tfc-wizard-button--primary" data-next>Siguiente &rarr;</button><button type="submit" class="tfc-wizard-button tfc-wizard-button--primary hidden" data-submit>{{ $preRegistrationMode ? 'Finalizar alta' : 'Guardar miembro' }} &rarr;</button></footer>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-registration-wizard][data-pre-registration="true"]');
    if (!form) return;

    form.querySelector('[data-documents-step]')?.remove();
    form.querySelector('article.tfc-consent')?.remove();
    const completion = form.querySelector('[data-pre-registration-documents-step]');
    if (!completion) return;
    if (false) {
    const footer = form.querySelector('footer');
    const completion = document.createElement('section');
    completion.dataset.step = '4';
    completion.className = 'wizard-step hidden space-y-6';
    completion.innerHTML = `<div><p class="tfc-kicker">Hoja 4 de 4</p><h2 class="tfc-section-title">Documentos y firma</h2><p class="tfc-subtitle">El miembro debe revisar y aceptar cada documento antes de finalizar el alta.</p></div><section class="tfc-consent space-y-4"><h3>INFORME CONSENTIMIENTO INFORMADO</h3><p><strong>Miembro:</strong> <span data-member-document-name></span></p><p><strong>Documento:</strong> <span data-member-document></span></p><p>Las pruebas, test y cuestionarios realizados para evaluar su condición física permitirán obtener información sobre su estado general de salud.</p><p>Los entrenamientos a realizar son de fuerza y resistencia muscular. Usted podrá experimentar fatiga durante la realización de dichas pruebas y/o al finalizar las mismas.</p><p>Al firmar este documento, usted manifiesta explícitamente que se le han explicado y ha entendido la descripción de las pruebas a realizar y sus posibles complicaciones.</p><label class="tfc-check"><input type="checkbox" name="informed_consent_accepted" value="1" required> El miembro acepta el consentimiento informado.</label></section><section class="tfc-consent space-y-4"><h3>INFORME DE ASUNCIÓN DE RIESGOS</h3><p>Yo, <span data-member-risk-name></span>, asumo, acepto y eximo de cualquier responsabilidad al entrenador deportivo por las lesiones o accidentes que pudieran resultar de mi participación en el citado programa.</p><p>Entiendo los procedimientos aplicados y manifiesto que he tenido la oportunidad de discutir mis necesidades específicas en relación con mi participación en el citado programa.</p><p>Manifiesto conocer la existencia de riesgos asociados a la práctica de actividad física y estoy de acuerdo en aceptar las responsabilidades derivadas de mi participación y del uso de las instalaciones y/o equipamiento específico o genérico.</p><label class="tfc-check"><input type="checkbox" name="risk_assumption_accepted" value="1" required> El miembro acepta la asunción de riesgos y las condiciones del servicio.</label></section><div class="tfc-signature-field"><p class="font-semibold">Firma del miembro <span aria-hidden="true">*</span></p><canvas data-member-signature width="720" height="240" tabindex="0" aria-label="Firma del miembro"></canvas><input type="hidden" name="member_signature" data-member-signature-input><div class="mt-3 flex gap-3"><button type="button" class="tfc-text-button" data-clear-member-signature>Limpiar firma</button><span class="hidden text-sm text-red-300" data-member-signature-error>La firma del miembro es obligatoria.</span></div></div><div class="tfc-member-note"><strong>Tramitado por:</strong> ${form.dataset.employeeName} · ${form.dataset.recordedAt}</div><p class="text-sm text-zinc-400">Este registro guarda una aceptación digital con fecha y versión del texto. No sustituye una firma electrónica cualificada ni asesoramiento jurídico sobre el documento definitivo.</p>`;
    footer.before(completion);
    }
    const reviewPanel = document.createElement('div');
    reviewPanel.dataset.reviewPanel = '';
    reviewPanel.className = 'hidden space-y-4';
    reviewPanel.innerHTML = '<div class="tfc-member-note"><strong>Revisión necesaria</strong><p data-review-circumstances class="mt-2"></p></div><label class="tfc-field">Aclaraciones / observaciones<textarea name="review_observations"></textarea></label><label class="tfc-field">Decisión<select name="review_decision"><option value="">Seleccionar</option><option value="can_continue">Puede continuar con el proceso de alta</option><option value="do_not_continue_for_now">No continuar con el proceso por el momento</option></select></label>';
    completion.append(reviewPanel);

    const reviewDecision = reviewPanel.querySelector('[name="review_decision"]');
    const reviewObservations = reviewPanel.querySelector('[name="review_observations"]');
    const updateFinalStep = () => {
        const caaf = [...form.querySelectorAll('input[name^="caaf"][value="1"]:checked')].map(input => input.name.match(/caaf\[([^\]]+)/)?.[1]);
        const pregnancy = form.querySelector('input[name="pregnancy"][value="1"]:checked');
        const requiresReview = caaf.length > 0 || Boolean(pregnancy);
        const canReview = form.dataset.canReview === 'true';
        reviewPanel.classList.toggle('hidden', !requiresReview || !canReview);
        reviewDecision.disabled = !requiresReview || !canReview;
        reviewObservations.disabled = !requiresReview || !canReview;
        reviewDecision.required = requiresReview && canReview;
        reviewObservations.required = requiresReview && canReview;
        completion.querySelector('h2').textContent = requiresReview && canReview ? 'Documentos, firma y revisión' : 'Documentos y firma';
        completion.querySelector('.tfc-subtitle').textContent = requiresReview
            ? (canReview ? 'Registra la conversación y una decisión operativa antes de finalizar el alta.' : 'Esta alta requiere revisión de personal autorizado antes de finalizar.')
            : 'El miembro debe revisar y aceptar este contenido antes de finalizar el alta.';
        reviewPanel.querySelector('[data-review-circumstances]').textContent = [...caaf, ...(pregnancy ? ['embarazo declarado'] : [])].join(', ');
        footer.querySelector('[data-submit]').textContent = requiresReview && !canReview ? 'Enviar para revisión' : 'Finalizar alta';
    };
    form.querySelectorAll('input[name^="caaf"], input[name="pregnancy"]').forEach(input => input.addEventListener('change', updateFinalStep));
    const discoveryDetail = form.querySelector('[data-discovery-detail]');
    const discoveryDetailInput = discoveryDetail?.querySelector('input');
    const discoveryDetailLabel = discoveryDetail?.querySelector('[data-discovery-detail-label]');
    const syncDiscoveryDetail = () => {
        const selected = form.querySelector('input[name="discovery_channel"]:checked')?.value;
        const isRecommendation = selected === 'recomendacion';
        const isOther = selected === 'otro';
        const visible = isRecommendation || isOther;

        discoveryDetail.classList.toggle('hidden', ! visible);
        discoveryDetailInput.disabled = ! visible;
        discoveryDetailInput.required = visible;
        discoveryDetailLabel.textContent = isRecommendation ? '¿Quién te recomendó?' : 'Especifica';
    };
    form.querySelectorAll('input[name="discovery_channel"]').forEach(input => input.addEventListener('change', syncDiscoveryDetail));
    const syncMemberIdentification = () => {
        const name = [form.elements.first_name?.value, form.elements.last_name?.value].filter(Boolean).join(' ');
        const documentType = { dni: 'DNI', nie: 'NIE', passport: 'Pasaporte' }[form.elements.document_type?.value] || form.elements.document_type?.value || '';
        const documentNumber = form.elements.document_number?.value || '';
        completion.querySelector('[data-member-document-name]').textContent = name;
        completion.querySelector('[data-member-risk-name]').textContent = name;
        completion.querySelector('[data-member-document]').textContent = [documentType, documentNumber].filter(Boolean).join(' · ');
    };
    ['first_name', 'last_name', 'document_type', 'document_number'].forEach(name => form.elements[name]?.addEventListener('input', syncMemberIdentification));
    const signatureCanvas = form.querySelector('[data-member-signature]');
    const signatureInput = form.querySelector('[data-member-signature-input]');
    const signatureError = form.querySelector('[data-member-signature-error]');
    const finalizationError = document.createElement('div');
    finalizationError.className = 'hidden border border-red-400 bg-red-950 p-4 text-red-100';
    finalizationError.setAttribute('role', 'alert');
    completion.firstElementChild.after(finalizationError);
    const showFinalizationError = () => {
        const missing = [];
        if (!form.elements.informed_consent_accepted?.checked) missing.push('la aceptación del consentimiento informado');
        if (!form.elements.risk_assumption_accepted?.checked) missing.push('la aceptación de riesgos y condiciones');
        if (!signatureInput?.value) missing.push('la firma del miembro');
        if (missing.length === 0) {
            finalizationError.classList.add('hidden');
            return false;
        }

        finalizationError.textContent = `Para finalizar el alta, completa ${missing.join(', ')}.`;
        finalizationError.classList.remove('hidden');

        return true;
    };
    form.addEventListener('submit', event => {
        if (event.submitter?.name !== 'save_as_draft' && showFinalizationError()) event.preventDefault();
    });
    ['informed_consent_accepted', 'risk_assumption_accepted'].forEach(name => form.elements[name]?.addEventListener('change', showFinalizationError));
    if (signatureCanvas && signatureInput) {
        const context = signatureCanvas.getContext('2d');
        let drawing = false;
        let hasSignature = false;
        const point = event => {
            const bounds = signatureCanvas.getBoundingClientRect();
            return {
                x: (event.clientX - bounds.left) * (signatureCanvas.width / bounds.width),
                y: (event.clientY - bounds.top) * (signatureCanvas.height / bounds.height),
            };
        };
        const saveSignature = () => {
            signatureInput.value = hasSignature ? signatureCanvas.toDataURL('image/png') : '';
            signatureError?.classList.add('hidden');
            showFinalizationError();
        };
        context.lineWidth = 3;
        context.lineCap = 'round';
        context.strokeStyle = '#ffffff';
        signatureCanvas.addEventListener('pointerdown', event => {
            drawing = true;
            hasSignature = true;
            signatureCanvas.setPointerCapture(event.pointerId);
            const { x, y } = point(event);
            context.beginPath();
            context.moveTo(x, y);
        });
        signatureCanvas.addEventListener('pointermove', event => {
            if (!drawing) return;
            const { x, y } = point(event);
            context.lineTo(x, y);
            context.stroke();
        });
        signatureCanvas.addEventListener('pointerup', () => { drawing = false; saveSignature(); });
        signatureCanvas.addEventListener('pointercancel', () => { drawing = false; saveSignature(); });
        form.querySelector('[data-clear-member-signature]')?.addEventListener('click', () => {
            context.clearRect(0, 0, signatureCanvas.width, signatureCanvas.height);
            hasSignature = false;
            saveSignature();
        });
        form.addEventListener('submit', event => {
            if (event.submitter?.name !== 'save_as_draft' && !signatureInput.value) {
                event.preventDefault();
                signatureError?.classList.remove('hidden');
                signatureCanvas.focus();
            }
        });
    }
    updateFinalStep();
    syncMemberIdentification();
    syncDiscoveryDetail();
});
</script>
<script>document.addEventListener('DOMContentLoaded',()=>{const f=document.querySelector('[data-registration-wizard]');if(!f)return;let s=0;const total=4,steps=[...f.querySelectorAll('[data-step]')],indicators=[...f.querySelectorAll('[data-step-indicator]')],prev=f.querySelector('[data-previous]'),next=f.querySelector('[data-next]'),submit=f.querySelector('[data-submit]'),label=f.querySelector('[data-step-label]'),current=()=>steps.find(x=>+x.dataset.step===s);const render=()=>{steps.forEach(x=>x.classList.toggle('hidden',+x.dataset.step!==s));indicators.forEach(x=>x.classList.toggle('is-active',+x.dataset.stepIndicator===s));prev.disabled=s===0;next.classList.toggle('hidden',s===total);submit.classList.toggle('hidden',s!==total);label.textContent=s===0?'TIPO DE ALTA':`0${s} / 0${total}`;f.querySelector('[data-progress]').style.width=`${s/total*100}%`;window.scrollTo({top:0,behavior:'smooth'})};const validate=()=>{for(const x of current().querySelectorAll('input,select,textarea'))if(!x.disabled&&!x.checkValidity()){x.reportValidity();return false}return true};prev.addEventListener('click',()=>{s--;render()});next.addEventListener('click',()=>{if(validate()){s++;render()}});const sync=()=>f.querySelector('[data-caaf-alert]').classList.toggle('hidden',!f.querySelector('input[name^="caaf"][value="1"]:checked'));f.querySelectorAll('input[name^="caaf"],input[name="pregnancy"]').forEach(x=>x.addEventListener('change',sync));render();sync()});</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-registration-wizard]');
    if (!form) return;
    const current = form.querySelector('[data-current-exercise]');
    const past = form.querySelector('[data-past-exercise]');
    const reveal = (selector, visible) => {
        const block = form.querySelector(selector);
        block.classList.toggle('hidden', !visible);
        block.querySelectorAll('input, select, textarea').forEach(field => { field.disabled = !visible; });
    };
    const syncExercise = () => {
        const doesExercise = current.value === 'Si';
        const doesNotExercise = current.value === 'No';
        reveal('[data-exercise-current]', doesExercise);
        reveal('[data-exercise-past]', doesNotExercise);
        reveal('[data-past-exercise-details]', doesNotExercise && past.value === 'Si');
    };
    current.addEventListener('change', syncExercise);
    past.addEventListener('change', syncExercise);
    const syncTrainingRanges = () => form.querySelectorAll('[data-training-time]').forEach(check => {
        const range = form.querySelector(`[data-training-range="${check.dataset.trainingTime}"]`);
        range.classList.toggle('hidden', !check.checked);
        range.querySelectorAll('input').forEach(input => { input.disabled = !check.checked; });
    });
    form.querySelectorAll('[data-training-time]').forEach(check => check.addEventListener('change', syncTrainingRanges));
    const consentTemplates = new Map([...form.querySelectorAll('article.tfc-consent')].map(document => [document, document.innerHTML]));
    const syncConsentName = () => {
        const name = [form.elements.first_name?.value, form.elements.last_name?.value].filter(Boolean).join(' ').trim();
        const escapedName = document.createElement('span'); escapedName.textContent = name || '________________________________';
        consentTemplates.forEach((template, document) => { document.innerHTML = template.replaceAll('________________________________', escapedName.innerHTML); });
    };
    form.elements.first_name?.addEventListener('input', syncConsentName);
    form.elements.last_name?.addEventListener('input', syncConsentName);
    syncExercise();
    syncTrainingRanges();
    syncConsentName();
});
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-registration-wizard]');
    if (!form) return;
    let submissionLocked = false;
    let allowNativeSubmit = false;

    form.querySelector('[name="status"]')?.closest('label')?.remove();

    form.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        if (event.submitter?.name === 'save_as_draft') return;
        if (allowNativeSubmit) return;
        if (submissionLocked) {
            event.preventDefault();
            return;
        }
        if (!form.reportValidity()) {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        submissionLocked = true;
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        const overlay = form.querySelector('[data-submit-overlay]');
        form.querySelector('[data-registration-form-content]').setAttribute('inert', '');
        overlay.hidden = false;
        overlay.focus();
        const submit = form.querySelector('[data-submit]');
        submit.disabled = true;
        submit.textContent = 'Guardando miembro...';
        form.querySelector('[data-previous]').disabled = true;
        form.querySelector('[data-next]').disabled = true;

        requestAnimationFrame(() => requestAnimationFrame(() => {
            allowNativeSubmit = true;
            form.requestSubmit();
        }));
    });
});
</script>
</x-layouts::app>
