# Contrato de integración — API v1

> **Estado:** congelado para la Fase 0 (2026-10-21).
> **Consumidor principal:** paneles de rol (`app/views/admin|recepcion|medico|paciente`) y landing/kiosco.
> **Implementación:** `app/controllers/Api/**` + `app/controllers/AuthController.php` + `PublicController.php` + `KioscoController.php`.
> **Cambios:** se acuerdan entre Matias y Lucas antes de modificar este archivo.

---

## 1. Convenciones generales

| Tema | Regla |
|---|---|
| Base URL | `APP_URL` (local: `http://localhost:8080`). |
| Formato | JSON UTF-8, salvo vistas HTML e impresiones. |
| Fechas | `YYYY-MM-DD`. |
| Horas | `HH:MM` (24 h). |
| Zona horaria | `America/La_Paz`. |
| Dinero | Número con 2 decimales (`150.00`), sin símbolo. |
| IDs | Enteros. |
| Booleanos | `true` / `false`. |
| Paginación | `?page=1&per_page=20` (máximo 100). |
| Orden | `?sort=fecha&direction=desc` cuando aplique. |
| CSRF | Header `X-CSRF-Token` en `fetch`; campo `_token` en formularios. |
| Roles | `super_admin`, `admin`, `recepcionista`, `recepcionista_medico`, `medico`, `paciente`. |

### 1.1 Envelope de éxito

```json
{
  "ok": true,
  "data": {},
  "message": null,
  "meta": { "page": 1, "per_page": 20, "total": 57 }
}
```

### 1.2 Envelope de error

```json
{
  "ok": false,
  "error": {
    "code": "VALIDATION",
    "message": "Revisa los campos marcados.",
    "fields": { "fecha": "La fecha no puede ser pasada." }
  }
}
```

Códigos HTTP:

| Código | Uso |
|---|---|
| `200` | Consulta/actualización correcta. |
| `201` | Recurso creado. |
| `204` | Sin contenido (logout, eliminación lógica). |
| `400` | Solicitud mal formada. |
| `401` | Sin sesión o sesión expirada. |
| `403` | Sin permisos para el recurso, o CSRF inválido/expirado (`CSRF_TOKEN_MISMATCH`). |
| `404` | Recurso inexistente. |
| `405` | Método no permitido. |
| `409` | Conflicto (CI duplicada, slot ocupado, transición inválida). |
| `422` | Validación de campos. |
| `500` | Error interno (mensaje genérico). |

Códigos de error de negocio: `VALIDATION`, `UNAUTHENTICATED`, `FORBIDDEN`, `NOT_FOUND`, `CONFLICT`, `SLOT_TAKEN`, `DUPLICATE_CI`, `INVALID_TRANSITION`, `CSRF_TOKEN_MISMATCH`, `RATE_LIMITED`, `SERVER_ERROR`.

### 1.3 Autenticación

- Paneles, landing y portal usan sesión PHP con cookie `HttpOnly`, `Secure` (en HTTPS) y `SameSite=Lax`.
- El kiosco usa token HMAC en el header `X-Kiosk-Token`.
- La página pública de pedidos no usa sesión: valida `codigo` + `codigo_acceso`.
- `GET /api/me` devuelve el usuario de sesión. Si no hay sesión: `401`.
- `GET /api/csrf` devuelve el token para el `fetch` inicial del panel.

---

