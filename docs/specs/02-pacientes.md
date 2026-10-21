# SPEC 02 — Gestión de Pacientes (RF02)

> Módulo: Pacientes · Prioridad: **Alta (núcleo, Presentación 1)**
> Depende de: `01-autenticacion.md`, `99-modelo-datos-rls.md`

---

## 1. Objetivo

Registrar, consultar, buscar, modificar y desactivar pacientes de la clínica, conservando siempre su historial de citas y su información de perfil (datos personales, contacto, información médica y contacto de emergencia).

## 2. Alcance

- CRUD de pacientes (crear, listar, buscar, editar, desactivar).
- Búsqueda por **CI, nombre o apellido**.
- Perfil de paciente con secciones: datos personales, contacto, información médica, contacto de emergencia.
- Historial de citas del paciente (enlaza con Spec 08).
- Regla de **CI única**.
- Desactivación sin eliminar historial.
- Pacientes sin cuenta (creados en kiosco/recepción) y con cuenta (usuario_id).

**Fuera de alcance:** eliminación física de pacientes.

## 3. Actores

- `admin` / `super_admin`: CRUD completo, activar/desactivar.
- `recepcionista`: registrar, buscar, editar, ver historial.
- `recepcionista_medico`: ver pacientes atendidos por su médico + registrar nuevos.
- `medico`: ver pacientes de sus consultas.
- `paciente`: ver/editar su propio perfil.

## 4. Modelo de datos

### Tabla `pacientes`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | identity PK | |
| `codigo` | varchar(20) | único, `PAC-0001` |
| `usuario_id` | int FK `usuarios.id` | **nullable** (null hasta activar cuenta) |
| `ci` | varchar(20) | **único**, obligatorio |
| `nombres` | varchar(100) | obligatorio |
| `apellidos` | varchar(100) | obligatorio |
| `fecha_nacimiento` | date | obligatorio |
| `sexo` | enum | `masculino`,`femenino` |
| `telefono` | varchar(20) | |
| `email` | varchar(120) | opcional |
| `direccion` | varchar(200) | opcional |
| `tipo_sangre` | varchar(5) | opcional (A+, O-, ...) |
| `alergias` | text | opcional |
| `enfermedades_cronicas` | text | opcional |
| `medicamentos` | text | opcional |
| `antecedentes` | text | opcional |
| `emergencia_nombre` | varchar(100) | opcional |
| `emergencia_parentesco` | varchar(50) | opcional |
| `emergencia_telefono` | varchar(20) | opcional |
| `estado` | enum | `activo`,`inactivo` |
| `created_at`,`updated_at` | timestamptz | |

**Campos calculados:** `edad` (derivado de `fecha_nacimiento`, no almacenado).

## 5. Endpoints

| Método | Ruta | Descripción | Rol |
|---|---|---|---|
| GET | `/api/pacientes` | Listar (paginado, filtros q/estado) | admin, recepción |
| GET | `/api/pacientes/{id}` | Detalle | admin, recepción, médico |
| POST | `/api/pacientes` | Crear | admin, recepción |
| PUT | `/api/pacientes/{id}` | Editar | admin, recepción |
| PATCH | `/api/pacientes/{id}/estado` | Activar/desactivar | admin |
| GET | `/api/pacientes/{id}/citas` | Historial de citas | admin, recepción, médico |
| GET | `/api/pacientes/buscar?q=` | Buscar por CI/nombre/apellido | admin, recepción |
| GET | `/api/me/perfil` | Perfil propio | paciente |
| PUT | `/api/me/perfil` | Editar perfil propio (datos médicos y contacto) | paciente |

## 6. Reglas de negocio

| Código | Regla |
|---|---|
| RF02-A | `ci` es única (RN01). Intentar duplicar → error controlado. |
| RF02-B | Al crear, se genera `codigo` (`PAC-####`) y `estado=activo`. |
| RF02-C | No se puede eliminar físicamente un paciente con citas (RN11). |
| RF02-D | Desactivar (`estado=inactivo`) conserva historial; no puede agendar nuevas citas. |
| RF02-E | El paciente sin cuenta existe con `usuario_id=null` (kiosco/recepción). |
| RF02-F | Un paciente solo edita sus propios datos de contacto y médicos; no su CI ni su código. |
| RF02-G | El médico solo ve pacientes relacionados a sus consultas/citas. |
| RF02-H | El recepcionista de médico solo ve pacientes atendidos por los médicos asignados. |

