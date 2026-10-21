# SPEC 00 — Visión General, Convenciones y Arquitectura

> Proyecto: **Sistema Web de Gestión de Citas Médicas — Clínica Vida Salud**
> Stack: PHP (MVC manual) + PostgreSQL + HTML/CSS + JS
> Este documento es la **base contractual** de todas las specs por módulo. Cada módulo se implementa siguiendo su spec y esta spec global.

---

## 1. Objetivo del sistema

Centralizar la información y automatizar la gestión de citas médicas de la Clínica Vida Salud: registro de pacientes, administración de médicos y especialidades, definición de horarios, programación/reprogramación/cancelación de citas, control de asistencia, historia clínica, emisión de fichas (web y kiosco), reconsultas, pedidos por WhatsApp, pagos y reportes.

## 2. Alcance

**Incluido (núcleo del enunciado)**
- RF01–RF13: autenticación, pacientes, médicos, especialidades, horarios, citas, duplicados, reprogramación, cancelación, asistencia, agenda, historial, reportes.
- Landing pública informativa + reserva online.
- Kiosco (iPad) para emisión de fichas.
- Cupos 50% web / 50% kiosco.
- Reconsultas (ausencia del médico y seguimiento).
- Historia clínica, recetas, documentos, órdenes de estudio.
- Envío de pedidos por WhatsApp (enlace + código de acceso).
- Registro de pagos (monto y método).
- Auditoría de cambios de catálogos (bitácora).

**Fuera de alcance**
- Pasarela de pago real (el formulario Visa/Mastercard es solo UI).
- Matching biométrico real (la verificación facial es simulada; identidad por CI).
- Envío real por WhatsApp Business API (solo deep link con mensaje precargado).
- Integración HL7/FHIR ni interoperabilidad externa.

## 3. Actores / Roles

| Rol | Código | Descripción |
|---|---|---|
| Super administrador | `super_admin` | Matias y Lucas. Acceso total, incluida auditoría. |
| Administrador | `admin` | Configura catálogos, usuarios, reportes. |
| Recepcionista | `recepcionista` | Gestiona citas y pacientes de toda la clínica. |
| Recepcionista de médico | `recepcionista_medico` | Limitado a los médicos que tenga asignados. |
| Médico | `medico` | Atiende su agenda, historia clínica, recetas, pagos. |
| Paciente | `paciente` | Reserva y consulta sus propias citas y pedidos. |

## 4. Arquitectura general

### 4.1 Capas (PHP MVC manual)
```
petición HTTP
   └─> Router frontal (public/index.php)
        └─> Middleware (auth, roles) 
             └─> Controller (entrada, validación, permisos)
                  └─> Service (reglas de negocio, transacciones)
                       └─> Model (SQL parametrizado con PDO)
                            └─> PostgreSQL (RLS)
```

### 4.2 Tipo de aplicación — enfoque mixto
- **Paneles (Gentelella):** HTML renderizado con PHP (con sesión) + `fetch` a la API para datos y acciones.
- **Landing y Kiosco:** HTML/CSS/JS propio (misma paleta, kiosco touch-friendly).
- **API REST** (sin versionado) para operaciones dinámicas: horarios, disponibilidad, búsquedas, estados.

### 4.3 Autenticación
- **Paneles y landing:** sesión PHP con cookie (`HttpOnly`, `Secure`, `SameSite=Lax`).
- **Kiosco:** token firmado (HMAC) emitido al iniciar flujo, dispositivo público.
- **API pública de pedidos:** sin sesión, validación por `codigo + codigo_acceso`.

## 5. Estructura de carpetas

```
Clinica/
├── public/                     # raíz web (document root)
│   ├── index.php               # router frontal
│   ├── .htaccess               # rewrite → index.php
│   ├── assets/                 # css, js, img (landing/kiosco)
│   └── uploads/                # qr medicos, resultados, logos
├── app/
│   ├── config/                 # config.php, database.php, mail.php
│   ├── core/                   # Router, Request, Response, View, DB (PDO)
│   ├── middlewares/            # AuthMiddleware, RoleMiddleware, KioskMiddleware
│   ├── controllers/            # API y paneles
│   ├── services/               # DisponibilidadService, CitasService, ...
│   ├── models/                 # acceso a datos (PDO)
│   └── views/
│       ├── layouts/            # base.php, gentelella.php
│       ├── landing/            # vistas públicas
│       ├── kiosco/             # vistas del kiosco
│       ├── admin/              # paneles Gentelella
│       ├── medico/
│       ├── recepcion/
│       └── paciente/
├── database/
│   ├── migrations/             # 001_..., 002_...
│   └── seeders/
├── docs/
│   └── specs/                  # estos documentos
├── docker-compose.yml          # desarrollo (php-apache + postgres)
└── .env.example
```