## 2. Autenticación

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/login` | pública | Formulario de login. |
| POST | `/auth/login` | pública | Valida credenciales y crea sesión. |
| POST | `/auth/logout` | sesión | Cierra sesión. |
| GET | `/recuperar` | pública | Formulario "olvidé contraseña". |
| POST | `/auth/recuperar` | pública | Genera token y envía correo. |
| GET | `/reset?token=...` | pública | Formulario de nueva contraseña. |
| POST | `/auth/reset` | pública | Guarda la nueva contraseña. |
| GET | `/activar` | pública | Formulario de activación (CI + teléfono). |
| POST | `/auth/activar` | pública | Verifica y crea credenciales de paciente. |
| GET | `/api/me` | sesión | Usuario, rol y alcance actual. |
| GET | `/api/csrf` | pública | Token CSRF de la sesión. |

### 2.1 Login

```json
POST /auth/login
{
  "identificador": "medico@vidasalud.test",
  "password": "Demo1234!"
}
```

`identificador` acepta usuario, email o CI (paciente). Respuesta:

```json
{
  "ok": true,
  "data": {
    "usuario": {
      "id": 5,
      "rol": "medico",
      "nombre": "Dra. Ana Pérez",
      "medico_id": 3,
      "medico_ids": [3],
      "paciente_id": null
    },
    "redirect": "/medico"
  },
  "message": null,
  "meta": {}
}
```

`medico_ids` contiene uno o más médicos para `recepcionista_medico`; para los demás roles es `[]` o el médico propio. `paciente_id` solo se completa para pacientes.

Errores: credenciales inválidas (`401`, mensaje genérico), cuenta bloqueada (`423` o `403` con código `ACCOUNT_LOCKED`), cuenta inactiva (`403`, `ACCOUNT_INACTIVE`), paciente sin activar (`403`, `ACCOUNT_NOT_ACTIVE`). Los errores de validación incluyen `fields` con el detalle por campo.

### 2.2 Recuperación

```json
POST /auth/recuperar
{ "email": "usuario@vidasalud.test" }
```

Siempre responde `200` con mensaje genérico, exista o no el correo. Si existe, genera un token de 30 minutos y envía el enlace por correo.

```json
POST /auth/reset
{
  "token": "token-recibido-por-correo",
  "password": "NuevaClave1",
  "password_confirmation": "NuevaClave1"
}
```

Respuesta `200` con `data.redirect = "/login"`. Token inválido o expirado: `422`, `TOKEN_INVALID`.

### 2.3 Activación de paciente

```json
POST /auth/activar
{
  "ci": "12345678",
  "telefono": "71234567",
  "password": "NuevaClave1",
  "password_confirmation": "NuevaClave1",
  "email": "opcional@correo.com"
}
```

La CI y el teléfono deben coincidir con un paciente registrado en kiosco que aún no tenga cuenta. Respuesta `200` con `data.redirect = "/login"`. Si no coincide: `404`, `ACTIVATION_NOT_FOUND`; si ya está activo: `409`, `ACCOUNT_ALREADY_ACTIVE`.

### 2.4 Logout

```json
POST /auth/logout
```

Requiere sesión y CSRF. Responde `200` con `message = "Sesión cerrada."` y elimina la cookie de sesión.

---

## 3. Landing pública

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/` | pública | Landing HTML. |
| GET | `/reservar` | pública | Wizard de reserva (redirige a login si hace falta). |
| GET | `/kiosco` | pública | Instrucción/redirección al kiosco. |
| GET | `/pedido?codigo=&acceso=` | pública | Página pública de recetas/documentos/órdenes. |
| GET | `/api/public/especialidades` | pública | Especialidades activas, destacadas primero. |
| GET | `/api/public/medicos?especialidad=` | pública | Médicos activos por especialidad. |
| GET | `/api/public/disponibilidad?medico=&fecha=` | pública | Slots con cupo web. |
| POST | `/api/public/reservar` | paciente | Crea cita web + ficha + correo. |
| GET | `/api/public/pedido?codigo=&acceso=` | pública | Datos del pedido para imprimir. |

### 3.1 Reserva web

```json
POST /api/public/reservar
{
  "medico_id": 3,
  "especialidad_id": 1,
  "fecha": "2026-11-20",
  "hora": "09:00",
  "motivo": "Control general"
}
```

Respuesta `201`:

```json
{
  "ok": true,
  "data": {
    "cita": {
      "id": 120,
      "codigo": "CM-0120",
      "fecha": "2026-11-20",
      "hora_inicio": "09:00",
      "estado": "programada",
      "origen": "web"
    },
    "ficha": {
      "codigo": "FIC-0120",
      "codigo_acceso": "4821"
    }
  },
  "message": "Cita reservada. Revisa tu correo.",
  "meta": {}
}
```

Errores: `401` sin sesión, `409` slot ocupado/cupo agotado, `422` datos inválidos, `403` paciente no elegible por sexo/edad.

### 3.2 Catálogo público (implementado en M-15)

```json
GET /api/public/especialidades
{
  "ok": true,
  "data": [
    {
      "id": 1,
      "nombre": "Medicina General",
      "descripcion": "Atención integral para adultos y familias.",
      "sexo_permitido": "ambos",
      "edad_min": null,
      "edad_max": null,
      "destacada": true,
      "icono": "stethoscope",
      "estado": "activo"
    }
  ],
  "message": null,
  "meta": { "page": 1, "per_page": 50, "total": 1 }
}
```

`GET /api/public/medicos?especialidad=1` devuelve médicos activos. Cada ítem incluye `id`, `codigo`, `nombres`, `apellidos`, `nombre_completo`, `registro_profesional`, `duracion_cita_min`, `estado`, `especialidades[]` y `qr_url`. Si `especialidad` no existe o está inactiva: `404 NOT_FOUND`.

`GET /api/public/disponibilidad?medico=3&fecha=2026-11-20` devuelve todos los bloques generados para el canal web; el cliente debe filtrar `disponible=true`:

```json
{
  "ok": true,
  "data": {
    "fecha": "2026-11-20",
    "medico_id": 3,
    "canal": "web",
    "duracion_cita_min": 30,
    "slots": [
      { "hora": "08:00", "hora_inicio": "08:00", "hora_fin": "08:30", "disponible": true, "cupo_restante": 50 },
      { "hora": "08:30", "hora_inicio": "08:30", "hora_fin": "09:00", "disponible": false, "cupo_restante": 0 }
    ]
  },
  "message": null,
  "meta": {}
}
```

Reglas de los endpoints públicos:

- Solo se listan especialidades y médicos con `estado=activo`.
- `disponibilidad` excluye fechas pasadas, bloqueos, citas activas y franjas sin cupo web.
- Sin sesión, los endpoints de catálogo son de solo lectura y no requieren CSRF.
- `POST /api/public/reservar` exige sesión `paciente`; el rol se valida con `RoleMiddleware`.
- La reserva web revalida la disponibilidad dentro de una transacción y responde `409 SLOT_TAKEN` si el bloque se ocupó mientras el paciente completaba el formulario.

---

## 4. Kiosco

