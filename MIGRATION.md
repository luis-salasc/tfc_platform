# Migración de la aplicación TFC

La aplicación Laravel incluye los flujos de operación diarios: miembros, ficha resumida, pagos, progreso, asistencias, quiosco y panel de control.

## Datos heredados

La importación no modifica `tfc_database`. Para copiar o sincronizar los datos desde la aplicación PHP antigua:

```powershell
php artisan tfc:import-legacy
```

El comando es idempotente: se puede repetir sin duplicar miembros, pagos, progresos ni asistencias. Todos los campos históricos no mostrados aún en la ficha se conservan en el bloque de datos de admisión del miembro.

## Arranque local

Con MySQL activo en XAMPP:

```powershell
php artisan migrate
php artisan db:seed --class=DevelopmentSeeder
php artisan serve
```

El quiosco de acceso está en `/kiosco/the-fitness-club`. Para probar un check-in, abre la ficha de un miembro y usa su código QR mostrado en ella.
