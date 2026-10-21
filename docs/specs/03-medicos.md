# SPEC 03 — Gestión de Médicos (RF03)

> Módulo: Médicos · Prioridad: **Alta (núcleo, Presentación 1)**
> Depende de: `01-autenticacion.md`, `04-especialidades.md`, `99-modelo-datos-rls.md`

---

## 1. Objetivo

Administrar la información de los médicos de la clínica: registro, consulta, búsqueda, modificación, activación/desactivación, asignación de especialidades, agenda y código QR profesional.

## 2. Alcance

- CRUD de médicos.
- Asignación de una o varias especialidades (N:M).
- Búsqueda por **nombre o especialidad**.
- Activar/desactivar médicos.
- Consulta de agenda del médico (enlaza Spec 08).
- Carga del QR del médico (solo el propio médico).
- Asignación de recepcionistas al médico (Spec 03, tabla puente — gestionada por admin).

**Fuera de alcance:** eliminación física de médicos.

## 3. Actores

- `admin` / `super_admin`: CRUD, especialidades, activar/desactivar, asignar recepcionistas.
- `medico`: ver/editar sus datos, subir QR, ver su agenda.
- `recepcionista`: consultar médicos y horarios (spec 05).
- `recepcionista_medico`: ver datos de los médicos asignados.
- `paciente`/público: ver listado de médicos activos por especialidad (landing).

## 4. Modelo de datos

### Tabla `medicos`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | identity PK | |
| `codigo` | varchar(20) | único, `MED-0001` |
| `usuario_id` | int FK `usuarios.id` | **nullable** |
| `registro_profesional` | varchar(50) | **único**, obligatorio |
| `nombres` | varchar(100) | |
| `apellidos` | varchar(100) | |
| `telefono` | varchar(20) | |
| `email` | varchar(120) | |
| `duracion_cita_min` | smallint | default 30 |
| `qr_imagen` | varchar(255) | ruta a `/uploads/qr/` |
| `estado` | enum | `activo`,`inactivo` |
| `created_at`,`updated_at` | timestamptz | |

### Tabla `medico_especialidad` (N:M)
| Campo | Tipo |
|---|---|
| `medico_id` | FK `medicos.id` |
| `especialidad_id` | FK `especialidades.id` |
| PK | (`medico_id`, `especialidad_id`) |

### Tabla `recepcionista_medico` (asignación)
| Campo | Tipo |
|---|---|
| `usuario_id` | FK `usuarios.id` (rol recepcionista_medico) |
| `medico_id` | FK `medicos.id` |
| PK | (`usuario_id`, `medico_id`) |

## 5. Endpoints

| Método | Ruta | Descripción | Rol |
|---|---|---|---|
| GET | `/api/medicos` | Listar (filtros: q, especialidad, estado) | admin, recepción |
| GET | `/api/medicos/{id}` | Detalle + especialidades | admin, recepción |
| POST | `/api/medicos` | Crear | admin |
| PUT | `/api/medicos/{id}` | Editar | admin, (medico: propio) |
| PATCH | `/api/medicos/{id}/estado` | Activar/desactivar | admin |
| PUT | `/api/medicos/{id}/especialidades` | Asignar especialidades | admin |
| POST | `/api/medicos/{id}/qr` | Subir QR (multipart) | medico (propio) |
| GET | `/api/medicos/{id}/agenda?fecha=` | Agenda del día | admin, recep, medico |
| GET | `/api/public/medicos?especialidad=` | Listado público (activos) | pública |
| POST | `/api/medicos/{id}/recepcionistas` | Asignar recepcionista | admin |

## 6. Reglas de negocio

| Código | Regla |
|---|---|
| RF03-A | `codigo` y `registro_profesional` únicos (RN02). |
| RF03-B | Un médico inactivo no recibe citas nuevas (RN04), pero conserva historial. |
| RF03-C | No se elimina físicamente un médico con citas (RN11). |
| RF03-D | Un médico puede tener **una o varias** especialidades (N:M). |
| RF03-E | Solo el propio médico sube/cambia su QR; otros roles solo lo ven. |
| RF03-F | Un `recepcionista_medico` necesita al menos un médico asignado. |
| RF03-G | `duracion_cita_min` default 30; editable por admin. |
| RF03-H | Al crear médico, se puede crear opcionalmente su usuario (rol medico) o dejar `usuario_id=null`. |

