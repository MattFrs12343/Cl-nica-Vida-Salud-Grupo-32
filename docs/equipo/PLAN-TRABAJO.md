# Plan de trabajo y reparto — Clínica Vida Salud

> **Última actualización:** 2026-10-23
> **Fuente funcional de verdad:** `docs/specs/`
> **Estado del repo:** Fase 1 backend (DB, RLS, auth y correo) terminada. Fase 2 backend terminada (M-15–M-22). Fase 3 en curso: token de kiosco (M-23) y APIs de kiosco (M-24) listos; pendientes la ficha (M-25), la UI del kiosco y las APIs de recepción.

Este documento define **quién hace qué**, **qué archivos puede tocar cada uno** y **en qué orden**. La meta es que Matias y Lucas puedan trabajar en paralelo sin pisarse.

---

## 0. Reparto en una frase

| Persona | Se encarga de |
|---|---|
| **Matias (tú)** | Backend PHP, base de datos, landing pública, kiosco, SMTP/correo y APIs. |
| **Lucas** | Vistas de los paneles de rol (admin, recepción, médico, portal paciente), vistas de autenticación y assets del panel. |
| **Ambos** | Contratos de integración, QA cruzado, demo y documentación. |

**Regla clave:** Lucas **no** escribe SQL, migraciones, servicios ni endpoints. Matias **no** edita las vistas de panel de Lucas. Si un trabajo cruza la frontera, se pide por contrato o por PR.

### 0.1 Límites explícitos

**Lucas NO hace:**
- Migraciones, seeders, RLS, triggers ni consultas SQL.
- Servicios de negocio (`app/services/`), modelos (`app/models/`) ni core (`app/core/`).
- Endpoints de `app/controllers/Api/`, auth, landing, kiosco ni correo.
- Vistas de `landing/`, `kiosco/` ni sus assets.
- Deploy, Docker, `.env`, Composer.

**Matias NO hace:**
- Vistas de `admin/`, `recepcion/`, `medico/`, `paciente/` ni `auth/`.
- Layout Gentelella, menú lateral, componentes visuales ni JS de panel.
- Cambios dentro de `gentelella/` (salvo revisar).
- Build de assets del panel.

> Si algo falta en la frontera, se agrega al contrato (`docs/api/contract.md`) y se implementa del lado que corresponda. No se resuelve "tocando un poquito" el archivo del otro.

---

## 1. Reglas para no pisarnos

1. **Un archivo, un dueño.** Ver mapa de la sección 2.
2. **No refactors cruzados.** Si ves algo mejorable en el archivo del otro, abre un issue o coméntalo; no lo cambies.
3. **Base de datos solo Matias.** Lucas pide campos o endpoints nuevos; no crea migraciones.
4. **Contrato antes que código.** La Fase 0 es bloqueante: sin contrato de API/sesión/vista, no se paraleliza nada.
5. **Mocks primero.** Lucas puede construir todas las pantallas con los JSON de `docs/api/mocks/` antes de que exista el endpoint real.
6. **Una rama por tarea.** `matias/m07-migraciones`, `lucas/l04-layout`. PR pequeño a `main`.
7. **PR revisado por el otro.** Nadie mergea su propio PR sin revisión.
8. **Sincronización diaria de 10 minutos.** Qué terminé, qué sigue, qué me bloquea.
9. **Las specs mandan.** Si el código y la spec difieren, gana la spec; si la spec está mal, se acuerda el cambio y se actualiza el documento.
10. **Nada de secretos en Git.** `.env`, claves SMTP, tokens y contraseñas reales no se commitean.

---

## 2. Mapa de propiedad de archivos

