# Plan de iteraciones y criterios de arquitectura

## Principios no negociables

- Un cliente es una organizacion; toda lectura y escritura operativa debe quedar aislada por organizacion y, cuando aplique, sede.
- Ninguna pantalla decide reglas de negocio por si misma. Pagos, bonos, reservas, asistencia y kiosco usan servicios de dominio compartidos.
- Los cambios economicos y de saldo son auditables. No se sobrescriben sin conservar el motivo, usuario y valores anteriores.
- Las integraciones entran por contratos versionados, no por acceso directo a la base de datos.
- La seguridad se aplica por capas: autenticacion, autorizacion, validacion, CSRF, rate limits, cabeceras, auditoria, pruebas y despliegue seguro.
- Una funcion desactivada por plan no borra datos ni deja rutas accesibles.

## Riesgos actuales que bloquean produccion

1. `sessions_remaining` mezcla bonos distintos y no permite caducidad ni trazabilidad de consumo.
2. El kiosco publico usa un QR como credencial portadora y no esta vinculado a un dispositivo de sede.
3. La autorizacion por organizacion se repite en controladores; debe centralizarse en policies y servicios.
4. Pagos, asistencia y recibos no tienen aun un libro de movimientos ni auditoria completa.
5. La cobertura de pruebas no protege los limites entre organizaciones, pagos, permisos y concurrencia.

## Iteracion 1 - Fundacion segura

Objetivo: impedir que la migracion siga acumulando deuda tecnica.

- Documentar limites de modulos y dependencias.
- Centralizar contexto de organizacion, permisos y pruebas de aislamiento.
- Separar configuracion local, testing, staging y produccion.
- Establecer cabeceras, politica de secretos, rate limits y registro de auditoria base.
- Reparar flujos existentes que no cumplen el contrato de interfaz antes de ampliar funciones.

Criterio de salida: pruebas de autorizacion entre dos organizaciones, pruebas de seguridad de rutas, analisis estatico y despliegue reproducible en staging.

## Iteracion 2 - Bonos, pagos y caducidad

Objetivo: sustituir el saldo global por bonos emitidos y movimientos inmutables.

- Productos de bono configurables y politica de caducidad.
- Bono emitido con sesiones disponibles, activacion y vencimiento.
- Consumo FIFO por vencimiento, extensiones auditadas y anulaciones/correcciones.
- Pago y recibo como evidencia del movimiento, sin perder su historico.

Criterio de salida: un miembro con varios bonos consume el correcto y una extension deja auditoria completa.

## Iteracion 3 - Planificacion, reservas y asistencia

Objetivo: unificar planificacion recurrente, huecos a demanda, capacidad y check-in.

- Plantillas recurrentes, sesiones concretas y excepciones.
- Reserva individual/grupal, lista de espera, cancelacion y no-show.
- Control de capacidad con transacciones; nunca solo en interfaz.
- Asistencia consume el bono al que esta vinculada la reserva.

Criterio de salida: no es posible sobrepasar capacidad ni consumir un bono caducado.

## Iteracion 4 - Kiosco e integraciones

Objetivo: kiosco de sede y contrato de integracion seguro.

- Credencial de dispositivo, sede asignada y permisos minimos.
- QR/NFC como identificadores de miembro; adaptadores para tornos y lectores.
- API privada `/api/v1`, scopes, rate limits, auditoria, idempotencia y OpenAPI.
- Estrategia de funcionamiento degradado para conectividad limitada.

## Iteracion 5 - Reporting, comunicaciones, retos y carteleria

Objetivo: convertir datos operativos fiables en producto vendible.

- Metricas agregadas y segmentos guardados; no consultas pesadas sobre operacion.
- Comunicaciones con consentimiento, colas e historial.
- Retos configurables, ranking y carteleria de solo lectura en tiempo real.

## Produccion

- Staging y produccion usan cuentas, bases, secretos y almacenamiento distintos.
- Produccion no ejecuta seeders de desarrollo ni tests.
- `APP_DEBUG=false`, HTTPS, cookies seguras, backups externos y restauracion verificada.
- La base de datos no se expone publicamente; los accesos administrativos usan MFA y claves SSH.