## 7. Validaciones

**Servidor:**
- `registro_profesional` requerido y único.
- `nombres`, `apellidos` requeridos.
- `email` válido si se envía; `telefono` válido si se envía.
- `duracion_cita_min` entre 5 y 120.
- Al menos una especialidad activa.
- Archivo QR: imagen (png/jpg), ≤ 1 MB.

**Cliente (JS):**
- Campos requeridos, formato email/teléfono, al menos una especialidad seleccionada.

## 8. Flujos

### 8.1 Registrar médico (admin)
```
[POST /api/medicos]
  → validar; verificar registro_profesional único
  → INSERT medicos (codigo MED-####)
  → INSERT medico_especialidad (listado)
  → (opcional) crear usuarios rol=medico y enlazar usuario_id
  → auditoría INSERT
  → 201
```

### 8.2 Subir QR (médico)
```
[POST /api/medicos/{id}/qr] (solo si id == sesión medico)
  → validar imagen
  → guardar en /uploads/qr/med-<id>.png
  → UPDATE qr_imagen
```

### 8.3 Desactivar médico (admin)
```
[PATCH /api/medicos/{id}/estado]
  → si tiene citas futuras activas → aviso (reprogramar/cancelar primero)
  → UPDATE estado='inactivo'
```

### 8.4 Asignar recepcionista (admin)
```
[POST /api/medicos/{id}/recepcionistas] { usuario_id }
  → validar rol recepcionista_medico
  → INSERT recepcionista_medico
```

## 9. UI / Pantallas

| Vista | Rol | Descripción |
|---|---|---|
| Lista de médicos | admin/recepción | Tabla con nombre, especialidades, estado, acciones |
| Formulario médico | admin | Datos + multiselect de especialidades |
| Detalle médico | admin/recepción | Datos, especialidades, QR, agenda, recepcionistas |
| Mi perfil médico | medico | Datos + subir QR |
| Directorio público | público | Listado por especialidad (landing) |

## 10. Criterios de aceptación

- [ ] Registrar médico con especialidades queda guardado.
- [ ] Buscar por nombre o especialidad devuelve resultados.
- [ ] Médico inactivo no aparece en disponibilidad para nuevas citas.
- [ ] Médico sube su QR; se muestra en su perfil (prueba de permisos QR).
- [ ] Historial de citas del médico se conserva al desactivar.
- [ ] Admin asigna recepcionista a un médico.

## 11. Errores

| Caso | Mensaje |
|---|---|
| Registro profesional duplicado | "Ya existe un médico con ese registro profesional." |
| Sin especialidad | "Seleccione al menos una especialidad." |
| QR inválido | "El archivo debe ser una imagen PNG o JPG menor a 1 MB." |
| Médico inactivo en cita | "El médico está inactivo y no puede recibir citas." |

## 12. Tareas de implementación

1. Migraciones `medicos`, `medico_especialidad`, `recepcionista_medico`.
2. `MedicoModel` (CRUD, especialidades, QR, agenda).
3. `MedicoController` + endpoint público.
4. Vistas Gentelella (lista, formulario, detalle, perfil médico).
5. Upload seguro del QR.
6. Políticas RLS.
7. Directorio público en landing.

## 13. Pruebas

| # | Prueba | Resultado esperado |
|---|---|---|
| 1 | Crear médico con 2 especialidades | Guardado con relación N:M |
| 2 | Registro profesional duplicado | Rechazado |
| 3 | Desactivar médico | No recibe nuevas citas |
| 4 | Médico sube QR | QR visible |
| 5 | Agendar con médico inactivo (prueba #5 enunciado indirecta) | Rechazado |
| 6 | Asignar recepcionista | Relación creada |
