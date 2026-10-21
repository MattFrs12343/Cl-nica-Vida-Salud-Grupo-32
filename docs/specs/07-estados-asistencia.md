# SPEC 07 — Estados, Control de Asistencia y Reconsultas (RF10)

> Módulo: Estados de cita / Asistencia · Prioridad: **Alta (núcleo)**
> Depende de: `06-citas.md`, `09-kiosco-fichas.md`, `99-modelo-datos-rls.md`

---

## 1. Objetivo

Administrar los estados de las citas, controlar las transiciones válidas entre ellos, registrar asistencia/inasistencia y gestionar las **reconsultas** (por ausencia del médico y por seguimiento), con prioridad de cupo.

## 2. Alcance

- Estados: `programada`, `confirmada`, `atendida`, `cancelada`, `inasistencia`.
- Transición automática `programada → confirmada` al emitirse la ficha (RX08).
- Marcar `atendida` / `inasistencia` por médico o recepción (Spec 03).
- Reconsulta por **ausencia del médico** (RX05): reagenda con prioridad.
- Reconsulta de **seguimiento** (RX06): al marcar `atendida`, agendar siguiente semana.
- Prioridad de reconsulta: puede tomar cupo aunque el canal esté agotado (solo recepción, RX07).

## 3. Actores

- `medico`: marcar `atendida` / `inasistencia` de sus citas; crear reconsulta de seguimiento.
- `recepcionista` / `recepcionista_medico`: marcar asistencia; crear reconsulta (ausencia/seguimiento).
- `admin`: todo.

## 4. Modelo de datos

Reutiliza `citas` (Spec 06):
- `estado` (enum), `es_reconsulta`, `es_prioritaria`, `reconsulta_tipo`, `cita_origen_id`, `motivo_cancelacion`.

Tabla auxiliar de historial de estados (opcional, recomendada):
### Tabla `citas_estado_historial`
| Campo | Tipo |
|---|---|
| `id` | identity PK |
| `cita_id` | FK `citas.id` |
| `estado_anterior` | enum |
| `estado_nuevo` | enum |
| `usuario_id` | FK `usuarios.id` |
| `observacion` | varchar(200) |
| `fecha` | timestamptz default now() |

## 5. Máquina de estados (transiciones válidas)

```
programada  → confirmada   (ficha emitida)
programada  → cancelada    (hasta día anterior)
programada  → inasistencia (paciente no llegó, ya pasó la hora)
confirmada  → atendida     (consulta realizada)
confirmada  → cancelada    (hasta día anterior)
confirmada  → inasistencia (no se presentó)
atendida    → (terminal)
cancelada   → (terminal; no se marca atendida sin procedimiento autorizado)
inasistencia→ (terminal, salvo reconsulta autorizada)
```

| De \ A | programada | confirmada | atendida | cancelada | inasistencia |
|---|:-:|:-:|:-:|:-:|:-:|
| programada | – | ✅ | ❌ | ✅ | ✅ |
| confirmada | ❌ | – | ✅ | ✅ | ✅ |
| atendida | ❌ | ❌ | – | ❌ | ❌ |
| cancelada | ❌ | ❌ | ❌ | – | ❌ |
| inasistencia | ❌ | ❌ | ❌ | ❌ | – |

> Cualquier transición no listada → 422 "Transición de estado no permitida."

## 6. Endpoints

| Método | Ruta | Descripción | Rol |
|---|---|---|---|
| PATCH | `/api/citas/{id}/confirmar` | Ficha → `confirmada` | kiosco/web (interno) |
| PATCH | `/api/citas/{id}/atender` | Marcar `atendida` | recep, medico |
| PATCH | `/api/citas/{id}/inasistencia` | Marcar `inasistencia` | recep, medico |
| PATCH | `/api/citas/{id}/cancelar` | Cancelar (Spec 06) | recep, paciente |
| POST | `/api/citas/{id}/reconsulta` | Crear reconsulta | recep, medico |
| GET | `/api/citas/{id}/historial-estados` | Historial de estados | admin |

### Body de reconsulta
```json
{
  "tipo": "ausencia_medico" | "seguimiento",
  "fecha": "2026-11-22",
  "hora": "09:00",
  "motivo": "opcional"
}
```