## 6. Convenciones de código

- **PHP 8.1+**; sin framework (requisito del enunciado).
- Autoload PSR-4 simple propio (`spl_autoload_register`).
- Nombres: `snake_case` en BD, `camelCase` en métodos PHP, `PascalCase` en clases.
- Todas las consultas con **PDO preparado** (nunca concatenar SQL).
- Contraseñas con `password_hash()` / verificación con `password_verify()`.
- Vistas **escapan output** con `htmlspecialchars()` (helper `e()`).
- Todo endpoint que muta estado usa **CSRF token** (excepto kiosco, protegido por token firmado).
- Errores: nunca mostrar detalle técnico al usuario; log interno y mensaje genérico.

## 7. Convenciones de base de datos

- **PK:** `id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY`.
- **Códigos visibles:** `codigo` único con prefijo por entidad (`PAC-0001`, `MED-0001`, `CM-0001`, `FIC-0001`, `RSV-XXXXXX`, `DOC-XXXXXX`, `ORD-XXXXXX`).
- **Auditoría temporal:** `created_at TIMESTAMPTZ DEFAULT now()`, `updated_at`.
- **Borrado lógico:** columna `estado` (`activo`/`inactivo`) o `activo BOOLEAN`. Nunca DELETE FÍSICO de entidades con historial.
- **Estados:** `ENUM` nativo de Postgres para cita, origen, rol, sexo, etc.
- **JSONB** para `signos_vitales` y para `datos_antes`/`datos_despues` de auditoría.
- Nombres de tablas en plural, `snake_case`; FKs `<entidad>_id`.

## 8. Reglas de negocio transversales (RN)

| Código | Regla | Módulo(s) |
|---|---|---|
| RN01 | Paciente con identificador único (CI única). | Pacientes |
| RN02 | Médico con identificador único (código y registro profesional únicos). | Médicos |
| RN03 | Cita asociada a paciente y médico válidos. | Citas |
| RN04 | Médico debe estar activo para recibir citas nuevas. | Citas |
| RN05 | Cita debe pertenecer al horario de atención del médico. | Citas |
| RN06 | No se permiten citas en fechas/horas pasadas. | Citas |
| RN07 | Un médico no puede tener citas superpuestas. | Citas |
| RN08 | Duración de la cita = configuración (bloques de 30 min). | Citas |
| RN09 | Citas canceladas no ocupan disponibilidad. | Citas |
| RN10 | Citas atendidas conservan su registro histórico. | Citas |
| RN11 | No se eliminan pacientes/médicos con historial; se desactivan. | Pacientes/Médicos |
| RN12 | Solo usuarios autorizados por rol realizan cada operación. | Todos |
| RN13 | Disponibilidad se verifica en el servidor en programar/reprogramar. | Citas |
| RN14 | Persistencia en PostgreSQL. | Todos |

**Reglas de ampliación (acordadas):**
| Código | Regla |
|---|---|
| RX01 | Cupos 50% web / 50% kiosco por franja de horario. |
| RX02 | Restricción de sexo por especialidad (ginecología → femenino; resto ambos). |
| RX03 | Restricción de edad por especialidad (pediatría 0–12; geriatría 60+; resto sin límite). |
| RX04 | Cancelación solo hasta el día anterior a la cita; el día de la cita no se permite. |
| RX05 | Reconsulta por ausencia del médico: reagenda con prioridad y origen `reconsulta`. |
| RX06 | Reconsulta de seguimiento al marcar `atendida` (misma semana siguiente sugerida). |
| RX07 | Reconsulta puede tomar cupo aunque el canal esté agotado (solo vía recepción). |
| RX08 | Ficha = ticket de la cita; sin ficha no se atiende. |
| RX09 | Bloqueo de login tras 3 intentos fallidos; desbloqueo manual del admin. |
| RX10 | Recuperación de contraseña por enlace con token temporal (30 min). |
| RX11 | Pedidos se envían por WhatsApp con enlace + código de acceso (uno por pedido). |
| RX12 | Paciente de kiosco existe sin cuenta; activa cuenta con CI + teléfono. |
| RX13 | Auditoría con IP, datos antes/después, solo CRUD de catálogos. |

