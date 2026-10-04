# Auditoría del alta de Member: evidencia y privacidad

> **ESTADO: AUDITORÍA HISTÓRICA / SUPERADA**
>
> Este documento describe la auditoría del flujo anterior a la separación de `MemberPreRegistration`. El flujo actual está documentado en [current-state.md](architecture/current-state.md): la alta visible pasa por `/miembros/altas`, revisión cuando corresponde y `MemberPreRegistrationFinalizer`. No utilizar este documento como especificación vigente ni reabrir el POST directo de creación de Member.

Fecha de auditoría: 3 de octubre de 2026. Alcance: inspección estática de `resources/views/members/form.blade.php`, `MemberController`, `Member`, migraciones, vistas de ficha, rutas, correo y tests relacionados. No se han inspeccionado ni modificado datos reales.

## 1. Flujo actual, paso por paso

El formulario es un único `POST` HTML a `miembros.store` (o `PUT` a `miembros.update` al editar) con un wizard cliente de cinco índices: tipo de alta (`data-step=0`) y cuatro hojas etiquetadas 01–04. El alta manual solo la pueden iniciar Owner, Admin, Trainer o Receptionist. Al crear, el controlador fuerza `status=registered`, `started_on=hoy`, `sessions_remaining=0`, organización, localización y usuario creador.

| Paso | Contenido | Persistencia principal | Observaciones |
| --- | --- | --- | --- |
| 0. Tipo de alta | Presencial / online | `members.client_type` | La interfaz exige selección; servidor no la exige. |
| 1. Acerca de ti | Identificación, contacto, captación y objetivos | Columnas de `members` e `members.intake` (JSON) | Contiene datos personales y preferencias. |
| 2. Entrevista | Hábitos, barreras, preferencias y lesiones | `members.intake` (JSON) | Incluye texto que puede revelar salud. |
| 3. Salud | C-AAF, embarazo y notas médicas | `members.intake` (JSON) | Incluye datos de salud y una alerta de navegador. |
| 4. Consentimiento | Texto, fecha, tres checks y envío opcional por correo | Columnas de `members` e `intake` | Mezcla aceptación del Member con revisión del entrenador. |

Al enviar, `MemberController::validated()` copia los campos de entrevista a `intake`, fija siempre `intake.consent_version = '2026-09-11'` y `intake.consent_recorded_at = now()->toIso8601String()`, y calcula `intake.caaf_requires_review`. Los tres valores se recalculan también en una edición posterior.

## 2. Inventario completo de campos renderizados

Abreviaturas: **HTML** es la obligatoriedad del atributo `required`; **backend** es la validación real en `MemberController`; **destino** indica columna o clave JSON; **edición** se refiere a la pantalla `miembros.edit`, accesible a Owner/Admin/Trainer.

### Paso 0 — Tipo de alta

| Label visible | `name` / tipo | HTML | Backend | Destino | Edición | Finalidad aparente |
| --- | --- | --- | --- | --- | --- | --- |
| Tipo de miembro: “Presencial: la ficha se completa en el centro.” | `client_type`, radio, `presencial` | Obligatorio | `nullable|string|max:60` | `members.client_type` | Sí | Canal de alta. |
| Tipo de miembro: “Online: el cliente completará la ficha desde una invitación segura.” | `client_type`, radio, `online` | Obligatorio | Igual | `members.client_type` | Sí | Canal de alta previsto. |

Texto adicional: “no se admite un alta online anónima. El centro crea una prealta e invita al email confirmado; el enlace debe caducar y ser de un solo uso.” No se registra como evidencia ni se impone desde este formulario.

### Paso 1 — Acerca de ti

