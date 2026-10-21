# SPEC 05 — Horarios de Atención y Cupos (RF05)

> Módulo: Horarios · Prioridad: **Alta (núcleo, Presentación 1)**
> Depende de: `03-medicos.md`, `99-modelo-datos-rls.md`

---

## 1. Objetivo

Definir la disponibilidad semanal de cada médico, la duración de las citas, los cupos por canal (web/kiosco) y los bloqueos de agenda, para calcular los horarios disponibles que consumen la web, el kiosco y la recepción.

## 2. Alcance

- Registrar franjas de atención por médico y día de la semana.
- Definir `cupos_web` y `cupos_kiosco` por franja (RX01).
- Definir duración de cita (bloques de 30 min por defecto; configurable por médico).
- Registrar bloqueos (feriados/ausencias) por médico y fecha.
- Calcular **slots disponibles** por médico/fecha/canal.

## 3. Actores

- `admin` / `super_admin`: gestiona horarios y bloqueos.
- `medico`: puede ver y sugerir sus horarios (edición limitada opcional).
- `recepcionista`: consulta disponibilidad.
- `kiosco`/`landing`: consumen disponibilidad pública.

## 4. Modelo de datos

### Tabla `horarios`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | identity PK | |
| `medico_id` | FK `medicos.id` | |
| `dia_semana` | smallint | 0=domingo … 6=sábado |
| `hora_inicio` | time | |
| `hora_fin` | time | > hora_inicio |
| `cupos_web` | smallint | cupos reservados al canal web |
| `cupos_kiosco` | smallint | cupos reservados al canal kiosco |
| `activo` | boolean | default true |
| `created_at`,`updated_at` | timestamptz | |

**Restricción:** `hora_fin > hora_inicio`; no solapar franjas activas del mismo médico/día.

### Tabla `bloqueos`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | identity PK | |
| `medico_id` | FK `medicos.id` | |
| `fecha` | date | |
| `hora_inicio` | time | nullable = todo el día |
| `hora_fin` | time | nullable |
| `motivo` | varchar(120) | feriado, ausencia, etc. |
| `created_at` | timestamptz | |

## 5. Cálculo de disponibilidad (motor)

```
disponibilidad(medico, fecha, canal):
  dia = extract(dow from fecha)
  franjas = horarios activos WHERE medico_id=? AND dia_semana=dia
  slots = []
  for franja in franjas:
     dur = medico.duracion_cita_min
     recorrer de franja.hora_inicio a franja.hora_fin en pasos de dur:
        slot = (fecha, hora)
        if slot cruza bloqueo(medico, fecha) → saltar
        if slot en el pasado → saltar
        citas_activas = COUNT(citas WHERE medico_id AND fecha AND hora_inicio=slot AND estado<>'cancelada')
        ocupado = citas_activas > 0   # RN07
        if ocupado → saltar
        # cupos por canal
        reservas_canal = COUNT(citas WHERE ... AND origen=canal AND estado<>'cancelada' AND es_reconsulta=false)
        if reservas_canal >= cupo(canal, franja) → saltar
        slots.push(slot)
  return slots
```

- `cupo('web') = franja.cupos_web`; `cupo('kiosco') = franja.cupos_kiosco`.
- Las reconsultas **no** consumen cupo de canal (RX07) pero sí ocupan el slot.
- Recepción ve la unión de web+kiosco más cupos de reconsulta.

## 6. Endpoints

| Método | Ruta | Descripción | Rol |
|---|---|---|---|
| GET | `/api/medicos/{id}/horarios` | Listar franjas | admin, recepción |
| POST | `/api/medicos/{id}/horarios` | Crear franja | admin |
| PUT | `/api/horarios/{id}` | Editar franja | admin |
| DELETE | `/api/horarios/{id}` | Desactivar franja | admin |
| GET | `/api/medicos/{id}/bloqueos` | Listar bloqueos | admin |
| POST | `/api/medicos/{id}/bloqueos` | Crear bloqueo | admin |
| GET | `/api/disponibilidad?medico=&fecha=&canal=` | Slots disponibles | según canal |
| GET | `/api/disponibilidad?medico=&fecha=&especialidad=` | Slots para reserva web/kiosco | pública/kiosco |

