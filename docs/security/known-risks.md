# Riesgos conocidos

Este documento separa bloqueadores actuales, deuda técnica y trabajo posterior. No sustituye una auditoría de producción.

## Bloqueadores MVP/producción

- Fotografías de miembros almacenadas en almacenamiento público; debe definirse almacenamiento privado y autorización antes de producción.
- Datos sensibles y de salud visibles a roles operativos más amplios de lo necesario.
- Falta de bootstrap reproducible de producción y de un procedimiento verificado de recuperación.
- Kiosco basado en QR portador sin identidad robusta de dispositivo y sede.
- La suite usa SQLite y no reproduce todas las restricciones, bloqueos y defaults de MariaDB; las migraciones deben validarse en MariaDB.
- El estado Git y el proceso de despliegue deben mantenerse reproducibles.

## Deuda técnica

- Conviven `OrganizationMembership.role` y Spatie Permission; algunos permisos efectivos dependen del rol de membresía.
- El esquema conserva capacidad multi-organización, aunque el producto se despliega como single-tenant por instalación.
- El middleware selecciona la primera membresía activa y no existe selector de organización.
- El código legacy de Member Portal permanece aunque el Portal está deshabilitado.
- Pagos y recibos todavía tienen envío de correo síncrono.
- La proyección `sessions_remaining` convive con el cálculo del ledger y requiere disciplina de integridad.

## Post-MVP

- Rediseño completo de identidades y permisos multi-organización.
- Portal de miembros con identidad separada y superficie restringida.
- Kiosco con credencial de dispositivo, sede asignada y operación degradada.
- Reservas, bonos con caducidad y reporting avanzado.
- API privada versionada, scopes, rate limits y OpenAPI.

No se incluyen secretos, credenciales ni textos legales en este documento.