| Ruta / patrón | Dueño | Notas |
|---|---|---|
| `database/**` | Matias | Migraciones, seeders, RLS, triggers, ERD. |
| `app/config/**` | Matias | `config.php`, `database.php`, `mail.php`, `panels.php` si hiciera falta. |
| `app/core/**` | Matias | Router, Request, Response, Session, DB, View, CSRF, helpers. |
| `app/middlewares/**` | Matias | Auth, Role, Kiosk. |
| `app/models/**` | Matias | PDO preparado. |
| `app/services/**` | Matias | Reglas de negocio y transacciones. |
| `app/controllers/Api/**` | Matias | Endpoints JSON de todos los módulos. |
| `app/controllers/AuthController.php` | Matias | Login, logout, reset, activación. |
| `app/controllers/PublicController.php` | Matias | Landing, reserva web, pedido público. |
| `app/controllers/KioscoController.php` | Matias | Flujo del kiosco. |
| `app/routes/public.php`, `auth.php`, `api.php` | Matias | Registro de rutas públicas, auth y API. |
| `app/routes/panels.php` | Lucas | Solo rutas de paneles. |
| `app/controllers/PanelController.php` | Lucas | Renderiza vistas; **sin SQL ni lógica de negocio**. |
| `app/views/layouts/base.php`, `kiosco.php` | Matias | Layouts públicos. |
| `app/views/layouts/gentelella.php`, `auth.php` | Lucas | Layout de paneles y auth. |
| `app/views/landing/**`, `kiosco/**` | Matias | Vistas públicas. |
| `app/views/admin/**`, `recepcion/**`, `medico/**`, `paciente/**`, `auth/**` | Lucas | Vistas de rol y autenticación. |
| `public/index.php`, `.htaccess` | Matias | Router frontal. |
| `public/assets/landing/**`, `kiosco/**` | Matias | CSS/JS/imágenes públicas. |
| `public/assets/panel/**` | Lucas | CSS/JS/imágenes del panel. |
| `gentelella/**` | Lucas | Fuente de la plantilla; solo Lucas lo edita/compila. |
| `docker-compose.yml`, `.env.example` | Matias | Infra local. |
| `composer.json`, `composer.lock` | Matias | Única persona que agrega dependencias PHP. |
| `README.md` | Matias | Arranque y deploy. |
| `docs/specs/**` | Ambos (lectura) | Cambios de spec se acuerdan y los aplica Matias. |
| `docs/api/**` | Matias | Contrato y mocks; Lucas los consume. |
| `docs/equipo/**` | Ambos | Cada uno escribe solo su archivo de estado si se crea. |

### 2.1 Archivos compartidos: cuidado especial

| Archivo | Dueño | Regla |
|---|---|---|
| `public/index.php` | Matias | Lucas no lo toca; sus rutas viven en `app/routes/panels.php`. |
| `app/core/Router.php` | Matias | Debe cargar automáticamente todos los archivos de `app/routes/`. |
| `composer.json` | Matias | Lucas no agrega dependencias PHP. |
| `.env.example` | Matias | Si Lucas necesita una variable, la pide. |
| `README.md` | Matias | Lucas documenta el panel en `docs/equipo/paneles.md` si hace falta. |

---

## 3. Contratos de integración (se congelan en Fase 0)

Estos contratos son la frontera entre los dos. Se publican en `docs/api/` y **no cambian sin avisar**.

### 3.1 Sesión

```php
$_SESSION['usuario'] = [
    'id'          => 12,
    'rol'         => 'medico',      // super_admin|admin|recepcionista|recepcionista_medico|medico|paciente
    'nombre'      => 'Dra. Ana Pérez',
    'medico_id'   => 3,             // solo medico
    'medico_ids'  => [3, 5],        // solo recepcionista_medico
    'paciente_id' => null,          // solo paciente
];
```

### 3.2 Helpers PHP disponibles

| Helper | Uso |
|---|---|
| `e($valor)` | Escapa output con `htmlspecialchars()`. |
| `auth()` | Devuelve el usuario de sesión o `null`. |
| `has_role(...$roles)` | `true` si el rol actual está en la lista. |
| `csrf_token()` | Token CSRF de la sesión. |
| `csrf_field()` | `<input type="hidden" name="_token">`. |
| `csrf_verify()` | Valida el token en POST/PUT/PATCH/DELETE. |
| `flash($tipo, $mensaje)` / `flashes()` | Mensajes de éxito/error. |
| `old($campo, $default)` | Repoblar formularios. |
| `redirect($url, $codigo = 302)` | Redirección. |
| `json_ok($data, $meta = [])` / `json_error(...)` | Respuestas API. |
| `asset($ruta)` / `url($ruta)` | URLs base. |

### 3.3 Respuesta API

Éxito:

```json
{
  "ok": true,
  "data": {},
  "message": null,
  "meta": { "page": 1, "per_page": 20, "total": 57 }
}
```

Error:

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

Códigos HTTP: `200`, `201`, `204`, `400`, `401`, `403`, `404`, `409`, `422`, `500`.

### 3.4 CSRF

- Formularios HTML: campo oculto `_token` con `csrf_field()`.
- `fetch`: header `X-CSRF-Token` con `csrf_token()`.
- Excepción: endpoints públicos de landing y kiosco (token de kiosco en su lugar).

### 3.5 Contrato de vistas de panel

`PanelController` renderiza:

```php
View::render('admin/usuarios/index', [
    'title'       => 'Usuarios',
    'active'      => 'usuarios',
    'breadcrumbs' => [
        ['label' => 'Inicio', 'url' => '/admin'],
        ['label' => 'Usuarios'],
    ],
]);
```

El layout `gentelella.php` recibe y usa:

| Variable | Contenido |
|---|---|
| `$title` | Título de la página. |
| `$active` | Clave del ítem activo del menú. |
| `$user` | Usuario de sesión. |
| `$csrfToken` | Token para el meta tag. |
| `$flashes` | Mensajes pendientes. |
| `$content` | HTML de la vista. |

Convención de rutas y carpetas:

| Ruta | Vista |
|---|---|
| `/admin/usuarios` | `admin/usuarios/index.php` |
| `/recepcion/citas` | `recepcion/citas/index.php` |
| `/medico/agenda` | `medico/agenda/index.php` |
| `/portal/mis-citas` | `paciente/mis-citas/index.php` |

### 3.6 Rutas que cada uno registra

| Archivo | Dueño | Contenido |
|---|---|---|
| `app/routes/public.php` | Matias | `/`, `/reservar`, `/kiosco`, `/pedido`, APIs públicas. |
| `app/routes/auth.php` | Matias | `/login`, `/recuperar`, `/reset`, `/activar`, `/auth/*`. |
| `app/routes/api.php` | Matias | `/api/*` de todos los módulos. |
| `app/routes/panels.php` | Lucas | `/admin/*`, `/recepcion/*`, `/medico/*`, `/portal/*`. |

### 3.7 Formatos

- Fechas: `YYYY-MM-DD`. Horas: `HH:MM`. Zona horaria: `America/La_Paz`.
- Dinero: número con 2 decimales (no string con símbolo).
- IDs: enteros.
- Paginación: `?page=1&per_page=20`.
- Booleanos: `true`/`false` reales en JSON.

### 3.8 Mocks

Matias publica en `docs/api/mocks/` un JSON por endpoint importante:

```
especialidades.json
medicos.json
disponibilidad.json
citas.json
pacientes.json
dashboard-admin.json
dashboard-medico.json
pagos.json
reportes.json
```

Lucas usa esos JSON para maquetar y luego cambia el `fetch` real. Si un mock no existe, lo pide.

---

## 4. Fases y tareas

**Leyenda:** `[ ]` pendiente · `[~]` en curso · `[x]` hecho · `[!]` bloqueado

### Fase 0 — Acuerdos y esqueleto (1–2 días)

**Salida:** el repo arranca con Docker, el contrato está publicado y Lucas puede pintar un panel con mocks.

#### Matias

- [x] **M-01 · Esqueleto del proyecto** — crea `public/`, `app/{config,core,middlewares,controllers,services,models,views}`, `database/`, `storage/logs`, `.gitignore`, `.env.example` y README de arranque. · *DoD:* `php -S localhost:8000 -t public` responde 200 en `/`.
- [x] **M-02 · Docker local** — `php:8.2-apache` con `pdo_pgsql` + `postgres:16` + `mailpit`; volúmenes, variables y healthchecks. · *DoD:* PDO conecta a Postgres y Mailpit abre en `localhost:8025`.
- [x] **M-03 · Core PHP** — autoload PSR-4, Router (GET/POST/PUT/PATCH/DELETE, parámetros y archivos de rutas), Request, Response, Session, DB (PDO), View, CSRF, helpers, manejo de errores, timezone. · *DoD:* ruta demo, respuesta JSON demo, 404 y 500 controlados.
- [~] **M-04 · Contrato de API + mocks** — `docs/api/contract.md` con todos los endpoints de las specs, envelope, roles, paginación y errores; `docs/api/mocks/*.json`. · *DoD:* Lucas revisa y firma; puede trabajar sin preguntar. · *Estado:* contrato y 12 mocks listos; pendiente firma de Lucas.
- [x] **M-05 · Contrato de sesión, helpers y vistas** — `docs/api/view-contract.md` con el shape de sesión, helpers, variables de layout, menú por rol y CSRF. · *DoD:* ambos lo dan por congelado.
- [x] **M-06 · Usuarios demo** — seed de 6 roles + credenciales de desarrollo en `docs/equipo/CREDENCIALES-DEV.md` y script `db:seed`. · *DoD:* existen las cuentas para probar cada panel.

#### Lucas

