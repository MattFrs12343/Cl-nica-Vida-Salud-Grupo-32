# SPEC 04 — Gestión de Especialidades Médicas (RF04)

> Módulo: Especialidades · Prioridad: **Alta (núcleo, Presentación 1)**
> Depende de: `99-modelo-datos-rls.md`

---

## 1. Objetivo

Administrar las especialidades que ofrece la clínica, incluyendo sus restricciones de sexo y edad (RX02, RX03) y su visibilidad en la landing.

## 2. Alcance

- CRUD de especialidades.
- Nombre único normalizado.
- Restricción de **sexo permitido** (`ambos`/`femenino`/`masculino`).
- Restricción de **edad** (`edad_min`, `edad_max`; null = sin límite).
- Marcar especialidades **destacadas** (pediatría, medicina general, geriatría).
- Activar/desactivar.

## 3. Actores

- `admin` / `super_admin`: CRUD completo.
- Resto de usuarios: solo lectura.
- Público: especialidades activas (landing).

## 4. Modelo de datos

### Tabla `especialidades`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | identity PK | |
| `nombre` | varchar(80) | **único** (normalizado) |
| `descripcion` | text | opcional |
| `sexo_permitido` | enum | `ambos`,`femenino`,`masculino` (default `ambos`) |
| `edad_min` | smallint | nullable |
| `edad_max` | smallint | nullable |
| `destacada` | boolean | default false |
| `icono` | varchar(50) | clase Font Awesome / SVG (opcional) |
| `estado` | enum | `activo`,`inactivo` |
| `created_at`,`updated_at` | timestamptz | |

**Normalización:** `nombre` se guarda con `trim` + colapso de espacios; comparación de unicidad `lower(nombre)`.

## 5. Datos iniciales (seeder)

| Nombre | sexo | edad | destacada |
|---|---|---|---|
| Pediatría | ambos | 0–12 | ✅ |
| Medicina General | ambos | — | ✅ |
| Geriatría | ambos | 60+ | ✅ |
| Endocrinología | ambos | — | |
| Alergología | ambos | — | |
| Urología | ambos | — | |
| Ginecología | femenino | — | |
| Traumatología | ambos | — | |
| Reumatología | ambos | — | |
| Oftalmología | ambos | — | |
| Otorrinolaringología | ambos | — | |

## 6. Endpoints

| Método | Ruta | Descripción | Rol |
|---|---|---|---|
| GET | `/api/especialidades` | Listar (filtros: estado, destacada) | admin, recepción |
| GET | `/api/especialidades/{id}` | Detalle | admin |
| POST | `/api/especialidades` | Crear | admin |
| PUT | `/api/especialidades/{id}` | Editar | admin |
| PATCH | `/api/especialidades/{id}/estado` | Activar/desactivar | admin |
| GET | `/api/public/especialidades` | Activas (landing/kiosco) | pública |

## 7. Reglas de negocio

| Código | Regla |
|---|---|
| RF04-A | No se permiten dos especialidades con el mismo nombre normalizado. |
| RF04-B | `edad_min <= edad_max` (si ambos definidos). |
| RF04-C | Una especialidad inactiva no aparece en kiosco/landing ni para nuevas citas. |
| RF04-D | `sexo_permitido` restringe pacientes elegibles (RX02). |
| RF04-E | `edad_min`/`edad_max` restringen pacientes elegibles (RX03). |
| RF04-F | La validación de sexo/edad se aplica en web, kiosco y recepción. |

## 8. Validaciones

**Servidor:**
- `nombre` requerido, único (normalizado), 3–80 chars.
- `sexo_permitido` en el enum.
- `edad_min`/`edad_max` enteros 0–120 y coherentes.
- `destacada`: máximo recomendado 5 (regla blanda).

**Cliente (JS):**
- Campos requeridos; coherencia de rangos de edad.

## 9. Flujos

### 9.1 Crear especialidad
```
[POST /api/especialidades]
  → normalizar nombre; verificar unicidad lower(nombre)
  → INSERT especialidades
  → auditoría INSERT
```

### 9.2 Validación de elegibilidad (usada por Citas/Kiosco)
```
elegible(paciente, especialidad):
  if especialidad.sexo_permitido != 'ambos' and paciente.sexo != especialidad.sexo_permitido → false
  edad = calcular_edad(paciente.fecha_nacimiento)
  if especialidad.edad_min and edad < edad_min → false
  if especialidad.edad_max and edad > edad_max → false
  return true
```

## 10. UI / Pantallas

| Vista | Rol | Descripción |
|---|---|---|
| Lista especialidades | admin | Tabla con nombre, restricciones, destacada, estado |
| Formulario especialidad | admin | Nombre, descripción, sexo, rangos edad, destacada |
| Grid en landing | público | Tarjetas por especialidad (destacadas primero) |
| Grid en kiosco | kiosco | Especialidades activas como botones grandes |

## 11. Criterios de aceptación

- [ ] Crear especialidad con nombre nuevo funciona.
- [ ] Crear con nombre duplicado (mismo normalizado) es rechazado.
- [ ] Ginecología restringe a pacientes femeninos.
- [ ] Pediatría rechaza pacientes > 12 años; geriatría rechaza < 60.
- [ ] Especialidad inactiva no aparece en landing/kiosco.

## 12. Errores

| Caso | Mensaje |
|---|---|
| Nombre duplicado | "Ya existe una especialidad con ese nombre." |
| Rango de edad inválido | "El rango de edad no es válido." |
| Paciente no elegible | "Esta especialidad no está disponible para el paciente seleccionado." |

## 13. Tareas de implementación

1. Migración `especialidades` + seeder (11 especialidades).
2. `EspecialidadModel` + método `esElegiblePaciente()`.
3. `EspecialidadController`.
4. Vista Gentelella de gestión.
5. Endpoint público para landing/kiosco.
6. Reutilizar `esElegiblePaciente()` en Citas y Kiosco.

## 14. Pruebas

| # | Prueba | Resultado esperado |
|---|---|---|
| 1 | Crear "Dermatología" | Guardada |
| 2 | Crear "pediatria" (dup) | Rechazado |
| 3 | Hombre intenta ginecología | Rechazado |
| 4 | Niño de 5 años en geriatría | Rechazado |
| 5 | Especialidad inactiva en landing | No aparece |
