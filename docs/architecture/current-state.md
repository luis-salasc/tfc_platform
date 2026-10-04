# Estado arquitectónico actual

## 1. Topología y límites del producto

TFC Platform utiliza un único codebase y un deployment independiente por cliente, con una base de datos independiente por instalación. Cada instalación tiene una `Organization` principal y puede tener varias `Location` (sedes). Las features se mantienen modulares sobre el mismo codebase; no se mantienen forks normales por cliente.

El esquema conserva `organization_id` y puede representar técnicamente más de una organización. Es una capacidad residual y un límite defensivo de consultas, no el modelo de producto SaaS compartido. `Organization` sigue siendo la raíz de configuración, identidad de empresa y relación con sedes.

## 2. Identidades

- `User` representa a un usuario interno del CRM.
- `Member` representa al cliente del centro y no es un usuario reducido del CRM.
- `OrganizationMembership` vincula usuarios internos con la organización y su rol.

## 3. Alta de miembros

El flujo visible actual es:

```text
/miembros/crear
  → /miembros/altas
  → MemberPreRegistration (concepto interno)
  → revisión si corresponde
  → evidencia, consentimientos y firma
  → finalización
  → Member REGISTERED
```

“Prealta” es una entidad interna de implementación. No debe presentarse como una superficie operativa independiente. `POST /miembros` no está expuesto como vía pública de creación; la creación válida pasa por `MemberPreRegistrationFinalizer`.

## 4. Lifecycle de Member

La finalización crea un `Member` en estado `REGISTERED`, con `sessions_remaining = 0`. El primer check-in válido puede pasar `REGISTERED` a `ACTIVE`. `INACTIVE` y `FROZEN` existen como estados y no pueden realizar check-in normal. Las transiciones adicionales deben seguir el código y sus tests, no esta documentación.

## 5. Sesiones, pagos y ledger

`SessionLedger` calcula el saldo a partir de `SessionMovement`. `SessionSettlement` registra regularizaciones de asistencias sin saldo. Una asistencia sin saldo queda marcada para regularización y puede regularizarse con pagos posteriores; el flujo actual utiliza movimientos y regularización FIFO donde corresponde. `sessions_remaining` se mantiene como proyección/cache operativa, no como libro de verdad independiente.

No están implementados todavía bonos con caducidad completa, extensiones auditadas por producto ni un sistema avanzado de reservas.

## 6. Attendance y Kiosk

La asistencia autenticada permite buscar por nombre, apellidos, documento o teléfono, devuelve como máximo diez resultados y solo permite `REGISTERED`/`ACTIVE`. El check-in es idempotente por día, consume un movimiento de sesión y crea una incidencia de regularización si no hay saldo.

El kiosco público identifica al miembro mediante QR y está limitado por organización activa, throttle y estados válidos. Todavía no existe una identidad robusta de dispositivo/sede; esa limitación es deuda MVP, no una capacidad futura ya resuelta.

## 7. Timeclock

Los eventos append-only son:

- `CLOCK_IN`
- `BREAK_START`
- `BREAK_END`
- `CLOCK_OUT`

Se admiten múltiples bloques y pausas. El historial y el detalle se agrupan por día local de `Location`; los eventos se almacenan en UTC y Carbon/IANA calcula límites y presentación local, incluido DST. Timeclock no depende de `CONVERT_TZ` ni de las tablas de timezone de MariaDB.

Timeclock Iteración 2 añade solicitudes `ADD`, `CORRECT` y `ANNUL`. Las originales permanecen intactas; las correcciones aprobadas producen eventos efectivos virtuales. Las solicitudes se aprueban o rechazan con auditoría, bloqueo transaccional y permisos. La auto-resolución está permitida cuando el usuario posee `timeclock.resolve_corrections`. También existe reporting y bandeja de pendientes.

## 8. Portal

Member Portal está fuera del MVP y deshabilitado por defecto. Las rutas y el código legacy se conservan detrás de `member.portal.enabled`; no debe tratarse como módulo disponible ni como superficie segura para producción.

## 9. Autorización actual

La autorización combina:

- `OrganizationMembership.role` para el contexto organizativo;
- Spatie Permission para permisos materializados;
- `User::canWithinOrganization()` para la decisión efectiva;
- `InteractsWithOrganization::requirePermission()` y `requireRole()` en controladores.

Esta convivencia es deuda técnica conocida. No debe confundirse con una arquitectura final de permisos ni resolverse de forma transversal dentro de una feature aislada. Los controladores deben mantener comprobaciones explícitas de organización porque el route model binding no filtra por organización por sí solo.