- [ ] **L-01 · Lectura y dudas** — lee `docs/specs/00`, `01`, `02`–`15` (en diagonal) y este plan; anota dudas antes de tocar código. · *DoD:* dudas resueltas con Matias.
- [ ] **L-02 · Spike Gentelella v4 ↔ PHP** — entiende que es Vite + vanilla JS (no Bootstrap/jQuery), decide cómo integrarla: assets compilados + shell PHP, o convertir `production/*.html`. Documenta en `docs/equipo/gentelella-integracion.md`. · *Timebox:* 4 horas. · *DoD:* decisión escrita y probada en una página.
- [ ] **L-03 · Pipeline de assets del panel** — build reproducible de Gentelella hacia `public/assets/panel/**`; ignora `node_modules/` y `dist/`. · *DoD:* `npm run build` + copia funciona desde cero.
- [ ] **L-04 · Layout de panel** — `app/views/layouts/gentelella.php` + partials: sidebar, topbar, breadcrumbs, flash, menú de usuario, meta CSRF. Menú distinto por rol. · *DoD:* `/admin`, `/recepcion`, `/medico` y `/portal` renderizan shell con el menú correcto.
- [ ] **L-05 · Kit de componentes** — cards, tablas, paginación, badges de estado, modales, toasts, formularios, empty/loading/error, confirmación. · *DoD:* catálogo interno en una vista demo.
- [ ] **L-06 · Rutas y controlador de panel** — `app/routes/panels.php` + `PanelController.php` que solo resuelve vista y permisos; páginas 403/404. · *DoD:* una ruta demo por rol renderiza su vista.
- [ ] **L-07 · JS base del panel** — `apiFetch()` con CSRF, manejo de 401/403/422, toasts, modales y helpers de tabla. · *DoD:* un fetch demo muestra éxito y error de validación.

**Criterio de salida Fase 0:** backend base, contratos publicados, seed demo y mocks listos. El login real y los paneles se completan en la Fase 1 (M-12, L-08, L-09).

---

### Fase 1 — Núcleo: base de datos, auth y correo

**Salida:** base de datos completa, login real para los 6 roles, correo de recuperación funcionando en Mailpit y paneles con sesión real.

#### Matias

- [x] **M-07 · Migraciones 001–004** — usuarios, pacientes, médicos, especialidades, `medico_especialidad`, `recepcionista_medico`, horarios, bloqueos y cupos. · *Deps:* M-02.
- [x] **M-08 · Migraciones 005–008** — citas, historial de estados, fichas, consultas, recetas/detalle, documentos, órdenes, pagos, auditoría y cola de correos. · *Deps:* M-07.
- [x] **M-09 · Constraints, índices y triggers** — unicidad de CI/códigos, índice anti-superposición, checks, `updated_at`, generación de códigos y trigger de auditoría `SECURITY DEFINER`. · *Deps:* M-08.
- [x] **M-10 · RLS y settings** — policies por rol, `app.usuario_id`, `app.rol`, `app.ip`; el wrapper de DB setea las variables por request. · *Deps:* M-09. · *DoD:* cada rol ve solo lo suyo; admin ve todo.
- [x] **M-11 · Seeders demo + reset** — especialidades (3 destacadas), médicos, horarios con cupos 50/50, pacientes, citas pasadas/futuras, fichas y pagos; `db:reset` y `database/README.md` con ERD. · *Deps:* M-10.
- [x] **M-12 · Auth + middlewares** — login por usuario/email/CI, logout, bloqueo a los 3 intentos, reset de 30 min, activación de paciente; `AuthMiddleware` y `RoleMiddleware` con redirección por rol. · *Deps:* M-07, M-10.
- [x] **M-13 · Correo** — `MailService` con PHPMailer, Brevo y Mailpit local; fallback a log/cola; plantillas de reset, confirmación y cancelación. · *Deps:* M-12. · *DoD:* el reset llega a Mailpit y el flujo se completa.
- [x] **M-14 · Pruebas de auth** — 6 roles, bloqueo/desbloqueo, token expirado, activación, sesión y redirects; `GET /api/me` y `GET /api/csrf` para el panel. · *Deps:* M-12, M-13.

#### Lucas