Todas las rutas salvo `sesion` requieren `X-Kiosk-Token`.

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/kiosco/sesion` | Inicia sesión con `device_key`; devuelve token HMAC (10 min). |
| POST | `/api/kiosco/identificar` | Busca paciente por CI; indica si existe o hay que registrarlo. |
| POST | `/api/kiosco/registrar` | Alta rápida de paciente sin cuenta. |
| GET | `/api/kiosco/especialidades` | Especialidades activas. |
| GET | `/api/kiosco/medicos?especialidad=` | Médicos activos. |
| GET | `/api/kiosco/disponibilidad?medico=&fecha=` | Slots con cupo kiosco. |
| POST | `/api/kiosco/reservar` | Crea cita con `origen=kiosco`. |
| POST | `/api/kiosco/ficha` | Emite ficha y confirma la cita. |
| GET | `/api/kiosco/ficha/{codigo}` | Reconsulta la ficha en pantalla. |

### 4.1 Sesión

```json
POST /api/kiosco/sesion
{ "device_key": "dev-kiosk-key" }
```

Respuesta: `{ "ok": true, "data": { "token": "...", "expires_in": 600 } }`.

El token va en el header `X-Kiosk-Token` de todas las demás rutas. Si falta, es inválido o expiró: `401 UNAUTHENTICATED` y el kiosco reinicia el flujo.

### 4.2 Identificar / registrar

```json
POST /api/kiosco/identificar
{ "ci": "12345678" }
```

Respuesta si existe:

```json
{
  "ok": true,
  "data": {
    "existe": true,
    "requiere_registro": false,
    "paciente": {
      "id": 1,
      "codigo": "PAC-0001",
      "ci": "12345678",
      "nombres": "Juan",
      "apellidos": "Pérez",
      "nombre_completo": "Juan Pérez",
      "fecha_nacimiento": "1985-05-12",
      "edad": 41,
      "sexo": "masculino",
      "telefono": "70020001",
      "estado": "activo"
    }
  }
}
```

Si no existe: `{ "existe": false, "requiere_registro": true }`. La respuesta no incluye correo, alergias ni otros datos clínicos.

```json
POST /api/kiosco/registrar
{
  "ci": "87654321",
  "nombres": "María",
  "apellidos": "Gómez",
  "fecha_nacimiento": "1990-05-12",
  "sexo": "femenino",
  "telefono": "71234567"
}
```

Reglas:

- CI de 5 a 10 dígitos con complemento opcional (`12345678-1A`); se normaliza a mayúsculas.
- Nombres/apellidos de 2 a 100 caracteres; fecha no futura ni mayor a 120 años; teléfono de 7 a 15 dígitos.
- El paciente se crea con `usuario_id = null` y `estado = activo`.
- Respuesta `201` con el mismo shape de `paciente`. Errores: `422 VALIDATION_ERROR` con `fields`, `409 CI_TAKEN`.

### 4.3 Catálogos

```http
GET /api/kiosco/especialidades?paciente_id=1
GET /api/kiosco/medicos?especialidad=1&paciente_id=1
```

`paciente_id` es opcional. Si se envía, se aplican las restricciones de sexo/edad de la especialidad (RX02/RX03) y se excluyen las no elegibles; en `medicos` una especialidad no elegible responde `403 NOT_ELIGIBLE`. Ambas respuestas usan el envelope con `meta.page/per_page/total`.

Errores: `422 VALIDATION_ERROR` si `especialidad` o `paciente_id` no son numéricos; `404 NOT_FOUND` si la especialidad no existe o está inactiva.

### 4.4 Disponibilidad

```http
GET /api/kiosco/disponibilidad?medico=1&fecha=2026-11-02
```

Descuenta bloqueos, citas activas y el cupo del canal `kiosco`. Respuesta:

```json
{
  "ok": true,
  "data": {
    "fecha": "2026-11-02",
    "medico_id": 1,
    "medico": "Carlos Méndez",
    "canal": "kiosco",
    "duracion_cita_min": 30,
    "slots": [
      { "hora": "08:00", "hora_inicio": "08:00", "hora_fin": "08:30", "disponible": true, "cupo_restante": 49 }
    ]
  }
}
```

Errores: `422` con `fields.fecha` (formato o fecha pasada) o `fields.medico`; `404` si el médico no existe o está inactivo.

### 4.5 Reservar

```json
POST /api/kiosco/reservar
{
  "paciente_id": 1,
  "medico_id": 1,
  "especialidad_id": 1,
  "fecha": "2026-11-02",
  "hora": "08:00",
  "motivo": "Consulta general"
}
```

Crea la cita con `origen = kiosco` y `estado = programada`. Revalida el cupo kiosco dentro de una transacción y bloquea al médico para evitar dobles reservas. Respuesta `201` con `id`, `codigo`, `fecha`, `hora_inicio`, `hora_fin`, `estado`, `origen`, `paciente`, `medico` y `especialidad`.

Errores: `422 VALIDATION_ERROR`, `403 NOT_ELIGIBLE`, `403 PATIENT_INACTIVE`, `404 NOT_FOUND`, `409 SLOT_TAKEN`.

### 4.6 Emitir ficha (M-25)

Pendiente de implementación en M-25. Contrato previsto:

```json
POST /api/kiosco/ficha
{ "cita_id": 120 }
```

Respuesta: `{ "codigo": "FIC-0120", "codigo_acceso": "4821", "qr": "data:image/png;base64,..." }`. Emitir la ficha confirma la cita (`programada → confirmada`).

---

## 5. Usuarios (admin)

> Endpoints propuestos para la pantalla "Usuarios" del panel admin. Se confirman en la Fase 1.

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/usuarios` | admin, super_admin | Listar con filtros `q`, `rol`, `estado`. |
| POST | `/api/usuarios` | admin, super_admin | Crear usuario interno. |
| GET | `/api/usuarios/{id}` | admin, super_admin | Detalle. |
| PUT | `/api/usuarios/{id}` | admin, super_admin | Editar datos y rol. |
| PATCH | `/api/usuarios/{id}/estado` | admin, super_admin | Activar/desactivar. |
| PATCH | `/api/usuarios/{id}/desbloquear` | admin, super_admin | Resetear intentos y `bloqueado=false`. |

