# The Fitness Club CRM — Instrucciones para agentes

## Contexto técnico

- Laravel 13 con PHP >= 8.3.
- Blade, Livewire 4 y Flux.
- Vite y Tailwind CSS.
- MariaDB/MySQL como base de datos principal.
- Monolito Laravel multiempresa; `organization_id` es la frontera de datos.
- Fortify gestiona la autenticación y Spatie Permission los permisos.
- Las sesiones y la cache usan actualmente la base de datos.
- El sistema legacy solo es una fuente de importación de datos.
- No existe una API pública actualmente.
- No hay Redis, Horizon, WebSockets ni una necesidad actual de workers permanentes.
- La producción futura será Ubuntu 24.04 con Nginx, PHP-FPM y MariaDB.
- Docker no es una decisión tomada: no debe introducirse sin aprobación explícita.

## Principios generales

Codex debe:

- Inspeccionar antes de modificar y entender el código existente antes de proponer una implementación.
- Preferir cambios pequeños, verificables, mantenibles, seguros y simples.
- Evitar refactors no solicitados, dependencias innecesarias y tecnologías nuevas sin justificación.
- Respetar los patrones existentes del proyecto.
- No asumir requisitos no documentados; debe indicar claramente cualquier suposición.

## Forma de trabajo

Antes de implementar una tarea no trivial:

1. Inspeccionar los archivos relevantes.
2. Explicar brevemente el diagnóstico.
3. Proponer un plan pequeño.
4. Identificar los archivos que pretende modificar.
5. Implementar solamente lo necesario.
6. Ejecutar las comprobaciones apropiadas.
7. Informar exactamente qué cambió y qué queda pendiente.

No debe ampliar silenciosamente el alcance de una tarea.

## Git

El repositorio es la fuente de verdad.

- Nunca hacer `commit`, `push`, `merge`, `rebase`, `reset`, `checkout` destructivo ni `force push` sin autorización explícita.
- Nunca descartar cambios existentes del usuario.
- Comprobar `git status` antes de trabajos importantes.
- Avisar si hay archivos modificados o sin seguimiento relacionados con la tarea.
- Mantener los cambios pequeños para que puedan revisarse.
- No incluir secretos, `.env`, credenciales, dumps de base de datos, claves privadas ni archivos sensibles en Git.

## Base de datos

- La base de datos principal es MariaDB/MySQL. No sustituirla por PostgreSQL.
- No asumir que SQLite reproduce correctamente el comportamiento de producción.
- Preservar foreign keys, transacciones e integridad de datos.
- Considerar concurrencia e idempotencia en operaciones económicas, asistencias y migraciones.
- No ejecutar migraciones destructivas ni modificar datos reales sin autorización.
- Tratar toda migración como un cambio especialmente sensible.

## Multiempresa

`organization_id` es una frontera de seguridad.

Todo código que acceda a datos de organizaciones debe revisar explícitamente:

- aislamiento entre organizaciones;
- autorización;
- queries correctamente acotadas;
- relaciones Eloquent;
- validaciones;
- tests de aislamiento cuando correspondan.

Nunca resolver un problema multiempresa eliminando filtros o comprobaciones de autorización.

## Seguridad y privacidad

El CRM contiene información personal y potencialmente sensible.

- Aplicar mínimo privilegio.
- Evitar exposición pública de archivos privados.
- No registrar secretos ni información sensible innecesariamente.
- Mantener CSRF, autenticación y autorización.
- Validar uploads y no confiar en datos enviados por el cliente.
- Usar las protecciones de Laravel cuando sean adecuadas.
- Señalar explícitamente cambios que afecten autenticación, permisos, pagos, datos médicos, fotografías o aislamiento multiempresa.

## Pagos y operaciones económicas

Los pagos y saldos son dominio crítico.

- Usar transacciones cuando corresponda.
- Considerar concurrencia e idempotencia.
- Evitar operaciones parciales.
- Separar efectos secundarios, como correo, de la integridad de la transacción.
- Nunca ocultar errores económicos mediante manejo genérico de excepciones.

## Laravel

Preferir:

- Convenciones estándar de Laravel.
- Form Requests para validación cuando resulte apropiado.
- Policies o Gates para centralizar autorización cuando ayuden.
- Eloquent y relaciones claras.
- Servicios solo cuando exista una responsabilidad real que extraer.
- Configuración mediante `config` y variables de entorno.
- Tests para comportamiento crítico.

Evitar:

- abstracciones prematuras;
- repositories genéricos sin necesidad;
- service layers vacíos;
- patrones enterprise innecesarios;
- lógica de negocio importante dentro de vistas Blade.

## Frontend

Mantener el stack actual:

- Blade;
- Livewire;
- Flux;
- Tailwind;
- Vite.

No introducir React, Vue, Angular, TypeScript ni otro framework frontend sin decisión explícita. Priorizar componentes reutilizables solo cuando exista repetición real, no anticipada.

## Tests y calidad

Para cada cambio, determinar qué comprobaciones son razonables.

El proyecto dispone de Pest/PHPUnit, Laravel Pint, Larastan/PHPStan y GitHub Actions.

- Ejecutar primero los tests relacionados cuando sea posible.
- Ampliar a una suite mayor cuando el riesgo lo justifique.
- No afirmar que algo funciona si no se ha comprobado.
- Diferenciar claramente entre «implementado» y «verificado».
- No modificar tests solo para hacerlos pasar si el comportamiento esperado es correcto.

## Producción

Producción es un entorno protegido.

- No asumir acceso al VPS.
- No ejecutar comandos contra producción salvo petición y autorización explícitas.
- Explicar previamente cualquier comando potencialmente destructivo.
- Favorecer procedimientos reproducibles y reversibles.
- Contemplar backup y rollback en cambios de riesgo.
- No habilitar `APP_DEBUG` en producción.
- No exponer MariaDB a Internet.
- Mantener el document root del servidor web apuntando a `public/`.

## Infraestructura

No introducir automáticamente:

- Docker;
- Redis;
- Horizon;
- Kubernetes;
- WebSockets;
- microservicios;
- colas;
- nuevos servicios persistentes.

Si alguno fuera necesario, explicar primero qué problema resuelve, por qué la arquitectura actual no basta, su coste operativo y la alternativa más sencilla.

## Roles de trabajo

Estos roles orientan el razonamiento, pero no autorizan trabajo paralelo ni cambios fuera del alcance de la tarea.

### ARCHITECT

Responsable de arquitectura, dominio, límites entre módulos, decisiones técnicas y coherencia global. No debe implementar cambios grandes antes de definir el problema.

### BACKEND

Responsable de Laravel, PHP, Eloquent, MariaDB, validación, servicios y lógica de negocio. Debe seguir las decisiones arquitectónicas existentes.

### REVIEWER

Responsable de revisar cambios, tests, regresiones, seguridad, aislamiento multiempresa y calidad. Debe intentar encontrar problemas, no limitarse a confirmar que la implementación parece correcta.

### DEVOPS

Responsable de despliegue, Ubuntu, Nginx, PHP-FPM, MariaDB, TLS, backups, logs y recuperación. Producción requiere autorización explícita antes de cualquier cambio.

## Bloqueadores de producción conocidos

Los siguientes puntos están documentados como bloqueadores conocidos y no deben considerarse resueltos hasta comprobarlos individualmente:

- Estado Git no reproducible.
- Incoherencia del modelo de permisos multiempresa.
- Fotografías de miembros almacenadas públicamente.
- Ausencia de bootstrap inicial de producción.
- Riesgos de integridad y concurrencia en pagos y asistencias.
- Envío síncrono de correo ligado a operaciones económicas.
- Despliegue y recuperación de producción todavía no definidos.

Esta lista es una advertencia, no autorización para corregir todos los puntos en una sola tarea.

## Regla fundamental

Cuando exista conflicto entre hacer más cambios y hacer el cambio mínimo correcto y comprobable, preferir el cambio mínimo correcto y comprobable.

Si una tarea revela un problema adicional importante, informarlo y dejarlo fuera del alcance salvo que sea imprescindible para completar de forma segura la tarea actual.