- [ ] **L-08 · Vistas de autenticación** — login, recuperar, reset y activar; validación JS, estados de error y mensajes de bloqueo. · *Deps:* L-04, M-04. · *DoD:* funcionan con mock.
- [ ] **L-09 · Integración de auth** — conecta las vistas a los endpoints reales, redirección por rol y manejo de errores. · *Deps:* M-12, M-13.
- [ ] **L-10 · Layout final con sesión real** — usuario, menú, CSRF, flashes y logout. · *Deps:* M-12.
- [ ] **L-11 · QA de auth y páginas de error** — 403/404/500, sesión expirada, cuenta bloqueada, formularios inválidos. · *Deps:* L-09, L-10.
- [ ] **L-12 · Pass visual** — paleta azul médico/teal, tipografía, logo placeholder, responsive del shell. · *Deps:* L-10.

**Criterio de salida Fase 1:** login real de los 6 roles, recuperación por correo en Mailpit y paneles con sesión válida.

---

### Fase 2 — Landing pública + panel administrador

**Salida:** landing con buscador y reserva web; panel admin con catálogos, citas, reportes y auditoría.

#### Matias

- [x] **M-15 · APIs públicas** — especialidades activas, médicos por especialidad y disponibilidad web; validaciones y solo activos. · *Deps:* M-11.
- [x] **M-16 · Reserva web** — `POST /api/public/reservar` con sesión de paciente, cupo web, revalidación de disponibilidad, creación de cita + ficha web + correo. · *Deps:* M-15, M-13.
- [x] **M-17 · Landing backend** — rutas `/`, `/reservar`, 404, layout `base.php`, SEO/OG y assets. · *Deps:* M-03. · *DoD:* landing servida por PHP.
- [x] **M-18 · Landing UI** — hero editorial, nosotros, especialidades, médicos, noticias, contacto, footer; rediseño responsive y accesible. · *Deps:* M-17. · *DoD:* landing, kiosco y 404 verificados a 360–1440 px, sin overflow horizontal.
- [x] **M-19 · Landing JS** — buscador, filtros, directorio y wizard de reserva en 3 pasos con validaciones, carga, éxito y manejo de `401`/`409`/`422`. · *Deps:* M-15, M-16, M-18. · *DoD:* reserva real, búsqueda/filtros y alertas verificados en navegador; `bin/test-public.php` y `bin/test-auth.php` en verde.
- [x] **M-20 · APIs admin** — usuarios (CRUD, activar, desbloquear, roles), médicos/especialidades (CRUD, asignaciones, QR, restricciones), horarios/cupos/bloqueos. · *Deps:* M-11, M-12. · *DoD:* CRUDs, permisos y CSRF verificados con `bin/test-admin.php`; `bin/test-public.php` y `bin/test-auth.php` en verde.
- [x] **M-21 · APIs admin de citas, reportes y auditoría** — listados con filtros, detalle, agenda, historial de paciente, reportes agregados, dashboard admin y bitácora. Las escrituras de citas (reprogramar/cancelar) quedan para M-27. · *Deps:* M-20. · *DoD:* permisos por rol, filtros, agregados y auditoría verificados con `bin/test-reportes.php`; `bin/test-admin.php`, `bin/test-public.php` y `bin/test-auth.php` en verde.
- [x] **M-22 · Pruebas landing + admin** — reserva real, cupo web, correo, CRUDs y reportes con datos seed. · *Deps:* M-19, M-21. · *DoD:* flujo landing → reserva → cupo → correo → admin → reportes y CRUD temporal verificados con `bin/test-integracion.php`; `bin/test-auth.php`, `bin/test-public.php`, `bin/test-admin.php` y `bin/test-reportes.php` en verde.

#### Lucas

- [ ] **L-13 · Dashboard admin** — contadores, gráficos y accesos rápidos desde `/api/dashboard/admin`. · *Deps:* L-07, M-21.
- [ ] **L-14 · Usuarios** — listado, filtros, alta/edición, activar/desactivar, desbloquear y asignar rol. · *Deps:* M-20.
- [ ] **L-15 · Médicos** — CRUD, especialidades, subida de QR, activar/desactivar y asignar recepcionista. · *Deps:* M-20.
- [ ] **L-16 · Especialidades** — CRUD, restricciones de sexo/edad y destacadas. · *Deps:* M-20.
- [ ] **L-17 · Horarios** — grilla semanal, cupos web/kiosco, bloqueos de agenda. · *Deps:* M-20.
- [ ] **L-18 · Citas (admin)** — listado con filtros, detalle, reprogramar y cancelar. · *Deps:* M-21 (lectura), M-27 (escrituras).
- [ ] **L-19 · Reportes + auditoría** — tablas/gráficos, exportación CSV opcional y diff antes/después de auditoría. · *Deps:* M-21.

**Criterio de salida Fase 2:** un paciente reserva desde la landing y un admin gestiona catálogos y ve reportes.