## 7. Reglas de negocio

| Código | Regla |
|---|---|
| RF05-A | `hora_fin > hora_inicio`. No horarios inválidos. |
| RF05-B | No se ofrecen citas fuera del horario de atención (RN05). |
| RF05-C | No se ofrecen citas en fechas/horas pasadas (RN06). |
| RF05-D | Las citas respetan los intervalos (duración configurada, RN08). |
| RF05-E | Franjas no se solapan para el mismo médico/día. |
| RF05-F | Los bloqueos excluyen slots (feriados/ausencia) — opcional según tiempo (RX). |
| RF05-G | Cupos web/kiosco respetan 50/50 por franja (RX01). |
| RF05-H | Citas canceladas liberan el slot (RN09). |

## 8. Validaciones

**Servidor:**
- `dia_semana` 0–6; `hora_inicio`/`hora_fin` válidas y coherentes.
- `cupos_web`, `cupos_kiosco` ≥ 0; la suma no supera los slots posibles de la franja.
- No solapamiento de franjas activas.
- `bloqueos`: fecha válida; `hora_fin > hora_inicio` si ambas.

**Cliente (JS):**
- Comparación de horas, cupos numéricos.

## 9. Flujos

### 9.1 Crear franja
```
[POST /api/medicos/{id}/horarios]
  → validar horas y solapamiento
  → INSERT horarios
```

### 9.2 Consultar disponibilidad (kiosco/web)
```
[GET /api/disponibilidad?medico=3&fecha=2026-11-15&canal=web]
  → motor disponibilidad()
  → [{hora_inicio, hora_fin, cupos_libres}]   (para el canal)
```

## 10. UI / Pantallas

| Vista | Rol | Descripción |
|---|---|---|
| Horarios del médico | admin | Grilla semanal editable + cupos web/kiosco |
| Bloqueos | admin | Lista y alta de bloqueos |
| Disponibilidad | recepción/kiosco/web | Slots como botones/chips |
| Selector fecha-hora | kiosco | Touch-friendly |

## 11. Criterios de aceptación

- [ ] Crear franja válida funciona; con hora_fin ≤ hora_inicio se rechaza.
- [ ] Franjas solapadas se rechazan.
- [ ] Disponibilidad respeta la duración (bloques de 30 min).
- [ ] No aparecen slots en fechas pasadas.
- [ ] No aparecen slots fuera del horario.
- [ ] Cupos 50/50 se aplican por franja y canal.

## 12. Errores

| Caso | Mensaje |
|---|---|
| Hora inválida | "La hora final debe ser posterior a la inicial." |
| Franja solapada | "La franja se superpone con otra existente." |
| Cupo excedido | "Los cupos superan los intervalos disponibles." |
| Fuera de horario | "El horario seleccionado no está dentro de la atención del médico." |

## 13. Tareas de implementación

1. Migraciones `horarios`, `bloqueos`.
2. `HorarioModel` + `DisponibilidadService` (motor).
3. `HorarioController` + endpoints de disponibilidad.
4. Vista Gentelella de administración de horarios.
5. Consumo desde Citas, Kiosco y Landing.
6. Validación de cupos en transacción (`SELECT ... FOR UPDATE`).

## 14. Pruebas

| # | Prueba | Resultado esperado |
|---|---|---|
| 1 | Crear franja Lunes 08:00–12:00 | Guardada |
| 2 | Franja hora_fin < hora_inicio | Rechazada |
| 3 | Disponibilidad devuelve slots de 30 min | Correcto |
| 4 | Fecha pasada | Sin slots |
| 5 | Cupos web agotados | Web sin slots, kiosco con slots |
| 6 | Bloqueo por feriado | Slots excluidos |