## 9. Seguridad

- **RLS** en PostgreSQL (ver `99-modelo-datos-rls.md`).
- Bloqueo de cuenta por intentos fallidos.
- Tokens de recuperación con expiración.
- CSRF en formularios de paneles/landing.
- Token firmado (HMAC) para el kiosco.
- `pgsql` con `sslmode=require` hacia la BD en la nube (Neon/Railway).
- Validación **cliente (JS) + servidor (PHP)**.
- Sanitización y consultas parametrizadas (anti SQL-injection).

## 10. Infraestructura y deploy

- **Desarrollo:** `docker-compose` con `php:8.2-apache` (con `pdo_pgsql`) + `postgres:16`.
- **Producción:** hosting compartido cPanel (solo PHP/web) + PostgreSQL en Neon o Railway.
  - ⚠️ Verificar que el cPanel tenga `pdo_pgsql` habilitado.
- **HTTPS obligatorio** (cámara facial del iPad y cookies seguras).
- **iPad kiosco:** accede a `https://<dominio>/kiosco` por WiFi/datos.

## 11. Correo electrónico (SMTP)

- Proveedor: **Brevo free tier** (SMTP autenticado, sin dominio propio).
- Librería: **PHPMailer**.
- Configuración en `app/config/mail.php` (host, puerto, usuario, clave desde `.env`).
- **Fallback:** si el envío falla, se registra en log y se marca "correo en cola"; la operación no se interrumpe.
- Usos: recuperación de contraseña, confirmación/cancelación de cita, avisos.

## 12. Diseño visual

- **Paleta:** azul médico (`#0A6EBD` aprox.) + verde/teal de acento + fondos claros.
- **Estilo:** moderno y limpio; tarjetas con sombras suaves; tipografía Google Fonts (Inter/Poppins); iconografía Font Awesome.
- **Paneles internos:** plantilla **Gentelella** (admin, recepcionista, recepcionista_medico, medico).
- **Landing y kiosco:** custom, misma paleta, kiosco **touch-friendly** (botones grandes, tipografía grande, indicador de progreso).
- **Logo e imágenes:** aportados por el equipo; usar placeholders hasta integrarlos.

## 13. Criterios de calidad

- Diseño profesional y responsive.
- Validaciones JS y PHP.
- Manejo de errores controlado.
- Comentarios en el código.
- Buenas prácticas (capas, nombres, DRY).
- Código publicado en GitHub con URL + QR.

## 14. Índice de specs

| Spec | Módulo | RF |
|---|---|---|
| `01-autenticacion.md` | Autenticación y control de acceso | RF01 |
| `02-pacientes.md` | Gestión de pacientes | RF02 |
| `03-medicos.md` | Gestión de médicos | RF03 |
| `04-especialidades.md` | Gestión de especialidades | RF04 |
| `05-horarios-cupos.md` | Horarios de atención y cupos | RF05 |
| `06-citas.md` | Registro, duplicados, reprogramación, cancelación | RF06–RF09 |
| `07-estados-asistencia.md` | Estados, asistencia y reconsultas | RF10 |
| `08-agenda-historial.md` | Agenda médica e historial de paciente | RF11–RF12 |
| `09-kiosco-fichas.md` | Kiosco y emisión de fichas | — |
| `10-historia-clinica.md` | Consultas, recetas, documentos, órdenes | — |
| `11-whatsapp-pedidos.md` | Envío por WhatsApp y página pública | — |
| `12-pagos.md` | Registro de pagos | — |
| `13-reportes.md` | Reportes y dashboards | RF13 |
| `14-auditoria.md` | Auditoría y bitácora | — |
| `15-landing.md` | Landing pública | — |
| `99-modelo-datos-rls.md` | Modelo de datos consolidado y RLS | — |

## 15. Terminología

- **Ficha:** ticket/comprobante de una cita, con código y código de acceso; habilita la atención.
- **Cupo:** unidad de disponibilidad de un horario para un canal (web o kiosco).
- **Franja:** rango dentro del horario semanal del médico (`horarios`), con cupos web/kiosco.
- **Slot:** bloque de tiempo concreto (fecha + hora) calculado para reservar.
- **Reconsulta:** cita prioritaria por ausencia del médico o seguimiento post-atención.
- **Pedido:** receta, documento u orden de estudio generado por el médico.