---

### Fase 3 — Kiosco + panel de recepción

**Salida:** kiosco emitiendo fichas con QR y recepción gestionando pacientes, citas, asistencia y pagos.

#### Matias

- [x] **M-23 · Token de kiosco** — HMAC, `KioskMiddleware`, expiración de 10 min y `POST /api/kiosco/sesion`. · *Deps:* M-03. · *DoD:* token firmado con TTL configurable de 10 min, validación por header `X-Kiosk-Token` y endpoint de sesión con device key verificados con `bin/test-kiosco.php`; suites previas en verde.
- [x] **M-24 · APIs de kiosco** — identificar por CI, alta rápida, especialidades, médicos, disponibilidad kiosco y reserva con revalidación de cupo. La emisión de ficha pasa a M-25. · *Deps:* M-23, M-15, M-16. · *DoD:* identificación, alta sin cuenta, filtrado por sexo/edad, cupo kiosco exclusivo y reserva con `origen=kiosco` verificados con `bin/test-kiosco-api.php`; suites previas en verde.
- [ ] **M-25 · FichaService** — código `FIC-####`, código de acceso de 4 dígitos, QR, confirmación de cita y datos para impresión. · *Deps:* M-24.
- [ ] **M-26 · Kiosco UI** — bienvenida, CI, alta rápida, especialidad, médico, horario, verificación facial simulada, ficha con QR/print y reset por inactividad. Touch-friendly. · *Deps:* M-24, M-25.
- [ ] **M-27 · APIs de recepción** — pacientes (CRUD, búsqueda, historial, desactivar), citas (crear, reprogramar, cancelar), agenda, asistencia/inasistencia y reconsultas. · *Deps:* M-11, M-21.
- [ ] **M-28 · APIs de recepción: fichas y pagos** — búsqueda/reimpresión de ficha, registrar/listar pagos. · *Deps:* M-25, M-27.
- [ ] **M-29 · Pruebas kiosco + recepción** — token expirado, CI duplicada, cupo kiosco, ficha, transiciones de estado, reconsulta y pagos. · *Deps:* M-26, M-28.

#### Lucas

- [ ] **L-20 · Dashboard recepción** — citas del día, en espera, fichas y accesos rápidos. · *Deps:* M-27.
- [ ] **L-21 · Pacientes** — búsqueda, alta/edición, desactivar e historial. · *Deps:* M-27.
- [ ] **L-22 · Citas de recepción** — wizard paciente/especialidad/médico/fecha/slot, aviso de duplicado, reprogramar y cancelar. · *Deps:* M-27.
- [ ] **L-23 · Agenda diaria/semanal** — filtros por médico/fecha/estado y acciones de asistencia. · *Deps:* M-27.
- [ ] **L-24 · Reconsultas + fichas** — modal de reconsulta (ausencia/seguimiento) y consulta/reimpresión de ficha. · *Deps:* M-27, M-28.
- [ ] **L-25 · Pagos** — registrar pago, formulario de tarjeta solo visual, listado con filtros. · *Deps:* M-28.

**Criterio de salida Fase 3:** un paciente saca ficha en el kiosco y recepción completa el flujo hasta el pago.

---

### Fase 4 — Panel médico + portal del paciente

**Salida:** médico atiende, registra historia, emite pedidos y cobra; paciente ve sus citas e historial y cancela.

#### Matias

- [ ] **M-30 · APIs del médico** — agenda, resumen diario, pacientes atendidos e historia clínica. · *Deps:* M-27.
- [ ] **M-31 · Consultas y pedidos** — consultas con signos vitales, recetas, documentos y órdenes; vistas imprimibles; URL `wa.me` y página pública de pedido. · *Deps:* M-30.
- [ ] **M-32 · Pagos y QR del médico** — registrar/listar pagos propios y subir/ver QR. · *Deps:* M-28.
- [ ] **M-33 · APIs del portal paciente** — mis citas, historial, perfil, cambio de contraseña y cancelación con la regla del día anterior. · *Deps:* M-21.
- [ ] **M-34 · Dashboards y reportes** — métricas admin/médico y reportes faltantes. · *Deps:* M-21, M-30.
- [ ] **M-35 · Pruebas médico + portal** — consulta completa, receta, WhatsApp, pago, historial y cancelación. · *Deps:* M-31, M-32, M-33.

#### Lucas

