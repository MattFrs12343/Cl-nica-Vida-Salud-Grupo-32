# Guion de demo — Clínica Vida Salud (M-39)

Duración objetivo: **12–15 minutos**. La demo muestra la landing, los cinco
roles, el kiosco, reportes y auditoría usando los datos seed.

## 1. Antes de empezar

```bash
docker compose up -d
docker compose exec app php bin/db-migrate.php
docker compose exec app php bin/db-reset.php   # destructivo: reinicia datos demo
docker compose exec app php bin/qa-final.php   # opcional, 18 suites
```

Abre estas pestañas:

| Pestaña | URL |
|---|---|
| Landing | <http://localhost:8080/> |
| Login | <http://localhost:8080/login> |
| Kiosco | <http://localhost:8080/kiosco> |
| Mailpit | <http://localhost:8025> |
| Health | <http://localhost:8080/api/health> |

Cuentas (contraseña `Demo1234!`):

| Rol | Usuario |
|---|---|
| super_admin | `superadmin` |
| admin | `admin` |
| recepcionista | `recepcion` |
| recepcionista_medico | `recepcion.medico` |
| médico | `medico` |
| paciente | `12345678` (CI) |

> Si la demo es en producción, no uses estas cuentas ni ejecutes seeders.

## 2. Landing y reserva (2 min)

1. Muestra la landing: especialidades, médicos destacados y CTA **Reservar cita**.
2. Entra con el paciente `12345678`.
3. Reserva una cita para **mañana** con Medicina General y el Dr. Carlos Méndez.
4. Señala el mensaje de confirmación y la ficha generada.

Mensaje clave: la reserva usa sesión de paciente, valida el cupo y respeta
CSRF/`same-origin`; el cupo se descuenta de forma transaccional.

## 3. Panel administrativo (3 min)

Entra con `admin`:

1. **Dashboard**: citas de hoy, estados, origen, tasa de asistencia y totales.
2. **Usuarios**: filtros por rol/estado y bloqueo.
3. **Médicos** y **Especialidades**: catálogos y edición.
4. **Horarios**: agenda semanal, cupos web/kiosco y bloqueos.
5. **Citas**: filtros por fecha, médico, especialidad y estado; abre el detalle.
6. **Reportes**: descarga un CSV de citas por médico o asistencia.
7. **Auditoría**: muestra el registro del cambio reciente (usuario, IP, antes/después).

Mensaje clave: permisos por rol, RLS, paginación y exportaciones sin paginar.

## 4. Recepción (2 min)

Entra con `recepcion`:

1. **Pacientes**: busca por CI `10000002` (María Gómez) y abre la ficha.
2. Registra o actualiza un paciente nuevo.
3. **Citas**: agenda una cita para el paciente y confirma.
4. **Fichas**: reimprime o consulta el código de acceso.
5. **Pagos**: registra un pago en efectivo o con tarjeta.

Mensaje clave: el flujo de mostrador queda auditado y no requiere acceso clínico
innecesario.

## 5. Médico (3 min)

Entra con `medico`:

1. **Agenda**: muestra las citas de hoy/mañana y los contadores.
2. Abre una cita y registra una **consulta** con signos vitales, diagnóstico y
   tratamiento.
3. Emite una **receta** y una **orden de laboratorio**; muestra el código y el
   código de acceso.
4. Envía la receta por WhatsApp (o muestra el enlace generado).
5. Registra un **pago/QR** y consulta el historial del paciente.
6. Marca una cita como **atendida** o **inasistencia**.

Mensaje clave: el médico solo ve sus pacientes y sus datos clínicos.

## 6. Portal del paciente (2 min)

Entra con `12345678`:

1. **Mis citas**: próximas y pasadas; abre el detalle.
2. Cancela una cita que cumpla la regla del día anterior.
3. **Historial**: consultas, recetas, documentos y órdenes.
4. **Perfil**: actualiza teléfono/email.
5. **Cambiar contraseña**: muestra la validación de contraseña actual y complejidad.
6. Abre el **pedido público** de una receta con código + acceso (sin sesión).

Mensaje clave: el paciente solo accede a sus propios datos.

## 7. Kiosco (2 min)

En `/kiosco`:

1. Inicia sesión con la device key de desarrollo o usa el modo demo.
2. Identifica al paciente con CI `10000001`.
3. Selecciona una cita y muestra la ficha con código de acceso.
4. Si hay tiempo, registra un paciente nuevo y agenda una cita.

Mensaje clave: el kiosco usa token HMAC, no sesión PHP, y no accede a datos
clínicos ni pagos.

## 8. Cierre técnico (1 min)

1. Muestra `docker compose exec app php bin/qa-final.php` en verde.
2. Muestra `GET /api/health` y las cabeceras de seguridad.
3. Recuerda: `APP_DEBUG=false`, HTTPS, backups y preflight de producción.
4. Muestra `docs/deploy/PRODUCCION.md` como guía de despliegue.

## 9. Plan B

- Si falla la red o el correo: usa Mailpit y muestra el correo en cola.
- Si falla una cita: usa otra fecha/hora y explica la validación de cupo.
- Si falla un pago: muestra el historial y el registro de auditoría.
- Si el navegador conserva una sesión: abre una ventana incógnito.
- Si el kiosco no responde: usa la CI `10000001` y el botón de demo.
