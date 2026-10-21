# SPEC 01 — Autenticación y Control de Acceso (RF01)

> Módulo: Seguridad / Sesiones · Prioridad: **Alta (núcleo, Presentación 1)**
> Depende de: `99-modelo-datos-rls.md`

---

## 1. Objetivo

Permitir que usuarios autorizados inicien sesión, identifiquen su rol, accedan solo a las opciones permitidas y cierren sesión de forma segura. Incluye bloqueo por intentos fallidos y recuperación de contraseña por correo.

## 2. Alcance

- Login con **CI o correo** (usuarios internos usan usuario/correo; pacientes usan **CI**).
- Sesión segura con cookie `HttpOnly`, `Secure`, `SameSite=Lax`.
- Identificación de rol y menú dinámico según permisos.
- Logout.
- Middleware que bloquea páginas internas sin sesión válida.
- Bloqueo de cuenta tras **3 intentos fallidos** (desbloqueo manual del admin).
- Recuperación de contraseña por **token** enviado por correo (expira 30 min).
- Activación de cuenta de paciente registrado en kiosco (CI + teléfono).

**Fuera de alcance:** 2FA, login social, biometría en login de web/admin.

## 3. Actores

Todos los roles. El paciente también puede activar cuenta desde la landing.

## 4. Modelo de datos

### Tabla `usuarios`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | identity PK | |
| `username` | varchar(60) | único, nullable |
| `email` | varchar(120) | único, nullable |
| `password_hash` | varchar(255) | `password_hash()` |
| `rol` | enum | `super_admin`,`admin`,`recepcionista`,`recepcionista_medico`,`medico`,`paciente` |
| `activo` | boolean | default true |
| `intentos_fallidos` | smallint | default 0 |
| `bloqueado` | boolean | default false |
| `token_reset` | varchar(128) | nullable |
| `token_reset_expira` | timestamptz | nullable |
| `ultimo_acceso` | timestamptz | nullable |
| `created_at`,`updated_at` | timestamptz | |

### Tabla `pacientes` (relación)
- `pacientes.usuario_id` (FK nullable) — se llena al **activar cuenta**.
- `pacientes.ci` (único), `pacientes.telefono` — usados en activación.

> La tabla `auditoria` **no** registra logins (solo CRUD de catálogos, ver RX13).

## 5. Endpoints (API / rutas)

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| GET | `/login` | Formulario de login | pública |
| POST | `/auth/login` | Validar credenciales | pública |
| POST | `/auth/logout` | Cerrar sesión | sesión |
| GET | `/recuperar` | Formulario "olvidé contraseña" | pública |
| POST | `/auth/recuperar` | Generar token y enviar correo | pública |
| GET | `/reset?token=...` | Formulario nueva contraseña | pública |
| POST | `/auth/reset` | Guardar nueva contraseña | pública |
| GET | `/activar` | Formulario activación (CI + teléfono) | pública |
| POST | `/auth/activar` | Verificar y crear credenciales | pública |

## 6. Reglas de negocio

| Código | Regla |
|---|---|
| RF01-A | Credenciales válidas inician sesión; inválidas suman intento fallido. |
| RF01-B | Al 3.er intento fallido → `bloqueado=true`; se rechaza aunque la clave sea correcta. |
| RF01-C | Solo el admin/super_admin puede desbloquear (`usuarios.bloqueado=false`, `intentos_fallidos=0`). |
| RF01-D | Login exitoso resetea `intentos_fallidos=0` y actualiza `ultimo_acceso`. |
| RF01-E | Usuario `activo=false` no puede iniciar sesión. |
| RF01-F | Token de recuperación: aleatorio, un solo uso, expira en 30 min. |
| RF01-G | Activación solo si existe `pacientes` con la CI y teléfono coincidentes y sin `usuario_id` asignado. |
| RF01-H | Tras activar, el paciente inicia sesión con **CI + contraseña**. |
| RF01-I | Todas las páginas internas exigen sesión válida (middleware). |
| RF01-J | El menú y las rutas se restringen por rol (middleware de roles). |

## 7. Validaciones

**Servidor (obligatorio):**
- Usuario/CI y contraseña no vacíos.
- Formato de CI (numérico/alfanumérico configurable), email válido.
- Contraseña nueva ≥ 8 caracteres, con al menos 1 letra y 1 número.
- Confirmación de contraseña coincide.
- Token existente y no expirado.

**Cliente (JS):**
- Campos requeridos, longitud mínima, coincidencia de contraseñas.

## 8. Flujos principales

