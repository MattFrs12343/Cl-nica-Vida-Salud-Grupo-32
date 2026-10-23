# Clínica Vida Salud — Sistema de Gestión de Citas

Sistema web de gestión de citas médicas para la Clínica Vida Salud.
Stack: **PHP 8.1+ (MVC manual) + PostgreSQL 16 + HTML/CSS/JS**.

> Documentación funcional: [`docs/specs/`](docs/specs/).
> Reparto de trabajo: [`docs/equipo/PLAN-TRABAJO.md`](docs/equipo/PLAN-TRABAJO.md).
> Contrato de API: [`docs/api/contract.md`](docs/api/contract.md).

---

## Requisitos

- Docker Desktop (recomendado) **o** PHP 8.1+ con `pdo_pgsql`, Apache con `mod_rewrite` y PostgreSQL 16.
- Git.

## Arranque con Docker

```bash
# 1. Crear el archivo de entorno local
copy .env.example .env        # Windows
# cp .env.example .env        # Linux/macOS

# 2. Levantar PHP/Apache, PostgreSQL y Mailpit
docker compose up -d --build

# 3. Instalar dependencias PHP (PHPMailer, etc.)
docker compose exec app composer install

# 4. Crear las tablas y cargar datos demo
docker compose exec app php bin/db-migrate.php
docker compose exec app php bin/db-seed.php
```

Servicios:

| Servicio | URL |
|---|---|
| Aplicación | <http://localhost:8080> |
| Health check | <http://localhost:8080/health> |
| Mailpit (correo local) | <http://localhost:8025> |
| PostgreSQL | `localhost:5432` (db: `clinica`, user: `clinica`, pass: `clinica`) |

Para detener: `docker compose down`. Para borrar también la base: `docker compose down -v`.

## Arranque sin Docker

1. Copia `.env.example` a `.env` y ajusta `DB_HOST=127.0.0.1`, `MAIL_HOST=127.0.0.1`.
2. Crea la base `clinica` en PostgreSQL.
3. Ejecuta `php bin/db-migrate.php` y `php bin/db-seed.php`.
4. Sirve la aplicación con `php -S localhost:8080 -t public`.

## Estructura

```
public/                 # document root
  index.php             # router frontal
  .htaccess             # rewrite → index.php
  assets/               # css, js, imágenes
  uploads/              # archivos subidos (no se versionan)
app/
  config/               # app, database, mail
  core/                 # Router, Request, Response, Session, DB, View, helpers
  middlewares/          # Auth, Role, CSRF, Kiosk
  controllers/          # API, auth, paneles, públicas
  services/             # reglas de negocio
  models/               # acceso a datos (PDO)
  routes/               # public.php, auth.php, api.php, panels.php
  views/                # landing, kiosco, admin, recepcion, medico, paciente
database/
  migrations/           # SQL versionado
  seeders/              # datos demo
bin/                    # scripts CLI (migrate, seed)
docs/                   # specs, contrato de API y reparto
storage/logs/           # logs internos
```

## Usuarios demo

Después de ejecutar los seeders:

| Rol | Usuario | Contraseña |
|---|---|---|
| super_admin | `superadmin` | `Demo1234!` |
| admin | `admin` | `Demo1234!` |
| recepcionista | `recepcion` | `Demo1234!` |
| recepcionista_medico | `recepcion.medico` | `Demo1234!` |
| medico | `medico` | `Demo1234!` |
| paciente | `12345678` (CI) | `Demo1234!` |

> Solo para desarrollo. Nunca usar estas cuentas en producción.

## Comandos útiles

```bash
docker compose exec app php bin/db-migrate.php   # aplica migraciones
docker compose exec app php bin/db-seed.php      # carga/actualiza datos demo
docker compose exec app php bin/db-reset.php     # borra datos y recarga demo (destructivo)
docker compose exec app php bin/test-auth.php         # pruebas de login, bloqueo, reset y middlewares
docker compose exec app php bin/test-public.php       # pruebas de catálogo público, disponibilidad y reserva web
docker compose exec app php bin/test-admin.php        # pruebas de CRUDs administrativos, permisos y CSRF
docker compose exec app php bin/test-reportes.php     # pruebas de citas, agenda, reportes y auditoría
docker compose exec app php bin/test-integracion.php  # flujo landing → reserva → cupo → correo → admin → reportes
docker compose exec app php bin/test-kiosco.php       # pruebas de token HMAC y middleware del kiosco
docker compose exec app php -l archivo.php            # lint de un archivo
docker compose logs -f app                            # logs de Apache/PHP
```

La recuperación de contraseña se envía a Mailpit (`http://localhost:8025`). Si el SMTP falla, el correo queda en `cola_correos`.

## Convenciones

- PHP 8.1+, sin framework.
- PDO preparado; nunca concatenar SQL.
- Contraseñas con `password_hash()` / `password_verify()`.
- Output escapado con `e()`.
- CSRF en toda mutación de estado (excepto kiosco, que usa token HMAC).
- Zona horaria: `America/La_Paz`.

## Estado del proyecto

- **Fase 0 completada:** esqueleto, Docker, core, contratos, migración de usuarios y seeders demo.
- **Fase 1 backend completada:** modelo de datos 001–010, RLS, seeders, auth real (login por usuario/email/CI, bloqueo, reset, activación), middlewares y correo con PHPMailer/Mailpit.
- **Fase 2 backend completada:** catálogo público (`/api/public/especialidades`, `/medicos`, `/disponibilidad`), reserva web con cupo, ficha y correo (`POST /api/public/reservar`), landing PHP en `/`, `/reservar`, `/kiosco`, APIs administrativas (M-20/M-21) y pruebas de integración (M-22).
- **Fase 3 backend en curso:** token HMAC de kiosco, `KioskMiddleware` y `POST /api/kiosco/sesion` (M-23) listos; siguen las APIs de kiosco y recepción.
- **Pendiente de Lucas:** vistas de autenticación (`app/views/auth/*`), paneles y su integración visual. Mientras no existan, los GET de auth devuelven `501 VIEW_NOT_AVAILABLE` en JSON.
- **Pendiente de Matias:** APIs de kiosco y recepción (M-24 en adelante).

Las fases y tareas están en [`docs/equipo/PLAN-TRABAJO.md`](docs/equipo/PLAN-TRABAJO.md).
