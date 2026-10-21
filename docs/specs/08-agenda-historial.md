# SPEC 08 — Agenda Médica e Historial de Paciente (RF11–RF12)

> Módulo: Agenda / Historial · Prioridad: **Media**
> Depende de: `06-citas.md`, `07-estados-asistencia.md`, `03-medicos.md`, `02-pacientes.md`

---

## 1. Objetivo

Permitir consultar la agenda de un médico para una fecha específica (con filtros) y el historial de citas de un paciente, incluyendo citas pasadas y canceladas sin perder trazabilidad.

## 2. Alcance

- Agenda por médico + fecha (hora, código de cita, paciente, especialidad, estado, motivo).
- Filtros por médico, fecha y estado.
- Historial por paciente (fecha/hora, médico, especialidad, estado, fecha de registro).
- Incluye citas pasadas y canceladas.
- Vista de calendario (semana/mes) para el médico.

## 3. Actores

- `medico`: su propia agenda.
- `recepcionista` / `recepcionista_medico`: agenda de sus médicos.
- `paciente`: su historial.
- `admin`: todo.

## 4. Modelo de datos

Reutiliza `citas`. Consultas agregadas por médico/fecha y por paciente. No requiere tablas nuevas.

## 5. Endpoints

| Método | Ruta | Descripción | Rol |
|---|---|---|---|
| GET | `/api/medicos/{id}/agenda?fecha=&estado=` | Agenda del día | recep, medico, admin |
| GET | `/api/medicos/{id}/agenda?desde=&hasta=` | Rango (calendario) | recep, medico |
| GET | `/api/pacientes/{id}/historial?desde=&hasta=&estado=` | Historial de citas | recep, medico, paciente(propio) |
| GET | `/api/medicos/{id}/agenda/dia` | Resumen diario (contadores) | recep, medico |

## 6. Reglas de negocio

| Código | Regla |
|---|---|
| RF11-A | La agenda muestra solo citas del médico en la fecha indicada. |
| RF11-B | El motivo se muestra si el usuario tiene permiso (medico/recep/admin). |
| RF11-C | Filtros combinables (medico, fecha, estado). |
| RF12-A | El historial incluye citas pasadas y canceladas (trazabilidad, RN10). |
| RF12-B | El paciente solo ve su propio historial. |
| RF12-C | Orden cronológico descendente por fecha/hora. |

## 7. Validaciones

**Servidor:**
- Fechas válidas (`desde <= hasta`).
- `id` de médico/paciente existe.
- Permiso por rol sobre el recurso consultado.

## 8. Flujos

### 8.1 Agenda del día (médico)
```
[GET /api/medicos/3/agenda?fecha=2026-11-15]
  → SELECT citas WHERE medico_id=3 AND fecha=?
     ORDER BY hora_inicio
  → columnas: hora, codigo, paciente, especialidad, estado, motivo
  → contadores: total, programadas, confirmadas, atendidas, inasistencias
```

### 8.2 Historial de paciente
```
[GET /api/pacientes/10/historial]
  → SELECT citas WHERE paciente_id=10 ORDER BY fecha DESC, hora_inicio DESC
  → incluye estado (incl. cancelada) y fecha_registro
```

## 9. UI / Pantallas

| Vista | Rol | Descripción |
|---|---|---|
| Agenda diaria | medico/recep | Tabla por hora con acciones de estado |
| Calendario semanal | medico | Vista Gentelella calendar |
| Historial paciente | recep/medico/paciente | Timeline/lista de citas |
| Dashboard médico (resumen) | medico | Contadores del día, pacientes en espera, próximas |

## 10. Criterios de aceptación

- [ ] Buscar las citas de un médico por fecha muestra resultados correctos (prueba #8 enunciado).
- [ ] Consultar el historial de un paciente aparece con sus citas (prueba #7 enunciado).
- [ ] El historial incluye canceladas.
- [ ] Filtros por médico/fecha/estado funcionan.
- [ ] El paciente solo ve su propio historial.

## 11. Errores

| Caso | Mensaje |
|---|---|
| Fechas inválidas | "El rango de fechas no es válido." |
| Sin permisos | 403 |
| Recurso inexistente | "No encontrado." |

## 12. Tareas de implementación

1. `AgendaController` + consultas agregadas.
2. `HistorialController` (paciente).
3. Vistas Gentelella (agenda tabla + calendario).
4. Timeline de historial.
5. Filtros y paginación.

## 13. Pruebas

| # | Prueba | Resultado |
|---|---|---|
| 1 | Agenda por fecha | Citas correctas |
| 2 | Filtro por estado | Filtrado |
| 3 | Historial paciente | Todas sus citas |
| 4 | Historial incluye cancelada | Sí |
| 5 | Paciente ve solo su historial | Aislado (RLS) |