| Label visible | `name` / tipo | HTML | Backend | Destino | Edición | Finalidad aparente |
| --- | --- | --- | --- | --- | --- | --- |
| Nombre | `first_name`, text | Obligatorio | `required|string|max:120` | `members.first_name` | Sí | Identificación. |
| Apellidos | `last_name`, text | Obligatorio | `required|string|max:160` | `members.last_name` | Sí | Identificación. |
| DNI / NIE | `national_id`, text | Opcional | `nullable|string|max:30` | `members.national_id` | Sí | Identificación; expresamente fuera de la próxima iteración. |
| Localidad | `city`, text | Opcional | `nullable|string|max:120` | `members.city` | Sí | Contacto. |
| Móvil | `phone`, tel | Obligatorio | `nullable|string|max:40` | `members.phone` | Sí | Contacto. |
| ¿A qué te dedicas? | `occupation`, text | Opcional | `nullable|string|max:160` | `intake.occupation` | Sí | Contexto/entrevista. |
| Fecha de nacimiento | `birth_date`, date | Obligatorio | `nullable|date|before:today` | `members.birth_date` | Sí | Identificación/edad. |
| Sexo | `sex`, select: Masculino, Femenino, Prefiero no decirlo | Obligatorio | `nullable|string|max:30` | `intake.sex` | Sí | Perfil/entrevista. |
| Email | `email`, email | Obligatorio | `nullable|email|max:150` | `members.email` | Sí | Contacto y posible envío de documentos. |
| Dirección | `address`, text | Opcional | `nullable|string|max:255` | `members.address` | Sí | Contacto. |
| Alias público | `public_alias`, text | Opcional | `nullable|string|max:40`, único por organización | `members.public_alias` | Sí | Kiosco y retos. |
| ¿Cómo nos has conocido? | `discovery_channel`, radio | Opcional | `nullable|string|max:80` | `intake.discovery_channel` | Sí | Captación. Valores: folleto_calle, publicidad_buzoneo, busqueda_internet, Facebook, Instagram, Twitter, recomendacion, otro. |
| Detalle de recomendación u otro origen | `discovery_detail`, text | Opcional | `nullable|string|max:255` | `intake.discovery_detail` | Sí | Captación. |
| ¿Qué resultados quieres tener? | `objectives[]`, checkbox múltiple | Opcional | `nullable|array`; elementos `string|max:100` | `intake.objectives` | Sí | Objetivos de entrenamiento. |
| Otro resultado | `other_objective`, text | Opcional | `nullable|string|max:255` | `intake.other_objective` | Sí | Objetivo libre. |
| Quiero conseguirlo antes de | `goal_date`, date | Opcional | `nullable|date` | `intake.goal_date` | Sí | Horizonte del objetivo. |
| ¿Cuándo te gustaría empezar? | `desired_start_on`, date | Opcional | `nullable|date` | `intake.desired_start_on` | Sí | Planificación. |

Los valores visibles de `objectives[]` son: mejorar_salud, subir_peso, bajar_peso, disminuir_volumen, tonificar, mejorar_postura, aumentar_resistencia, reducir_grasa, sentirse_mejor, aumentar_masa_muscular, rehabilitacion, aliviar_dolores, fortalecer, rendimiento_laboral, dormir_mejor, eliminar_celulitis, reducir_estres y rendimiento_deportivo.

### Paso 2 — Entrevista de inicio

