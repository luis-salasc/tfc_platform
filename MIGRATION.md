# Migración e importación de datos de TFC Platform

Este documento describe únicamente el arranque de una instalación local y la importación desde el sistema legacy. No es un manual de despliegue completo.

## Arranque local

Con MariaDB/MySQL local activo y un `.env` configurado:

```powershell
php artisan migrate
```

La base de datos de la aplicación debe ser independiente de la base de datos legacy. No ejecutes `migrate:fresh`, `db:wipe` ni rollbacks destructivos sobre una instalación que contenga datos que deban conservarse.

## Datos de desarrollo

`DevelopmentSeeder` crea únicamente datos de desarrollo y requiere `TFC_DEVELOPMENT_OWNER_PASSWORD` en el entorno local/testing. No incluyas el valor de esa contraseña en Git ni ejecutes este seeder en producción.

```powershell
php artisan db:seed --class=DevelopmentSeeder
```

## Importación legacy

La conexión `legacy` es una fuente de solo lectura. La importación es idempotente y conserva en los bloques de datos del miembro los campos históricos que todavía no tienen una presentación específica.

```powershell
php artisan tfc:import-legacy --dry-run
php artisan tfc:import-legacy
```

Usa primero `--dry-run` para revisar el alcance. La importación no debe apuntar a la base de datos operativa como fuente y no sustituye una copia de seguridad.

## Separación de entornos

- Desarrollo: datos locales y credenciales locales, nunca datos de producción.
- Importación: conexión legacy separada, preferiblemente con usuario de solo lectura.
- Producción: no ejecutar seeders de desarrollo ni comandos de importación sin procedimiento aprobado, backup y comprobación de restauración.

Las credenciales, contraseñas y secretos deben permanecer en variables de entorno o en el gestor de secretos del entorno; este documento no contiene valores reales.
