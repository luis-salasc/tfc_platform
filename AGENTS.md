# TFC Platform — instrucciones para agentes

## Contexto del proyecto

- Laravel 13, PHP >= 8.3, Blade, Livewire, Flux, Vite y Tailwind.
- MariaDB/MySQL es la base de datos principal.
- TFC Platform usa un único codebase y un deployment/base de datos independiente por cliente.
- Cada instalación tiene una `Organization` principal y puede tener varias `Location` (sedes).
- Esta es una arquitectura single-tenant por instalación, multisede y con codebase compartido; no es un SaaS multi-tenant compartiendo una base entre clientes.
- El esquema conserva `organization_id` y capacidad técnica residual para más de una organización. No elimines `organization_id`: funciona como raíz de configuración y límite defensivo/referencial.
- El sistema legacy es una fuente de importación, no el origen operativo principal.
- No existe actualmente una API pública estable.
- No introducir Docker, Redis, Horizon, WebSockets, Kubernetes ni workers permanentes sin aprobación explícita.

## Principios de trabajo

Antes de una tarea no trivial:

1. Inspecciona el código y el estado Git.
2. Explica el diagnóstico y el plan mínimo.
3. Identifica archivos que se modificarán.
4. Implementa solo el alcance autorizado.
5. Ejecuta verificaciones proporcionales al riesgo.
6. Informa exactamente qué cambió y qué queda pendiente.

No asumas requisitos no documentados ni conviertas deuda técnica en arquitectura nueva sin decisión expresa.

## Git

- No hagas `commit`, `push`, `merge`, `rebase`, `reset`, `restore`, `checkout` destructivo, `clean` ni `force push` sin autorización explícita.
- Comprueba `git status` antes de trabajos importantes.
- No descartes cambios existentes del usuario.
- No incluyas `.env`, secretos, credenciales, dumps, uploads privados, claves, `vendor/`, `node_modules/`, `public/build/` ni runtime generado.
- Usa staging explícito por archivo/hunk cuando se autorice un commit.

## Base de datos

- Conserva foreign keys, transacciones, aislamiento e idempotencia.
- No ejecutes migraciones destructivas ni modifiques datos sin autorización.
- No uses `migrate:fresh`, `db:wipe`, resets masivos o rollbacks globales como atajo.
- MariaDB es el motor de referencia. La suite usa SQLite en memoria y no reproduce todos los defaults, locks, modos SQL ni restricciones de MariaDB; las migraciones y consultas sensibles al motor deben validarse también contra MariaDB.

## Organización y autorización

`OrganizationMembership` aporta el contexto y rol organizativo; Spatie Permission aporta permisos materializados. `User::canWithinOrganization()` y `InteractsWithOrganization::requirePermission()`/`requireRole()` implementan la decisión efectiva actual. La convivencia de ambos mecanismos es deuda técnica conocida: no la resuelvas transversalmente dentro de una feature aislada.

Las consultas operativas deben filtrar explícitamente por `organization_id` y, cuando aplique, por `location_id`. El route model binding no filtra por organización por sí solo.

## Dominio vigente

- `User` es usuario interno del CRM; `Member` es cliente del centro y no una cuenta CRM reducida.
- La alta visible es `/miembros/crear` → `/miembros/altas` → `MemberPreRegistration` interno → revisión si procede → evidencia/firma → `MemberPreRegistrationFinalizer` → `Member` `REGISTERED`.
- `POST /miembros` no es una vía pública de creación y no debe reabrirse.
- `SessionLedger`, `SessionMovement` y `SessionSettlement` son la base de saldo y regularización; `sessions_remaining` es una proyección/cache operativa.
- Attendance permite búsqueda limitada y check-in de `REGISTERED`/`ACTIVE`; un check-in sin saldo genera regularización pendiente.
- Member Portal está fuera del MVP y deshabilitado por defecto. No lo habilites, no lo presentes como seguro y no corrijas su identidad legacy fuera de una tarea específica.

## Fechas y frontend

- Almacena timestamps en UTC cuando corresponda.
- La timezone de negocio pertenece a `Location` y debe ser IANA (`Europe/Madrid`, etc.).
- Usa Carbon/DateTimeZone para conversión por instante y DST.
- No uses offsets actuales o fijos para fechas históricas.
- Timeclock calcula límites UTC por día local y no depende de `CONVERT_TZ` ni de timezone tables de MariaDB.
- La UI española debe presentar fechas como `04/10/2026` y horas locales; ISO/UTC solo en campos o rutas donde sea técnicamente necesario.
- Mantén Blade, Livewire, Flux, Tailwind y Vite. No introduzcas otro framework frontend sin decisión.

## Seguridad y privacidad

- Mantén autenticación, autorización, CSRF, validación, rate limits y cabeceras.
- Aplica mínimo privilegio a datos personales y de salud.
- No expongas archivos privados ni registres secretos o datos sensibles innecesariamente.
- Señala explícitamente cambios que afecten pagos, salud, fotografías, identidad, Portal o aislamiento organizativo.

## Pagos y operaciones económicas

Usa transacciones, locks e idempotencia donde corresponda. Conserva auditoría de cambios y separa efectos secundarios como correo de la integridad de la operación. No ocultes errores económicos con excepciones genéricas.

## Tests y calidad

- Ejecuta primero tests focalizados y luego una suite proporcional.
- No modifiques tests solo para ocultar un fallo.
- Distingue siempre entre implementado y verificado.
- Pint, PHPStan y CI son comprobaciones independientes de los tests funcionales.

## Producción

La producción prevista usa Ubuntu 24.04, Nginx, PHP-FPM y MariaDB. No ejecutes comandos contra producción sin autorización explícita. Exige `APP_DEBUG=false`, HTTPS, document root en `public/`, secretos separados, backups externos, acceso MariaDB no público y restauración verificada.

## Bloqueadores conocidos

Siguen pendientes de resolución individual: fotografías públicas, datos sensibles demasiado amplios, bootstrap de producción, incoherencia de permisos, riesgos de concurrencia/integridad en pagos y asistencia, correo síncrono y despliegue/recuperación todavía no definidos.

## Regla fundamental

Ante el conflicto entre hacer más cambios y hacer el cambio mínimo correcto y comprobable, elige el cambio mínimo correcto y comprobable. Si aparece un problema adicional importante, infórmalo y déjalo fuera salvo que sea imprescindible para completar con seguridad la tarea autorizada.