| Label visible | `name` / tipo | HTML | Backend | Destino | Edición | Finalidad aparente |
| --- | --- | --- | --- | --- | --- | --- |
| ¿Cuánto tiempo llevas pensando en empezar? | `thinking_about_start`, text | Opcional | `nullable|string|max:255` | `intake.thinking_about_start` | Sí | Motivación. |
| Importancia de tus objetivos (1-10) | `objective_importance`, number | Opcional | `nullable|integer|between:1,10` | `intake.objective_importance` | Sí | Motivación. |
| Aspecto físico percibido (1-10) | `body_image_rating`, number | Opcional | `nullable|integer|between:1,10` | `intake.body_image_rating` | Sí | Autopercepción. |
| ¿Haces ejercicio regularmente? | `current_exercise`, select Sí/No | Opcional | `nullable|string|max:10` | `intake.current_exercise` | Sí | Hábitos. |
| ¿Qué tipo de ejercicio? | `current_exercise_type`, textarea | Opcional; habilitado solo si anterior=Sí | `nullable|string|max:2000` | `intake.current_exercise_type` | Sí | Hábitos. |
| ¿Cuántas veces por semana? | `current_exercise_frequency`, number | Opcional; condicional | `nullable|integer|min:1|max:7` | `intake.current_exercise_frequency` | Sí | Hábitos. |
| ¿Cuánto tiempo llevas haciéndolo de forma regular? | `current_exercise_duration`, text | Opcional; condicional | `nullable|string|max:100` | `intake.current_exercise_duration` | Sí | Hábitos. |
| ¿Estás obteniendo resultados? | `current_exercise_results`, select Sí/No | Opcional; condicional | `nullable|string|max:10` | `intake.current_exercise_results` | Sí | Hábitos. |
| ¿Has hecho alguna vez ejercicio de forma regular? | `past_exercise`, select Sí/No | Opcional; habilitado si ejercicio actual=No | `nullable|string|max:10` | `intake.past_exercise` | Sí | Hábitos previos. |
| Si has contestado sí, ¿qué hacías? | `past_exercise_type`, textarea | Opcional; condicional | `nullable|string|max:2000` | `intake.past_exercise_type` | Sí | Hábitos previos. |
| ¿Cuántas veces por semana? | `past_exercise_frequency`, number | Opcional; condicional | `nullable|integer|min:1|max:7` | `intake.past_exercise_frequency` | Sí | Hábitos previos. |
| ¿Hace cuánto tiempo? | `past_exercise_since`, text | Opcional; condicional | `nullable|string|max:100` | `intake.past_exercise_since` | Sí | Hábitos previos. |
| ¿Durante cuánto tiempo lo hiciste? | `past_exercise_duration`, text | Opcional; condicional | `nullable|string|max:100` | `intake.past_exercise_duration` | Sí | Hábitos previos. |
| ¿Obtuviste los resultados que querías? | `past_exercise_results`, select Sí/No | Opcional; condicional | `nullable|string|max:10` | `intake.past_exercise_results` | Sí | Hábitos previos. |
| Si los conseguiste, ¿por qué lo dejaste? | `past_exercise_reason`, textarea | Opcional; condicional | `nullable|string|max:2000` | `intake.past_exercise_reason` | Sí | Hábitos previos. |
| ¿Por qué no has empezado antes? | `barriers[]`, checkbox múltiple | Opcional | `nullable|array`; elementos `string|max:100` | `intake.barriers` | Sí | Barreras. Valores: compromisos_familiares, falta_motivacion, falta_tiempo, dejadez, lesion_salud, trabajo. |
| ¿Sigue siendo un problema? | `barriers_still_active`, radio Sí/No | Opcional | `nullable|boolean` | `intake.barriers_still_active` | Sí | Contexto de barreras. |
| ¿En qué momento prefieres entrenar? | `preferred_training_times[]`, checkbox Mañana/Tarde | Opcional | `nullable|array`; elementos `in:m,t` | `intake.preferred_training_times` | Sí | Planificación. |
| Desde / Hasta para Mañana | `preferred_training_ranges[m][from|to]`, time | Opcional; habilitado si Mañana | `nullable|date_format:H:i` | `intake.preferred_training_ranges.m` | Sí | Planificación. |
| Desde / Hasta para Tarde | `preferred_training_ranges[t][from|to]`, time | Opcional; habilitado si Tarde | `nullable|date_format:H:i` | `intake.preferred_training_ranges.t` | Sí | Planificación. |
| Lesiones o zonas afectadas: describe zona, intensidad y limitaciones conocidas | `injury_notes`, textarea | Opcional | `nullable|string|max:5000` | `intake.injury_notes` | Sí | Información potencialmente sanitaria/preventiva. |

