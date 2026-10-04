# TFC Platform

CRM y plataforma operativa para centros privados de entrenamiento.

## Estado del proyecto

El proyecto está en desarrollo y preparación de MVP. El código actual es una base operativa, no una declaración de que todos los módulos estén listos para producción.

## Arquitectura

- Un único codebase Laravel.
- Una instalación y una base de datos independiente por cliente.
- Una `Organization` principal por instalación.
- Una o varias `Location` (sedes) dentro de esa organización.
- Features modulares sobre el mismo codebase, sin forks normales por cliente.

El esquema conserva `organization_id` como contexto defensivo y relacional. Esa capacidad técnica no convierte el producto objetivo en un SaaS multi-tenant compartido.

## Módulos implementados

- Alta guiada de miembros mediante pre-registro interno, revisión cuando corresponde, evidencia y finalización a `Member`.
- Miembros y ficha operativa.
- Pagos, sesiones y `SessionLedger`, incluidos consumos y regularización de asistencias sin saldo.
- Asistencia manual con búsqueda limitada de miembros.
- Kiosco público por QR en su estado actual; todavía requiere endurecimiento de identidad de dispositivo y sede.
- Progreso y planes de entrenamiento internos.
- Timeclock: fichajes, pausas, historial, reporting y correcciones `ADD`, `CORRECT` y `ANNUL`.

Member Portal está fuera del MVP y deshabilitado por defecto (`MEMBER_PORTAL_ENABLED=false`). Su código legacy se conserva para una decisión posterior.

## Requisitos

- PHP `^8.3`.
- Laravel `^13.17`.
- MariaDB/MySQL como base principal.
- Node.js/npm para Vite, Tailwind y assets frontend.
- Composer.

Las versiones exactas de las dependencias están en `composer.json`, `composer.lock` y `package.json`.

## Instalación local

En una instalación local con MariaDB activa:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Configura en `.env` la conexión MariaDB local (`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) y ejecuta:

```powershell
php artisan migrate
npm ci
npm run build
php artisan serve
```

Para datos de desarrollo puede utilizarse `DevelopmentSeeder`, proporcionando `TFC_DEVELOPMENT_OWNER_PASSWORD` únicamente mediante el entorno local. Consulta `MIGRATION.md` para importación legacy; no ejecutes seeders de desarrollo en producción.

Para desarrollo frontend continuo puede usarse `npm run dev` en otra terminal.

## Tests

```powershell
php artisan test --compact
```

También están disponibles los scripts de Composer para lint y análisis estático. La suite usa SQLite en memoria; no reproduce todas las restricciones, bloqueos ni particularidades de MariaDB. Los cambios sensibles al motor deben comprobarse además contra MariaDB local.

## Fechas y timezone

Los timestamps se almacenan en UTC cuando corresponde. La timezone de negocio pertenece a `Location` y se expresa como IANA, por ejemplo `Europe/Madrid`. Carbon/DateTimeZone realiza la conversión, incluidos cambios DST. Las fechas visibles se presentan en español (`04/10/2026`, `20:01`); no se usa `CONVERT_TZ` para Timeclock ni offsets actuales para históricos.

## Assets

`public/build/` es generado por Vite y no se versiona. Ejecuta `npm run build` para generarlo localmente.

## Producción

La producción prevista usa Ubuntu 24.04, Nginx, PHP-FPM y MariaDB. Debe utilizar cuentas, secretos, almacenamiento y base de datos separados, `APP_DEBUG=false`, HTTPS, backups externos y un procedimiento verificado de restauración. El detalle operativo queda pendiente de documentar.

## Documentación relacionada

- [`MIGRATION.md`](MIGRATION.md): migración e importación de datos.
- [`docs/architecture/current-state.md`](docs/architecture/current-state.md): estado arquitectónico actual.
- [`docs/architecture/iteration-plan.md`](docs/architecture/iteration-plan.md): MVP pendiente y roadmap.
- [`docs/security/known-risks.md`](docs/security/known-risks.md): riesgos confirmados.