Payload de creación:

```json
{
  "username": "nuevo.recepcion",
  "email": "nuevo@vidasalud.test",
  "password": "ClaveSegura1",
  "rol": "recepcionista",
  "activo": true
}
```

---

## 6. Pacientes

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/pacientes` | admin, recepción | Listar con `q`, `estado`, paginación. |
| GET | `/api/pacientes/buscar?q=` | admin, recepción | Buscar por CI/nombre/apellido. |
| GET | `/api/pacientes/{id}` | admin, recepción, médico | Detalle. |
| POST | `/api/pacientes` | admin, recepción | Crear. |
| PUT | `/api/pacientes/{id}` | admin, recepción | Editar. |
| PATCH | `/api/pacientes/{id}/estado` | admin | Activar/desactivar. |
| GET | `/api/pacientes/{id}/citas` | admin, recepción, médico | Historial de citas. |
| GET | `/api/me/perfil` | paciente | Perfil propio. |
| PUT | `/api/me/perfil` | paciente | Editar contacto/datos médicos propios. |

Payload de creación:

```json
{
  "ci": "87654321",
  "nombres": "María",
  "apellidos": "Gómez",
  "fecha_nacimiento": "1990-05-12",
  "sexo": "femenino",
  "telefono": "71234567",
  "email": "maria@correo.com",
  "direccion": "Av. Siempre Viva 123",
  "tipo_sangre": "O+",
  "alergias": "Penicilina",
  "enfermedades_cronicas": null,
  "medicamentos": null,
  "antecedentes": null,
  "emergencia_nombre": "Juan Gómez",
  "emergencia_parentesco": "Padre",
  "emergencia_telefono": "70000000"
}
```

Errores: CI duplicada (`409`, `DUPLICATE_CI`), paciente inactivo no puede agendar (`409`).

---

## 7. Médicos

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/medicos` | admin, recepción | Listar con `q`, `especialidad`, `estado`. |
| GET | `/api/medicos/{id}` | admin, recepción | Detalle + especialidades. |
| POST | `/api/medicos` | admin | Crear (opcionalmente crea usuario). |
| PUT | `/api/medicos/{id}` | admin, médico (propio) | Editar. |
| PATCH | `/api/medicos/{id}/estado` | admin | Activar/desactivar. |
| PUT | `/api/medicos/{id}/especialidades` | admin | Asignar especialidades. |
| POST | `/api/medicos/{id}/qr` | médico (propio) | Subir QR (multipart, ≤ 1 MB). |
| POST | `/api/medicos/{id}/recepcionistas` | admin | Asignar recepcionista. |
| GET | `/api/medicos/{id}/agenda?fecha=` | admin, recepción, médico | Agenda del día. |
| GET | `/api/medicos/{id}/agenda/dia` | recepción, médico | Resumen con contadores. |