### Paso 3 — Cuestionario de aptitud y salud

| Label visible | `name` / tipo | HTML | Backend | Destino | Edición | Finalidad aparente |
| --- | --- | --- | --- | --- | --- | --- |
| Siete preguntas C-AAF detalladas en la sección 3 | `caaf[clave]`, radio Sí/No | Obligatorio, cada pareja | `nullable|array`; cada respuesta `boolean` | `intake.caaf.clave` | Sí | Procedimiento preventivo previo al ejercicio. |
| Información médica adicional, dolores crónicos o cirugías recientes | `medical_notes`, textarea | Opcional | `nullable|string|max:5000` | `intake.medical_notes` | Sí | Información sanitaria libre. |
| ¿Estás embarazada o crees que podrías estarlo? | `pregnancy`, radio Sí/No/No aplica | Opcional | `nullable|boolean` | `intake.pregnancy` | Sí | Información sanitaria/preventiva. |

### Paso 4 — Consentimiento y finalización

| Label visible | `name` / tipo | HTML | Backend | Destino | Edición | Finalidad aparente |
| --- | --- | --- | --- | --- | --- | --- |
| Fecha de aceptación | `consent_date`, date | Obligatorio; se precarga con hoy | `nullable|date` | `members.consent_date` | Sí | Fecha declarada de aceptación. |
| Estado | `status`, select | No obligatorio | **No está en las reglas de validación** | Ninguno por este flujo | No efectivo | El JavaScript elimina el label; en alta el controlador fuerza `registered`. |
| El miembro acepta el consentimiento informado. | `informed_consent_accepted`, checkbox | Obligatorio | `boolean` | `members.informed_consent_accepted` | Sí | Aceptación atribuida al Member. |
| El miembro acepta la asunción de riesgos y las condiciones del servicio. | `risk_assumption_accepted`, checkbox | Obligatorio | `boolean` | `members.risk_assumption_accepted` | Sí | Aceptación atribuida al Member. |
| He revisado la ficha con el miembro y registraré cualquier incidencia que requiera seguimiento. | `trainer_intake_reviewed`, checkbox | Obligatorio | `boolean` | `intake.trainer_intake_reviewed` | Sí | Acción atribuida al entrenador/empleado. |
| Enviar una copia de estos documentos al correo del miembro. | `send_consent_by_email`, checkbox | Opcional | `boolean` | No se persiste | Dispara un correo solo si existe email. |

## 3. Cuestionario exacto, advertencias y comportamiento

Las siete preguntas C-AAF se generan desde un mapa Blade. Todas tienen radios requeridos (`value="1"` para Sí y `value="0"` para No):

| Clave almacenada | Pregunta exacta | Sí | No | Texto libre asociado |
| --- | --- | --- | --- | --- |
| `heart_condition` | ¿Te ha dicho el médico que tienes una enfermedad de corazón y que debes hacer actividad solo bajo supervisión? | Activa la alerta C-AAF y hace `caaf_requires_review=true`. No bloquea el avance ni el envío. | No alerta por esa respuesta. | `medical_notes` general. |
| `chest_activity` | ¿Notas dolor en el pecho durante actividad física? | Igual. | Igual. | `medical_notes` general. |
| `chest_rest` | ¿Has notado dolor en el pecho en reposo durante el último mes? | Igual. | Igual. | `medical_notes` general. |
| `dizziness` | ¿Has perdido la conciencia o el equilibrio tras una sensación de mareo? | Igual. | Igual. | `medical_notes` general. |
| `bones_joints` | ¿Tienes un problema en huesos o articulaciones que podría empeorar con actividad? | Igual. | Igual. | `medical_notes` general; `injury_notes` está en el paso anterior. |
| `medication` | ¿Te han prescrito medicación para tensión arterial o corazón? | Igual. | Igual. | `medical_notes` general. |
| `other_reason` | ¿Hay otra razón, propia o indicada por un médico, que te impida ejercitarte sin supervisión? | Igual. | Igual. | `medical_notes` general. |