- [ ] **L-26 · Dashboard médico** — contadores del día, pacientes en espera y próximas citas. · *Deps:* M-30.
- [ ] **L-27 · Agenda del médico** — día/semana, calendario y resumen diario. · *Deps:* M-30.
- [ ] **L-28 · Pacientes e historia** — pacientes atendidos y timeline de historia clínica. · *Deps:* M-30.
- [ ] **L-29 · Consulta** — motivo, anamnesis, signos vitales, examen, diagnóstico, tratamiento e indicaciones. · *Deps:* M-31.
- [ ] **L-30 · Recetas, documentos y órdenes** — alta, listado, vista imprimible y modal de envío por WhatsApp. · *Deps:* M-31.
- [ ] **L-31 · Pagos y QR** — registrar/listar pagos y gestionar QR propio. · *Deps:* M-32.
- [ ] **L-32 · Portal del paciente** — dashboard, mis citas y cancelación. · *Deps:* M-33.
- [ ] **L-33 · Portal: historial y perfil** — timeline, detalle, perfil y cambio de contraseña. · *Deps:* M-33.

**Criterio de salida Fase 4:** flujo completo cita → ficha → atención → receta → pago, y portal del paciente operativo.

---

### Fase 5 — Integración, QA y deploy

**Salida:** demo estable de punta a punta, deployada y documentada.

#### Matias

- [ ] **M-36 · Hardening de integración** — CSRF en todas las mutaciones, guards por rol, uploads, logs, headers, expiración de sesión y errores genéricos. · *Deps:* todas las APIs.
- [ ] **M-37 · Deploy** — cPanel con `pdo_pgsql`, Neon/Railway con SSL, Brevo, HTTPS, variables de entorno y backups. · *Deps:* M-36.
- [ ] **M-38 · Performance** — índices, paginación, evitar N+1 y cache donde aplique. · *Deps:* M-36.
- [ ] **M-39 · QA final + demo** — seed de demo, guion de presentación, README y contrato final actualizado. · *Deps:* M-37, M-38.

#### Lucas

- [ ] **L-34 · Responsive y accesibilidad** — 375/768/1280, teclado, foco, labels y contraste. · *Deps:* todas las vistas.
- [ ] **L-35 · Estilos de impresión** — ficha, receta, documento y orden. · *Deps:* L-30.
- [ ] **L-36 · E2E por rol** — admin, recepción, recepción de médico, médico y paciente con datos seed. · *Deps:* M-39, L-34.
- [ ] **L-37 · Pulido y handoff** — loading/empty/error, confirmaciones, screenshots y documentación del panel. · *Deps:* L-36.

**Criterio de salida Fase 5:** demo completa, sin errores de consola, con usuarios y datos de prueba.

---

## 5. Arranque de Lucas (primer día)

> Lucas: cuando entres, haz esto en orden. No empieces por una pantalla al azar.

1. `git pull` y lee este documento completo.
2. Lee `docs/specs/00-vision-general.md` y `01-autenticacion.md`; hojea `02`–`15`.
3. Ejecuta **L-01** y anota dudas.
4. Haz **L-02** (spike de Gentelella) y **L-03** (pipeline de assets).
5. Espera o revisa **M-04/M-05**; si no están, usa `docs/specs/` y `gentelella/production/*.html` como referencia.
6. Construye **L-04** (layout) y **L-05** (componentes) con datos mock.
7. Abre un PR borrador con una captura del shell.
8. Reporta bloqueos con: tarea, archivo, endpoint o dato que falta.

**No toques** `database/`, `app/core/`, `app/models/`, `app/services/`, `app/controllers/Api/`, `public/index.php` ni `app/views/landing|kiosco`.

---

## 6. Arranque de Matias (primer día)

1. **M-01 → M-06** en orden. La Fase 0 es bloqueante para Lucas.
2. Publica el contrato y avisa a Lucas que está congelado.
3. Sigue con **M-07 → M-14** (DB, RLS, auth y correo).
4. Recién después empieza landing/kiosco; así Lucas no queda esperando el contrato.
5. Revisa los PR de Lucas priorizando: permisos, CSRF y consumo correcto de endpoints.

---

## 7. Flujo Git

- `main` siempre debe quedar demo-able.
- Ramas: `matias/m07-migraciones`, `lucas/l04-layout`.
- Antes de PR: `git pull --rebase origin main`.
- PR pequeño (una tarea o dos), con:
  - qué hace,
  - cómo probarlo,
  - captura si es vista,
  - endpoints/migraciones tocados.
- Revisión cruzada obligatoria; squash merge.
- Tags por fase: `fase-0`, `fase-1`, etc.
- Commits: `feat(panel): ...`, `feat(api): ...`, `db: ...`, `fix(api): ...`, `docs: ...`.