### 8.1 Login interno (admin, recepción, médico)
```
[GET /login] → formulario
[POST /auth/login] 
  → buscar usuario por username/email
  → si no existe → error genérico (no revelar cuál falló)
  → si bloqueado → mensaje "cuenta bloqueada, contacte al administrador"
  → password_verify()
     → false → intentos_fallidos++ ; si >=3 → bloqueado=true ; error
     → true  → session_regenerate_id(); guardar sesión {usuario_id, rol}
              → redirigir al panel según rol
```

### 8.2 Login paciente
```
[POST /auth/login] con CI
  → buscar pacientes.ci → usuarios asociado
  → password_verify() → sesión rol=paciente → /portal
  (si no tiene cuenta → mensaje "active su cuenta" con enlace a /activar)
```

### 8.3 Recuperación
```
[POST /auth/recuperar] email
  → si existe: token = bin2hex(random_bytes(32)); expira en 30 min
  → enviar correo (PHPMailer/Brevo con fallback a log)
  → mensaje genérico "si el correo existe, recibirás instrucciones"
[GET /reset?token] → valida token y expiración
[POST /auth/reset] → password_hash() → invalida token → redirige a login
```

### 8.4 Activación de paciente (kiosco → web)
```
[POST /auth/activar] ci + telefono
  → buscar pacientes (ci, telefono) sin usuario_id
  → crear usuarios (rol=paciente, password_hash, email opcional)
  → pacientes.usuario_id = usuarios.id
  → mensaje "cuenta activada, ya puede iniciar sesión"
```

## 9. UI / Pantallas

| Vista | Ruta | Notas |
|---|---|---|
| Login | `/login` | Logotipo, CI/usuario, contraseña, enlaces "recuperar" y "activar cuenta" |
| Recuperar | `/recuperar` | Campo email |
| Reset | `/reset` | Nueva contraseña + confirmación |
| Activar | `/activar` | CI + teléfono → crear contraseña + email (opcional) |
| Menú lateral | layout | Ítems filtrados por rol |

**Menú por rol (resumen):**
- `super_admin`/`admin`: Dashboard, Usuarios, Médicos, Especialidades, Horarios, Citas, Reportes, Auditoría.
- `recepcionista`: Dashboard, Pacientes, Citas, Reconsultas, Agenda, Fichas.
- `recepcionista_medico`: su médico (Agenda, Pacientes atendidos, Asistencia, Pagos, WhatsApp).
- `medico`: Dashboard, Agenda, Pacientes, Consultas, Historia clínica, Recetas/Documentos/Órdenes, Pagos, QR.
- `paciente`: Portal (mis citas, historial, perfil, cancelar).

## 10. Criterios de aceptación

- [ ] Login válido inicia sesión y redirige según rol.
- [ ] 3 intentos fallidos bloquean la cuenta y el admin puede desbloquear.
- [ ] Páginas internas sin sesión redirigen a `/login` (prueba #9 del enunciado).
- [ ] Recuperación envía correo con token; el token expira a los 30 min.
- [ ] Paciente registrado en kiosco puede activar cuenta con CI + teléfono.
- [ ] Logout destruye la sesión y no permite volver con "atrás".
- [ ] El menú cambia según el rol.

## 11. Errores y respuestas

| Caso | Mensaje al usuario |
|---|---|
| Credenciales inválidas | "CI/usuario o contraseña incorrectos." (genérico) |
| Cuenta bloqueada | "Cuenta bloqueada por intentos fallidos. Contacte al administrador." |
| Cuenta inactiva | "Cuenta inactiva. Contacte al administrador." |
| Token inválido/expirado | "El enlace expiró. Solicite uno nuevo." |
| Activación sin coincidencia | "No encontramos un registro con esos datos." |
| Error SMTP | "Solicitud registrada. Revise su correo en unos minutos." (email en cola) |

## 12. Tareas de implementación

1. Tabla `usuarios` + migración + seeder de admin.
2. Core: `Router`, `Request`, `Session`, `DB` (PDO), helper `e()`, `csrf()`.
3. `AuthController` (login, logout, recuperar, reset, activar).
4. `AuthMiddleware` + `RoleMiddleware`.
5. `UsuarioModel` (buscar, crear, actualizar intentos, bloquear, token).
6. `MailService` (PHPMailer + Brevo + fallback log).
7. Vistas login/recuperar/reset/activar + layout con menú por rol.
8. Validaciones JS en los formularios.

## 13. Pruebas

| # | Prueba | Resultado esperado |
|---|---|---|
| 1 | Login correcto | Acceso al panel del rol |
| 2 | 3 intentos fallidos | Cuenta bloqueada |
| 3 | Desbloqueo por admin | Usuario puede volver a entrar |
| 4 | Página interna sin sesión (prueba #9 enunciado) | Redirección a login |
| 5 | Recuperación por correo | Recibe enlace y cambia clave |
| 6 | Activación desde kiosco | Paciente inicia sesión con CI |