La alerta visible exacta es: **“Hay una o más respuestas que requieren revisión del entrenador antes de aumentar la actividad.”** Se muestra en cliente cuando alguna respuesta C-AAF es Sí; no hay bloqueo de paso, bloqueo de alta, ni workflow de derivación médica.

El controlador calcula y persiste `intake.caaf_requires_review=true` cuando alguna respuesta C-AAF es Sí **o** cuando `pregnancy` es Sí. No persiste si la alerta se mostró, cuándo se mostró, qué usuario la vio, ni una confirmación de lectura. La alerta JavaScript solo consulta C-AAF, no embarazo; por tanto un Sí en embarazo marca la clave de revisión en servidor pero no produce esa alerta en cliente.

La pregunta adicional exacta es: **“¿Estás embarazada o crees que podrías estarlo?”** con Sí, No y “No aplica / prefiero no responder”. No es requerida. Sí marca la clave derivada de revisión; No y no respuesta no. No tiene advertencia visible propia ni bloqueo.

## 4. Consentimientos actuales y texto visible

El primer bloque visible, bajo “Consentimiento informado y asunción de riesgos”, contiene literalmente:

> Las pruebas, test y cuestionarios permiten valorar la condición física general y orientar el entrenamiento de fuerza y resistencia muscular.
>
> La información será confidencial y se usará exclusivamente para seguimiento profesional. El miembro declara haber recibido explicación, haber podido plantear dudas y comprender los riesgos habituales de la actividad física.
>
> El entrenamiento puede realizarse en el centro o, cuando se acuerde expresamente, en el domicilio u otro lugar pactado.

Después se muestra un segundo artículo, cuyo texto visible completo es:

> **INFORME CONSENTIMIENTO INFORMADO**
>
> Las pruebas, test y cuestionarios realizados para evaluar su condición física permitirán obtener información sobre su estado general de salud.
>
> Los entrenamientos a realizar son de fuerza y resistencia muscular. Usted podrá experimentar fatiga durante la realización de dichas pruebas y/o al finalizar las mismas.
>
> Al firmar este documento usted manifiesta explícitamente que se le han explicado y ha entendido la descripción de las pruebas a realizar y sus posibles complicaciones. Además, usted indica que cualquier duda que haya podido surgir con el proceso de evaluación y sus potenciales riesgos ha sido respondida con claridad, quedando satisfecho con las explicaciones aportadas.
>
> La información obtenida como consecuencia de dichas pruebas será considerada como confidencial, pudiéndose utilizar únicamente con fines científicos, salvaguardando en cualquier caso su identidad. Para ello será necesario su expreso consentimiento mediante autorización por escrito.
>
> Al firmar el presente documento usted acepta la completa responsabilidad de su propia salud, y reconoce que ha sido informado y ha entendido que esta responsabilidad no es asumida por los responsables de su programa de ejercicio físico.
>
> **INFORME DE ASUNCIÓN DE RIESGOS**
>
> Yo, Don/Doña **[nombre y apellidos introducidos, o línea en blanco]**, asumo, acepto y eximo de cualquier responsabilidad al entrenador deportivo por las lesiones o accidentes que pudieran resultar de mi participación en el citado programa.
>
> Entiendo los procedimientos aplicados y manifiesto que he tenido la oportunidad de discutir mis necesidades específicas en relación con mi participación en el citado programa; como resultado de ello, acepto las condiciones expuestas para la participación en el citado programa.
>
> Manifiesto conocer la existencia de riesgos asociados a la práctica de actividad física y estoy de acuerdo en aceptar las responsabilidades derivadas de mi participación y del uso de las instalaciones y/o equipamiento específico o genérico.
>
> **ACUERDO:**
>
> Yo, Don/Doña **[nombre y apellidos introducidos, o línea en blanco]**, he solicitado este asesoramiento, mediante el cual autorizo al mencionado entrenador personal a dirigir mi entrenamiento en mi propio domicilio o en el lugar que de común acuerdo concertemos.