Payload de creación:

```json
{
  "nombres": "Ana",
  "apellidos": "Pérez",
  "registro_profesional": "MP-12345",
  "email": "ana.perez@vidasalud.test",
  "telefono": "71234567",
  "duracion_cita_min": 30,
  "especialidades": [1, 2],
  "crear_usuario": true,
  "username": "ana.perez",
  "password": "ClaveSegura1"
}
```

---

## 8. Especialidades

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/especialidades` | admin, recepción | Listar con `estado`, `destacada`. |
| GET | `/api/especialidades/{id}` | admin | Detalle. |
| POST | `/api/especialidades` | admin | Crear. |
| PUT | `/api/especialidades/{id}` | admin | Editar. |
| PATCH | `/api/especialidades/{id}/estado` | admin | Activar/desactivar. |

Payload:

```json
{
  "nombre": "Pediatría",
  "descripcion": "Atención de niños y adolescentes.",
  "sexo_permitido": "ambos",
  "edad_min": 0,
  "edad_max": 12,
  "destacada": true,
  "estado": "activo"
}
```

`sexo_permitido`: `ambos`, `masculino`, `femenino`.

---

## 9. Horarios, cupos y disponibilidad

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/medicos/{id}/horarios` | admin, recepción | Listar franjas. |
| POST | `/api/medicos/{id}/horarios` | admin | Crear franja. |
| PUT | `/api/horarios/{id}` | admin | Editar franja. |
| DELETE | `/api/horarios/{id}` | admin | Desactivar franja. |
| GET | `/api/medicos/{id}/bloqueos` | admin | Listar bloqueos. |
| POST | `/api/medicos/{id}/bloqueos` | admin | Crear bloqueo. |
| GET | `/api/disponibilidad?medico=&fecha=&canal=` | según rol | Slots disponibles por canal. |

Payload de franja:

```json
{
  "dia_semana": 1,
  "hora_inicio": "08:00",
  "hora_fin": "12:00",
  "cupos_web": 4,
  "cupos_kiosco": 4
}
```

`dia_semana`: `0` domingo … `6` sábado. `canal`: `web`, `kiosco`, `recepcion`.

---

## 10. Citas

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/citas` | según rol | Listar con `fecha`, `medico`, `especialidad`, `estado`. |
| GET | `/api/citas/{id}` | según rol | Detalle. |
| POST | `/api/citas` | recepción, paciente, kiosco | Registrar. |
| PUT | `/api/citas/{id}/reprogramar` | recepción | Cambiar fecha/hora. |
| PATCH | `/api/citas/{id}/cancelar` | recepción, paciente | Cancelar con motivo. |
| PATCH | `/api/citas/{id}/confirmar` | interno | Ficha → `confirmada`. |
| PATCH | `/api/citas/{id}/atender` | recepción, médico | Marcar `atendida`. |
| PATCH | `/api/citas/{id}/inasistencia` | recepción, médico | Marcar `inasistencia`. |
| POST | `/api/citas/{id}/reconsulta` | recepción, médico | Crear reconsulta. |
| GET | `/api/citas/{id}/historial-estados` | admin | Historial de estados. |

Payload de creación:

```json
{
  "paciente_id": 10,
  "medico_id": 3,
  "especialidad_id": 1,
  "fecha": "2026-11-20",
  "hora": "09:00",
  "motivo": "Control general"
}
```

Payload de reconsulta:

```json
{
  "tipo": "ausencia_medico",
  "fecha": "2026-11-22",
  "hora": "09:00",
  "motivo": "El médico no pudo atender"
}
```

`tipo`: `ausencia_medico` | `seguimiento`.

Estados: `programada`, `confirmada`, `atendida`, `cancelada`, `inasistencia`.
Transiciones inválidas → `409` con `INVALID_TRANSITION`.

---

## 11. Agenda e historial

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/medicos/{id}/agenda?fecha=&estado=` | recepción, médico, admin | Agenda del día. |
| GET | `/api/medicos/{id}/agenda?desde=&hasta=` | recepción, médico | Rango para calendario. |
| GET | `/api/medicos/{id}/agenda/dia` | recepción, médico | Contadores del día. |
| GET | `/api/pacientes/{id}/historial?desde=&hasta=&estado=` | recepción, médico, paciente (propio) | Historial de citas. |

