# Plan de iteraciones y criterios de arquitectura

Este documento separa el estado implementado del trabajo pendiente. No convierte una intención de roadmap en una funcionalidad disponible.

## Principios vigentes

- Un deployment y una base de datos independiente por cliente.
- Una `Organization` raíz con múltiples `Location` posibles.
- Un único codebase compartido entre instalaciones, sin forks normales por cliente.
- Toda operación debe comprobar organización y, cuando aplique, sede.
- Los cambios económicos y de saldo deben ser auditables y transaccionales.
- La autorización, validación, CSRF, rate limits y despliegue seguro se aplican por capas.
- Las funciones desactivadas no deben dejar rutas operativas accesibles.

## Implementado

- Organización, sedes, usuarios internos y acceso autenticado.
- Alta guiada mediante `MemberPreRegistration`, revisión append-only y finalización idempotente a `Member`.
- Estados iniciales de Member y flujo de check-in.
- Miembros, pagos, incidencias, progreso y planes de entrenamiento en su alcance actual.
- `SessionLedger`, `SessionMovement`, `SessionSettlement` y regularización de asistencias sin saldo.
- Búsqueda manual de miembros para asistencia, aislada por organización y limitada a diez resultados.
- Kiosco QR público en su alcance actual.
- Timeclock Iteración 1: eventos append-only, pausas, historial, detalle, timezone local y presentación española.
- Timeclock Iteración 2: correcciones `ADD`/`CORRECT`/`ANNUL`, aprobación/rechazo, auditoría, reporting y pendientes.
- Member Portal deshabilitado por defecto; su código se conserva fuera del MVP.

## MVP pendiente

- Privacidad y almacenamiento privado de fotografías.
- Restricción por mínimo privilegio de datos sanitarios y sensibles.
- Endurecimiento del kiosco con identidad de dispositivo y sede.
- Validación funcional transversal contra MariaDB, concurrencia y aislamiento entre organizaciones.
- Bootstrap reproducible de producción, staging, backups y restauración verificada.
- Revisión de la convivencia entre `OrganizationMembership.role` y Spatie Permission.
- Definición operativa de reservas, bonos y caducidad si son necesarios para el MVP.

## Post-MVP

- Portal de miembros con identidad separada y superficie restringida.
- Bonos con caducidad, extensiones y consumo FIFO completo por bono.
- Reservas, capacidad, lista de espera y no-show.
- API privada versionada, scopes, OpenAPI e integraciones.
- Funcionamiento degradado del kiosco.
- Reporting agregado, comunicaciones con colas, retos y cartelería.

## Criterios de salida comunes

Una iteración se considera cerrada solo cuando sus tests relevantes, aislamiento organizativo, migraciones MariaDB cuando corresponda y procedimiento de despliegue están verificados. Los riesgos de producción deben permanecer explícitos hasta su resolución individual.