Es contenido de interfaz estático; no se guarda una copia del texto ni un hash/snapshot por aceptación.

No hay aviso de privacidad separado, responsable, base jurídica, plazo de conservación, derechos, destinatarios ni versión de privacidad en este formulario. “La información será confidencial” es la única mención de privacidad localizada.

La casilla `trainer_intake_reviewed` tiene redacción de empleado, no de Member, pero está colocada junto a las dos aceptaciones del Member y es requerida en el mismo submit. No identifica al revisor ni fecha/hora de revisión; `created_by_user_id` identifica al creador de la ficha, no necesariamente a quien revisó.

## 5. Fecha de aceptación actual

`consent_date` es un `<input type="date">` requerido en la interfaz, precargado con la fecha local de renderizado (`now()->format('Y-m-d')`) y totalmente editable tanto en alta como en edición. Solo se guarda como fecha, no hora. El backend permite ausencia (`nullable|date`).

Además de esa fecha, cada alta o edición guarda `intake.consent_recorded_at` con la hora del servidor y `intake.consent_version='2026-09-11'`. No se vinculan condicionalmente a las casillas de aceptación, se sobrescriben en cada guardado y no prueban el instante inicial de aceptación. No hay firma, identidad del firmante, IP, user-agent, evidencia de lectura, texto inmutable, ni control de versiones del documento.

## 6. Revisión del entrenador actual

La única señal es `intake.trainer_intake_reviewed`, booleano procedente de “He revisado la ficha con el miembro y registraré cualquier incidencia que requiera seguimiento.” Se presenta como obligatoria pero la validación de servidor solo comprueba que, si llega, sea booleana; no exige valor verdadero.

No hay estado, fecha, revisor, decisión, observaciones, incidencia automática ni separación de la aceptación del Member. `caaf_requires_review` es una bandera derivada, no una revisión realizada. Las incidencias se crean desde otro módulo y no se crean automáticamente por esta bandera.

## 7. Persistencia, edición posterior y visibilidad CRM

### Persistencia

- Columnas de `members`: identificación, contacto, `client_type`, `informed_consent_accepted`, `risk_assumption_accepted`, `consent_date`, organización, localización, creador, estado y fechas de registro.
- JSON `members.intake`: entrevista, barreras, preferencias, `injury_notes`, `medical_notes`, `pregnancy`, `caaf`, `trainer_intake_reviewed`, `caaf_requires_review`, versión/timestamp de consentimiento y, por validación heredada aunque no haya controles actuales, fotos.
- JSON `members.initial_metrics`: el controlador acepta métricas y fotos por POST, pero no hay controles correspondientes en el formulario actual de alta. El test de operaciones las envía programáticamente.
- Correo opcional: no guarda una bandera de envío ni copia del mensaje. Si se marca `send_consent_by_email` y hay email, envía un texto breve que dice que se registraron documentos en `consent_date` (o, si falta, hoy). No adjunta el texto completo, versión ni evidencia.

### Edición

Owner, Admin y Trainer pueden abrir el mismo formulario de edición y modificar los mismos controles. El merge de `intake` conserva claves antiguas no enviadas, pero las aceptaciones principales se recalculan con `$request->boolean()`: una edición sin una casilla marcada puede dejarlas en falso. No hay historial, auditoría de cambio, ni protección de evidencia de aceptación frente a edición.

### Visibilidad