## 7. Reglas de negocio

| Código | Regla |
|---|---|
| RF10-A | Solo transiciones válidas de la matriz. |
| RF10-B | La ficha emitida confirma la cita (RX08); el paciente no confirma manualmente. |
| RF10-C | Cita `atendida` no se cancela ni se marca inasistencia. |
| RF10-D | Cita `cancelada` no se marca atendida sin procedimiento autorizado. |
| RF10-E | Reconsulta por ausencia: la cita original se cancela con motivo "ausencia del médico" y se crea nueva cita `es_reconsulta=true`, `reconsulta_tipo='ausencia_medico'`, `es_prioritaria=true`, `cita_origen_id=original`. |
| RF10-F | Reconsulta de seguimiento: al marcar `atendida`, se sugiere nueva cita para +7 días, mismo paciente/médico/especialidad, prioritaria. |
| RF10-G | Reconsulta puede asignarse aunque el cupo del canal esté agotado (solo recepción, RX07), siempre verificando no superposición. |
| RF10-H | Cada cambio de estado se registra en `citas_estado_historial` (trazabilidad). |

## 8. Validaciones

**Servidor:**
- Transición permitida por la matriz.
- Reconsulta: paciente/médico/especialidad de la cita origen; slot válido y no superpuesto.
- `reconsulta_tipo` en enum.

## 9. Flujos

### 9.1 Confirmación por ficha (kiosco/web)
```
emitir ficha → PATCH confirmar
  → validar estado='programada'
  → UPDATE estado='confirmada' + historial
```

### 9.2 Marcar atendida + reconsulta de seguimiento
```
PATCH /api/citas/{id}/atender
  → validar estado='confirmada'
  → UPDATE estado='atendida' + historial
  → (opcional) abrir modal "¿Reconsulta la próxima semana?"
       → POST /api/citas/{id}/reconsulta {tipo:seguimiento, fecha:+7d}
```

### 9.3 Reconsulta por ausencia del médico
```
POST /api/citas/{id}/reconsulta {tipo:ausencia_medico}
  → cancelar original (motivo="ausencia del médico")
  → crear nueva cita prioritaria (+7d o fecha elegida con el paciente)
  → no consume cupo de canal (RX07), verifica superposición
```

## 10. UI / Pantallas

| Vista | Rol | Descripción |
|---|---|---|
| Agenda del día | medico/recep | Lista con botones Atender / Inasistencia |
| Modal reconsulta | recep/medico | Tipo, fecha/hora disponibles, motivo |
| Detalle cita | varios | Estado actual + historial de estados |
| Badge prioridad | varios | Marca visual "Reconsulta" / "Prioritaria" |

## 11. Criterios de aceptación

- [ ] Ficha emitida cambia a `confirmada`.
- [ ] Marcar `atendida` solo desde `confirmada`.
- [ ] Transición inválida (ej. cancelada→atendida) rechazada.
- [ ] Reconsulta por ausencia crea cita prioritaria y cancela la original.
- [ ] Reconsulta de seguimiento crea cita a +7 días.
- [ ] Reconsulta toma cupo aunque el canal esté agotado (sin superponer).

## 12. Errores

| Caso | Mensaje |
|---|---|
| Transición inválida | "No se puede cambiar de {estado} a {nuevo}." |
| Reconsulta superpuesta | "El horario elegido ya está ocupado." |
| Reconsulta sin médico | "La cita origen no tiene médico asignado." |

## 13. Tareas de implementación

1. Migración historial de estados.
2. `EstadoCitaService` con la matriz de transiciones.
3. Endpoints confirmar/atender/inasistencia/reconsulta.
4. `ReconsultaService` (ausencia + seguimiento).
5. Vista agenda con acciones y modal de reconsulta.
6. Registro en historial de estados.

## 14. Pruebas

| # | Prueba | Resultado |
|---|---|---|
| 1 | Ficha → confirmada | OK |
| 2 | Atender confirmada | `atendida` |
| 3 | Cancelada → atendida | Rechazado |
| 4 | Reconsulta ausencia | Cita prioritaria creada |
| 5 | Reconsulta seguimiento | Cita +7d |
| 6 | Reconsulta con canal agotado | Permitida |