---

## 12. Fichas

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/fichas?q=&fecha=` | recepción | Buscar fichas. |
| GET | `/api/fichas/{codigo}` | recepción | Detalle/reimpresión. |
| POST | `/api/fichas/{codigo}/reimprimir` | recepción | Registrar reimpresión. |

La emisión se hace por el kiosco (`POST /api/kiosco/ficha`). La ficha confirma la cita.

---

## 13. Historia clínica y pedidos

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| POST | `/api/citas/{id}/consulta` | médico | Crear consulta al atender. |
| GET | `/api/consultas/{id}` | médico, paciente (propio) | Detalle de consulta. |
| GET | `/api/pacientes/{id}/historia` | médico, paciente (propio) | Historia clínica cronológica. |
| POST | `/api/consultas/{id}/recetas` | médico | Crear receta + detalle. |
| GET | `/api/recetas/{id}` | médico, paciente | Detalle de receta. |
| POST | `/api/consultas/{id}/documentos` | médico | Crear documento. |
| POST | `/api/consultas/{id}/ordenes` | médico | Crear orden + detalle. |
| POST | `/api/ordenes/{id}/resultados` | médico | Registrar/subir resultado. |
| GET | `/api/recetas/{id}/imprimir` | médico, paciente | Vista imprimible. |
| GET | `/api/documentos/{id}/imprimir` | médico, paciente | Vista imprimible. |
| GET | `/api/ordenes/{id}/imprimir` | médico, paciente | Vista imprimible. |
| POST | `/api/pedidos/{tipo}/{id}/enviar` | médico, recep_medico | Marca envío y devuelve URL `wa.me`. |

`tipo` de pedido: `receta`, `documento`, `orden`.

Payload de consulta:

```json
{
  "motivo": "Dolor de cabeza",
  "anamnesis": "Paciente refiere cefalea desde hace 3 días.",
  "signos_vitales": {
    "pa": "120/80",
    "fc": 72,
    "temp": 36.5,
    "peso": 68,
    "spo2": 98,
    "talla": 1.65
  },
  "examen_fisico": "Sin signos de alarma.",
  "diagnostico": "Cefalea tensional.",
  "tratamiento": "Paracetamol 500 mg cada 8 h por 3 días.",
  "indicaciones": "Hidratación y reposo."
}
```

Payload de receta:

```json
{
  "detalle": [
    {
      "medicamento": "Paracetamol 500 mg",
      "dosis": "1 tableta",
      "via": "oral",
      "frecuencia": "cada 8 horas",
      "duracion": "3 días",
      "instrucciones": "Después de comer"
    }
  ]
}
```

Respuesta de envío:

```json
{
  "ok": true,
  "data": {
    "whatsapp_url": "https://wa.me/59171234567?text=...",
    "mensaje": "..."
  }
}
```

---

## 14. Pagos

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| POST | `/api/pagos` | recep_medico, médico | Registrar pago. |
| GET | `/api/pagos?medico=&desde=&hasta=` | recep_medico, médico | Listar. |
| GET | `/api/pagos/{id}` | recep_medico, médico | Detalle. |

Payload:

```json
{
  "paciente_id": 10,
  "medico_id": 3,
  "monto": 150.00,
  "metodo": "efectivo",
  "referencia_paciente": "Consulta general"
}
```

`metodo`: `efectivo`, `visa`, `mastercard`. La tarjeta es solo UI; no hay pasarela.

---

## 15. Reportes y dashboards

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/reportes/citas?fecha=&medico=&especialidad=&estado=` | admin | Citas filtradas. |
| GET | `/api/reportes/citas-por-medico?desde=&hasta=` | admin | Conteo por médico. |
| GET | `/api/reportes/citas-por-especialidad?desde=&hasta=` | admin | Conteo por especialidad. |
| GET | `/api/reportes/citas-por-estado?desde=&hasta=` | admin | Conteo por estado. |
| GET | `/api/reportes/pacientes?desde=&hasta=` | admin | Pacientes registrados. |
| GET | `/api/reportes/asistencia?desde=&hasta=` | admin | Atendidas/canceladas/inasistencias. |
| GET | `/api/reportes/agenda-diaria?medico=&fecha=` | admin | Agenda diaria. |
| GET | `/api/dashboard/admin` | admin, super_admin | Métricas del dashboard admin. |
| GET | `/api/dashboard/medico` | médico | Métricas del dashboard médico. |
| GET | `/api/reportes/{tipo}/csv` | admin | Exportación CSV (opcional). |

