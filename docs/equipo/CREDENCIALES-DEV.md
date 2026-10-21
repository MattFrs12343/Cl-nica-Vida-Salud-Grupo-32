# Credenciales de desarrollo

> Solo para el entorno local. **Nunca** usar estas cuentas en producción.

Contraseña para todas las cuentas: **`Demo1234!`**

| Rol | Usuario | Email | Contraseña |
|---|---|---|---|
| `super_admin` | `superadmin` | `superadmin@vidasalud.test` | `Demo1234!` |
| `admin` | `admin` | `admin@vidasalud.test` | `Demo1234!` |
| `recepcionista` | `recepcion` | `recepcion@vidasalud.test` | `Demo1234!` |
| `recepcionista_medico` | `recepcion.medico` | `recepcion.medico@vidasalud.test` | `Demo1234!` |
| `medico` | `medico` | `medico@vidasalud.test` | `Demo1234!` |
| `paciente` | `12345678` (CI) | `paciente@vidasalud.test` | `Demo1234!` |

## Notas

- El paciente inicia sesión con su **CI** (`12345678`) cuando el `AuthController` esté implementado en la Fase 1.
- Las cuentas se crean con `database/seeders/001_usuarios_demo.sql`.
- Para regenerar la base desde cero:

```bash
docker compose down -v
docker compose up -d
docker compose exec app php bin/db-migrate.php
docker compose exec app php bin/db-seed.php
```