- La ficha `miembros.show` se autoriza a Owner, Admin, Trainer, Receptionist y Staff de la misma organización. Por tanto todos ellos pueden abrir los paneles “Ficha y documentos”, incluyendo “Salud” y “Consentimientos”.
- El panel Salud muestra `medical_notes`, embarazo y las siete respuestas C-AAF. No muestra `injury_notes`, aunque este permanece en JSON.
- El panel Legal muestra fecha de aceptación y los dos booleanos de consentimiento; no muestra versión, timestamp, alerta, `caaf_requires_review` ni revisión de entrenador.
- El panel KYC muestra captación y parte de hábitos; resumen muestra objetivos y algunas métricas iniciales.
- La edición de datos sensibles queda más restringida: Owner/Admin/Trainer.
- Las rutas públicas de kiosco y carnet no cargan la ficha sanitaria. No se encontró exposición de estos datos en los correos inspeccionados.

Existe código de invitación de acceso para Member que crea/usa un `User` y `MemberPortalAccess`, pero no hay rutas de una superficie de Member restringida revisadas en este flujo. La activación redirige al login. Es una incoherencia de producto a revisar antes de usarlo para recogida de evidencia; no debe asumirse que equivale a una superficie de tablet/móvil aislada del CRM.

## 8. Datos de salud, uso operativo y evidencia

### Información que puede ser dato de salud o especialmente sensible

- Las siete respuestas C-AAF.
- `medical_notes`: dolores crónicos, cirugías recientes e información médica adicional.
- `injury_notes`: lesiones, zonas, intensidad y limitaciones.
- `pregnancy`.
- Las barreras `lesion_salud` y objetivos como rehabilitación/aliviar dolores, según su contenido y contexto.
- Métricas corporales iniciales y de progreso: peso, grasa, masa muscular, agua, grasa visceral, edad metabólica y perímetros.
- Fotografías corporales, si llegan por el endpoint aunque no existan controles actuales en el alta.

### Información usada operativamente después del alta

- Identidad, email, teléfono, estado y sesiones: listado, pagos, asistencias, kiosco/carnet y operación del CRM.
- Objetivos, hábitos, disponibilidad y métricas: se muestran en resumen/ficha y pueden orientar entrenamiento.
- `caaf`/embarazo/notas: visibles en Salud; no se encontró automatismo operativo salvo `caaf_requires_review` almacenado.
- Consentimientos: visibles en Legal y fecha usada como texto en el correo opcional.

### Información que parece ser principalmente evidencia

- Booleanos `informed_consent_accepted` y `risk_assumption_accepted`.
- `consent_date`, `intake.consent_version` y `intake.consent_recorded_at`.
- `trainer_intake_reviewed` y `caaf_requires_review`.

## 9. Evidencia conservada y evidencia ausente

### Actualmente conservada

- Respuestas C-AAF, embarazo y notas, cuando se envían.
- Dos booleanos de aceptación, una fecha editable, versión fija y timestamp de último guardado.
- Booleano de revisión declarada por el entrenador y bandera derivada de necesidad de revisión.
- `created_at`, `updated_at` y `created_by_user_id` de Member.
- Texto de correo no se almacena; solo hay el efecto de envío síncrono.

### Actualmente no conservada

- Copia inmutable, hash o versión verificable del texto exacto mostrado al Member.
- Evidencia de qué preguntas vio realmente, orden/idioma, interfaz usada o advertencia mostrada.
- Fecha/hora automática e inmutable de aceptación; identidad/rol del aceptante; firma o equivalente.
- Identidad y timestamp de revisión profesional, resultado, comentarios o acciones posteriores.
- Confirmación de que se recomendó consulta médica, se detuvo el proceso o se informó de una advertencia.
- Evidencia de envío/entrega/lectura del correo de documentos.
- Historial de ediciones y valores anteriores para consentimientos, salud o revisión.

## 10. Contradicciones y riesgos detectados