Respuesta de dashboard admin (resumen):

```json
{
  "ok": true,
  "data": {
    "citas_hoy": 24,
    "atendidas": 11,
    "canceladas": 3,
    "inasistencias": 2,
    "por_origen": { "web": 9, "kiosco": 8, "recepcion": 7 }
  },
  "message": null,
  "meta": {}
}
```

---

## 16. Auditoría

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/auditoria?tabla=&usuario=&accion=&desde=&hasta=` | admin, super_admin | Listar bitácora. |
| GET | `/api/auditoria/{id}` | admin, super_admin | Detalle con diff. |

La auditoría se escribe por trigger; no se expone endpoint de escritura.

---

## 17. Endpoints internos de apoyo

| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/health` | pública | Health check JSON. |
| GET | `/api/me` | sesión | Usuario actual. |
| GET | `/api/csrf` | pública | Token CSRF. |
| GET | `/api/opciones/catalogos` | sesión | Especialidades, médicos y estados para selects (opcional). |

---

## 18. Notas de implementación

- Todos los endpoints de panel pasan por `AuthMiddleware` + `RoleMiddleware`; el alcance fino se refuerza con RLS.
- `recepcionista_medico` solo opera sobre `medico_ids` de su sesión.
- El médico solo accede a sus propios recursos (`medico_id`).
- El paciente solo accede a sus propios recursos (`paciente_id`).
- Cualquier endpoint que muta estado exige CSRF, salvo kiosco (token HMAC) y endpoints públicos.
- Los listados deben devolver `meta` con `page`, `per_page` y `total`.
- Los errores de validación devuelven `error.fields` con `campo => mensaje`.
- No se exponen stack traces ni SQL en producción.

---

## 19. Mocks para el panel

Los archivos de `docs/api/mocks/` son la referencia para maquetar sin esperar al backend:

| Archivo | Pantalla |
|---|---|
| `auth-me.json` | Sesión y menú. |
| `especialidades.json` | Admin especialidades / landing. |
| `medicos.json` | Admin médicos / recepción. |
| `disponibilidad.json` | Wizard de reserva / recepción. |
| `citas.json` | Admin/recepción/médico. |
| `pacientes.json` | Recepción/médico. |
| `dashboard-admin.json` | Dashboard admin. |
| `dashboard-medico.json` | Dashboard médico. |
| `pagos.json` | Pagos. |
| `auditoria.json` | Auditoría. |

Lucas puede usar estos JSON directamente en su capa de datos y luego cambiar la URL por el endpoint real.