## 7. Validaciones

**Servidor:**
- `ci` requerido, formato válido, único.
- `nombres`, `apellidos` requeridos, ≥ 2 caracteres, solo letras/espacios/acentos.
- `fecha_nacimiento` válida, no futura, no anterior a 120 años.
- `sexo` en (`masculino`,`femenino`).
- `telefono` formato válido (7–15 dígitos, opcional +).
- `email` válido si se envía (regex / `filter_var`).
- `tipo_sangre` dentro de lista permitida si se envía.

**Cliente (JS):**
- Campos requeridos, formato de CI/teléfono/email, fecha no futura.

## 8. Flujos

### 8.1 Registrar paciente (recepción)
```
[POST /api/pacientes]
  → validar campos
  → verificar CI no existente (SELECT ... WHERE ci=?)  [UNIQUE en BD como respaldo]
  → INSERT pacientes (codigo generado en trigger/seq)
  → registrar en auditoría (acción INSERT)  ← opcional, ver Spec 14
  → 201 + datos
  → si CI duplicada → 409 "Ya existe un paciente con esa CI" (prueba #2)
```

### 8.2 Buscar
```
[GET /api/pacientes/buscar?q=abc]
  → ILIKE sobre ci, nombres, apellidos (concatenados)
  → devolver lista con paginación
```

### 8.3 Desactivar
```
[PATCH /api/pacientes/{id}/estado]
  → si existen citas activas futuras → aviso
  → UPDATE estado='inactivo' (no DELETE)
  → historial se conserva
```

### 8.4 Paciente edita su perfil
```
[PUT /api/me/perfil]
  → solo campos permitidos (contacto, médicos, emergencia)
  → no tocar ci/codigo/usuario_id
```

## 9. UI / Pantallas

| Vista | Rol | Descripción |
|---|---|---|
| Lista de pacientes | admin/recepción | Tabla Gentelella, buscador, filtros, paginación, acciones |
| Ficha/Detalle paciente | admin/recepción/médico | Pestañas: datos personales, contacto, info médica, emergencia, historial de citas |
| Formulario paciente | admin/recepción | Alta/edición con validaciones |
| Mi perfil | paciente | Editar datos propios |

## 10. Criterios de aceptación

- [ ] Registrar un paciente nuevo queda guardado (prueba #1 del enunciado).
- [ ] Registrar con CI duplicada es rechazado (prueba #2 del enunciado).
- [ ] Buscar por CI, nombre o apellido devuelve resultados correctos.
- [ ] Editar datos persiste los cambios.
- [ ] Desactivar conserva las citas históricas (prueba #7 enunciado indirecta).
- [ ] El paciente accede a su perfil y edita solo campos permitidos.
- [ ] Paciente del kiosco aparece en lista y puede activar cuenta después.

## 11. Errores

| Caso | Mensaje |
|---|---|
| CI duplicada | "Ya existe un paciente con la CI ingresada." |
| Campos inválidos | Detalle por campo (mensajes específicos). |
| Paciente inexistente | "Paciente no encontrado." |
| Sin permisos | 403 "No tiene permisos para esta operación." |

## 12. Tareas de implementación

1. Migración `pacientes` + secuencia de `codigo` PAC.
2. `PacienteModel` (CRUD, búsqueda, historial).
3. `PacienteController` (API + vistas).
4. Vistas Gentelella (lista, detalle, formulario) + validación JS.
5. Vista "Mi perfil" para paciente.
6. Políticas RLS por rol.
7. Tests manuales de las pruebas 1 y 2.

## 13. Pruebas

| # | Prueba | Resultado esperado |
|---|---|---|
| 1 | Registrar paciente nuevo | Guardado correctamente |
| 2 | Registrar CI duplicada | Rechazado con mensaje |
| 3 | Buscar "López" | Devuelve coincidencias |
| 4 | Desactivar paciente con historial | Historial intacto |
| 5 | Paciente edita su perfil | Cambios persistidos |