1. La interfaz afirma que C-AAF positivo “requiere revisión profesional”, pero la alerta dice “revisión del entrenador” y no existe una acción de revisión persistida.
2. El contexto de negocio indica que TFC no diagnostica; el texto actual dice que los test permiten “valorar la condición física general”, y no delimita claramente procedimiento preventivo frente a evaluación/diagnóstico.
3. `caaf_requires_review` se marca por embarazo, pero el navegador no muestra la alerta C-AAF por embarazo.
4. La fecha de aceptación es elegida manualmente y editable; el timestamp técnico se sobrescribe al editar. Ninguno demuestra cuándo se formalizó la aceptación.
5. La versión de consentimiento es fija y el texto no se archiva; un cambio de texto futuro no puede reconstruir qué se aceptó.
6. La revisión del entrenador y la aceptación del Member están mezcladas en un único submit y no distinguen actor, fecha ni propósito.
7. HTML exige teléfono, fecha de nacimiento, sexo, email, tipo de cliente, C-AAF, fecha y tres checks, mientras el backend solo exige nombre y apellidos. Un cliente HTTP puede guardar información incompleta y aceptaciones falsas/ausentes.
8. La interfaz ofrece Estado, pero JavaScript lo elimina; alta fuerza `registered` y la validación no permite persistir ese campo desde edición.
9. Datos sanitarios JSON son visibles en la ficha a Receptionist y Staff, no solo a entrenadores/administradores.
10. El formulario permite editar retrospectivamente respuestas y aceptaciones sin auditoría; las claves JSON antiguas pueden mantenerse por merge.
11. El correo dice que envía una copia de documentos, pero solo envía un resumen breve y no conserva evidencia del envío.
12. El controlador acepta métricas y fotos que no tienen controles en el formulario actual; las fotos se almacenan en disco público conforme a la implementación existente.

## 11. Preguntas de negocio a decidir antes de implementar

1. ¿Qué actor completa cada parte: Member, empleado, entrenador, o una combinación con pasos separados?
2. ¿Qué respuestas deben solo generar advertencia y cuáles deben impedir continuar hasta una acción administrativa, sin constituir diagnóstico?
3. ¿Qué debe significar exactamente “revisión”: lectura, conversación, recomendación de consulta médica, limitación temporal, cierre, o creación de incidencia?
4. ¿Quién puede ver cada categoría de información de salud y quién puede editarla, por rol y organización?
5. ¿Qué evidencia debe ser inmutable y qué correcciones posteriores requieren una nueva versión/evento en vez de editar el registro original?
6. ¿Cuál es el texto definitivo de procedimiento preventivo, consentimiento, aviso de privacidad y asunción de riesgos, y quién aprueba cada versión?
7. ¿Cuándo debe fijarse automáticamente la aceptación: check final, firma futura, envío desde tablet, o una acción de empleado confirmada?
8. ¿Qué fecha/hora, zona horaria, actor y contexto técnico deben conservarse para la evidencia, respetando proporcionalidad y privacidad?
9. ¿Debe guardarse evidencia de advertencia presentada y de la respuesta/acción posterior? ¿Durante cuánto tiempo?
10. ¿La futura superficie de tablet/móvil será anónima con token de un solo uso, autenticada de forma limitada, o asistida por empleado? ¿Qué rutas y datos podrá acceder?
11. ¿Qué política de minimización, retención, rectificación y acceso aplica a notas sanitarias, métricas y fotografías?
12. ¿Debe continuar existiendo el envío de correo, qué contenido exacto debe incluir y qué evidencia de entrega es necesaria?

## 12. Fuentes revisadas

- `resources/views/members/form.blade.php`
- `app/Http/Controllers/MemberController.php`
- `app/Models/Member.php`
- `database/migrations/2026_09_02_130000_create_club_operations_tables.php`
- `resources/views/members/show.blade.php`
- `routes/web.php`
- `app/Http/Controllers/MemberPortalInvitationController.php`
- `app/Http/Controllers/ActivateMemberPortalController.php`
- `tests/Feature/ClubOperationsTest.php` y `tests/Feature/MemberRegistrationIterationATest.php`