**Nunca commitear:** `.env`, `vendor/`, `node_modules/`, `gentelella/dist/`, `public/uploads/*`, logs.

---

## 8. Definition of Done

### Backend (Matias)

- [ ] La migración corre desde cero y el seeder deja datos utilizables.
- [ ] RLS probado con cada rol.
- [ ] Endpoint documentado en el contrato, con envelope, códigos HTTP y CSRF.
- [ ] Validación de servidor; sin errores técnicos expuestos.
- [ ] Probado con curl/Postman o PHPUnit.
- [ ] Logs internos y documentación actualizados.

### Panel (Lucas)

- [ ] Usa el endpoint real o el mock documentado; **cero SQL**.
- [ ] Guard de rol funcionando; 401 redirige a login y 403 muestra página.
- [ ] Loading, empty y error states.
- [ ] Validación cliente + muestra errores de servidor.
- [ ] Responsive en 375/768/1280; navegación por teclado básica.
- [ ] Sin errores de consola; CSRF en mutaciones.
- [ ] Captura en el PR.

---

## 9. Bloqueos y comunicación

- Un bloqueo se reporta con: **tarea**, **archivo**, **endpoint/dato que falta** y **qué se intentó**.
- Si falta un endpoint, no se inventa: se agrega al contrato y se implementa como tarea de Matias.
- Si falta un campo de BD, se pide con: tabla, campo, tipo y motivo.
- Si un cambio afecta a ambos, se acuerda antes de codificar.
- Daily de 10 minutos: hecho / siguiente / bloqueo.

---

## 10. Decisiones pendientes y riesgos

| # | Tema | Decisión / riesgo | Dueño |
|---|---|---|---|
| 1 | Gentelella v4 | Es Vite + vanilla JS, no un template PHP. Hay que definir assets compilados vs. convertir HTML. | Lucas (L-02) |
| 2 | Registro público de paciente | Las specs definen activación desde kiosco, pero la landing habla de "registrarse". Definir si crea paciente+usuario o solo activa existentes. | Matias |
| 3 | RLS con un solo usuario de BD | Hay que setear `app.usuario_id`/`app.rol` por request o las policies no funcionan. | Matias (M-10) |
| 4 | Brevo free tier | Límite de correos; usar Mailpit en desarrollo y cola/log en fallos. | Matias (M-13) |
| 5 | `pdo_pgsql` en cPanel | Verificarlo antes de la fecha de entrega; si no está, cambiar de hosting. | Matias (M-37) |
| 6 | QR | Elegir librería PHP o JS para ficha/pedidos; definir antes de M-25. | Matias |
| 7 | Zona horaria | Fijar `America/La_Paz` en PHP y Postgres para evitar citas corridas. | Matias |
| 8 | Recepcionista de médico | Un usuario puede tener varios médicos; la sesión debe llevar `medico_ids`. | Matias |
| 9 | Alcance | Historia clínica, reportes y auditoría son grandes; priorizar RF01–RF13 y dejar extras al final. | Ambos |
| 10 | Uploads | Definir `public/uploads/` con permisos y nombres aleatorios; no servir archivos sensibles. | Matias |

---

## 11. Resumen de dependencias críticas

```
M-01/M-02/M-03 ──> M-04/M-05 ──> L-04/L-06/L-07 ──> paneles
        │
        └──> M-07..M-10 (DB/RLS) ──> M-12/M-13 (auth/mail) ──> L-09/L-10
                                      │
                                      └──> M-15..M-21 (landing/admin APIs) ──> L-13..L-19
                                                   │
                                                   └──> M-23..M-28 (kiosco/recepción) ──> L-20..L-25
                                                                │
                                                                └──> M-30..M-34 (médico/portal) ──> L-26..L-33
```

**Camino crítico:** contrato → DB/RLS → auth → APIs de cada módulo → vistas del módulo → QA.

---

## 12. Primer mensaje para Lucas

> Lucas, el reparto está en `docs/equipo/PLAN-TRABAJO.md`. Empieza por **L-01, L-02 y L-03**; luego **L-04** (layout) y **L-05** (componentes) con mocks. No toques `database/`, `app/core/`, `app/models/`, `app/services/`, `app/controllers/Api/`, `public/index.php` ni las vistas de landing/kiosco. Si falta un endpoint o un campo, lo pides y se agrega al contrato. Tu primer PR debe ser el shell del panel con una captura.
