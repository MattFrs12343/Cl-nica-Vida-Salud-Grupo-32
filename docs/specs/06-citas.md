# SPEC 06 — Registro, Duplicados, Reprogramación y Cancelación de Citas (RF06–RF09)

> Módulo: Citas (núcleo) · Prioridad: **Alta (núcleo)**
> Depende de: `02-pacientes.md`, `03-medicos.md`, `04-especialidades.md`, `05-horarios-cupos.md`, `07-estados-asistencia.md`, `99-modelo-datos-rls.md`

---

## 1. Objetivo

Registrar citas verificando disponibilidad en el servidor, impedir superposiciones y conflictos (RN07/RF07), permitir reprogramar (RF08) y cancelar (RF09) con reprogramación sugerida o reembolso del adelanto, generando un código único de cita y registrando origen y prioridad.

## 2. Alcance

- RF06: registrar cita (paciente, especialidad, médico, fecha, hora, motivo).
- RF07: prevención de citas duplicadas/superpuestas y fuera de horario.
- RF08: reprogramación de citas programadas (y confirmadas) con validación.
- RF09: cancelación con motivo, reprogramación sugerida (+7 días) o reembolso con comprobante. Se permite cancelar el mismo día.
- Generación de `codigo` de cita (`CM-####`).
- Registro de `origen` (web/kiosco/recepcion) y flags `es_reconsulta`, `es_prioritaria`.
- Registro del estado de pago de la cita (adelanto, saldo y reembolso — Spec 12).

**Fuera de alcance:** reprogramación directa de citas atendidas/canceladas (requiere procedimiento autorizado — ver RF10).

## 3. Actores

- `recepcionista` / `recepcionista_medico`: registrar, reprogramar, cancelar (su alcance).
- `paciente` (web): registrar y cancelar sus propias citas.
- `kiosco`: registrar (flujo propio, Spec 09).
- `admin`: todo.

## 4. Modelo de datos

### Tabla `citas`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | identity PK | |
| `codigo` | varchar(20) | único, `CM-0001` |
| `paciente_id` | FK `pacientes.id` | RN03 |
| `medico_id` | FK `medicos.id` | RN03 |
| `especialidad_id` | FK `especialidades.id` | |
| `fecha` | date | |
| `hora_inicio` | time | |
| `hora_fin` | time | = inicio + duración |
| `estado` | enum | `programada`,`confirmada`,`atendida`,`cancelada`,`inasistencia` |
| `origen` | enum | `web`,`kiosco`,`recepcion` |
| `es_prioritaria` | boolean | default false (reconsultas) |
| `es_reconsulta` | boolean | default false |
| `reconsulta_tipo` | enum | `ausencia_medico`,`seguimiento`,`reprogramacion` (nullable) |
| `cita_origen_id` | FK `citas.id` | nullable (cita que origina la reconsulta) |
| `motivo` | varchar(200) | opcional |
| `motivo_cancelacion` | varchar(200) | nullable |
| `precio_total` | numeric(10,2) | 300 Bs por defecto (Spec 12) |
| `adelanto` | numeric(10,2) | 50 % al reservar |
| `saldo` | numeric(10,2) | pendiente al terminar |
| `estado_pago` | enum `estado_pago_cita` | `pendiente`,`adelanto_pagado`,`pagado`,`reembolso_pendiente`,`reembolsado`,`transferido`,`perdido`,`expirado`,`sin_pago` |
| `pago_adelanto_id` | FK `pagos.id` | nullable |
| `pago_expira_en` | timestamptz | nullable |
| `reembolso_codigo` | varchar(20) | único, `REE-XXXXXX` |
| `reembolso_monto` | numeric(10,2) | |
| `reembolso_solicitado_en` | timestamptz | |
| `reembolso_entregado_en` | timestamptz | |
| `fecha_registro` | timestamptz | default now() |
| `created_at`,`updated_at` | timestamptz | |

### Índice anti-superposición (RN07)
```sql
CREATE UNIQUE INDEX ux_cita_no_superpone
  ON citas (medico_id, fecha, hora_inicio)
  WHERE estado <> 'cancelada';
```
> Además, validación por rango (para duraciones variables) en el service: comprobar que `[hora_inicio,hora_fin)` no solape ninguna cita activa del médico.

## 5. Endpoints

| Método | Ruta | Descripción | Rol |
|---|---|---|---|
| POST | `/api/citas` | Registrar cita | recepción, paciente, kiosco |
| GET | `/api/citas` | Listar (filtros fecha/medico/especialidad/estado) | según rol |
| GET | `/api/citas/{id}` | Detalle | según rol |
| PUT | `/api/citas/{id}/reprogramar` | Cambiar fecha/hora (recepción) | recepción |
| PATCH | `/api/citas/{id}/cancelar` | Cancelar con motivo | recepción, paciente |
| GET | `/api/citas/{id}/cancelacion/opciones` | Sugerencia +7 días, slots libres y política de reembolso | dueño, recepción, médico |
| POST | `/api/citas/{id}/cancelacion/reprogramar` | Reprogramar como reconsulta transfiriendo el adelanto | dueño, recepción, médico |
| POST | `/api/citas/{id}/cancelacion/definitiva` | Cancelar y generar comprobante de reembolso | dueño, recepción, médico |
| GET | `/api/disponibilidad` | (ver Spec 05) | — |

## 6. Reglas de negocio

| Código | Regla |
|---|---|
| RF06-A | Toda cita requiere paciente, médico y especialidad válidos (RN03). |
| RF06-B | El médico debe estar activo (RN04). |
| RF06-C | El slot debe estar dentro del horario del médico (RN05). |
| RF06-D | No citas en fecha/hora pasada (RN06). |
| RF06-E | Duración = configuración del médico (RN08). |
| RF06-F | Al guardar, se **revalida** disponibilidad en el servidor (RN13). |
| RF07-A | Un médico no puede tener citas superpuestas (RN07) → rechazo explicativo. |
| RF07-B | Validación en servidor + índice ÚNICO parcial + transacción. |
| RF08-A | Solo se reprograman citas en estado `programada`/`confirmada`. |
| RF08-B | Reprogramar revalida disponibilidad y evita conflictos. |
| RF08-C | `atendida`/`cancelada`/`inasistencia` no se reprograman directamente. |
| RF09-A | Cancelar registra estado `cancelada` + motivo; no se elimina (RN10). |
| RF09-B | Cancelada libera el slot (RN09). |
| RF09-C | Se puede cancelar hasta el mismo día de la cita; el motivo es obligatorio en la cancelación definitiva. |
| RF09-D | Cita `atendida` no puede cancelarse como si no hubiera ocurrido. |
| RF09-E | Con adelanto pagado, cancelar ofrece reprogramar (+7 días, misma hora) o reembolso con comprobante. |
| RF09-F | La reprogramación crea una reconsulta (`reconsulta_tipo='reprogramacion'`) y transfiere el adelanto; la cita origen queda `transferido`. |
| RF09-G | La cancelación definitiva con adelanto deja `estado_pago='reembolso_pendiente'` y avisa a recepción. |
| RF09-H | La inasistencia retiene el adelanto (`estado_pago='perdido'`). |

## 7. Validaciones

**Servidor:**
- IDs existentes y activos (paciente, médico, especialidad).
- Elegibilidad sexo/edad (RX02/RX03) vía `EspecialidadModel::esElegiblePaciente()`.
- Fecha no pasada; hora dentro de franja; slot no ocupado; cupo de canal disponible.
- Motivo ≤ 200 chars.
- Cancelación: estado `programada`/`confirmada`; motivo obligatorio en la cancelación definitiva.
- Reprogramación: fecha/hora distinta a la original, slot libre en el canal del usuario.

**Cliente (JS):**
- Fecha mínima = mañana; selectores dependientes; deshabilitar días sin disponibilidad.

## 8. Flujos

### 8.1 Registrar cita (RF06)
```
[POST /api/citas] {paciente_id, medico_id, especialidad_id, fecha, hora, motivo}
  BEGIN
  → validar paciente/médico/especialidad (existen, activos)
  → validar elegibilidad sexo/edad
  → validar fecha no pasada + hora en franja
  → SELECT ... FOR UPDATE (lock) sobre el slot del médico
  → verificar no superposición (SELECT citas activas rango)
  → verificar cupo del canal (origen): COUNT(origen=canal) < cupo(franja)
  → INSERT citas (codigo CM-####, estado='programada', origen)
  COMMIT
  → si conflicto → ROLLBACK + 409 (pruebas #3/#4/#5 enunciado)
  → si ok → generar ficha (Spec 09) + correo confirmación (Spec 11)
```

### 8.2 Prevención de duplicados (RF07)
```
Intento de cita 09:00–09:30 cuando ya existe otra activa en ese slot:
  → índice único o chequeo rango → 409 "El médico ya tiene una cita en ese horario."
Intento fuera de horario:
  → 422 "El horario no está dentro de la atención del médico."
```

### 8.3 Reprogramación (RF08)
```
[PUT /api/citas/{id}/reprogramar] {fecha, hora}
  → validar estado ∈ {programada, confirmada}
  → revalidar disponibilidad (no superposición, cupo, franja, fecha no pasada)
  → BEGIN ... lock ... UPDATE fecha/hora/hora_fin ... COMMIT
  → notificar al paciente (correo)
```

### 8.4 Cancelación (RF09)
```
[PATCH /api/citas/{id}/cancelar] {motivo}
  → validar estado ∈ {programada, confirmada}
  → UPDATE estado='cancelada', motivo_cancelacion=?, updated_at=now()
  → slot liberado automáticamente (RN09)
  → notificar al paciente
```

### 8.5 Cancelación con reprogramación o reembolso (RF09-E/F/G)
```
[GET /api/citas/{id}/cancelacion/opciones]
  → propone misma hora + 7 días y lista slots libres del médico
  → informa si puede reembolsar y el monto
[POST /api/citas/{id}/cancelacion/reprogramar] {fecha?, hora?, motivo?}
  → crea reconsulta ligada a la cita origen
  → transfiere el adelanto a la nueva cita (sin doble cobro)
  → origen: cancelada + estado_pago='transferido'
[POST /api/citas/{id}/cancelacion/definitiva] {motivo}
  → si hay adelanto/pago: REE-XXXXXX + estado_pago='reembolso_pendiente'
  → aviso a recepción y comprobante para el paciente
```

## 9. UI / Pantallas

| Vista | Rol | Descripción |
|---|---|---|
| Programar cita | recepción | Wizard: paciente → especialidad → médico → fecha → slot → motivo |
| Reprogramar | recepción | Cita actual + nuevos slots |
| Cancelar | recepción/paciente | Reprogramar (+7 días u otro horario) o reembolso con comprobante |
| Mis citas (web) | paciente | Reservar, ver, cancelar |
| Cita detalle | varios | Datos, estado, ficha, historial |

## 10. Criterios de aceptación

- [ ] Programar cita en horario disponible → registrada (prueba #3 enunciado).
- [ ] Cita superpuesta para el mismo médico → rechazada (prueba #4 enunciado).
- [ ] Reserva fuera del horario → rechazada (prueba #5 enunciado).
- [ ] Reprogramar respeta disponibilidad.
- [ ] Cancelar cambia a `cancelada` y conserva historial (prueba #6 enunciado).
- [ ] Cancelar el mismo día está permitido (motivo obligatorio en la cancelación definitiva).
- [ ] Cancelar con adelanto ofrece reprogramar (+7 días) o reembolso con comprobante.
- [ ] Reprogramar no vuelve a cobrar el adelanto.
- [ ] Cita atendida no se cancela (RF09-D).
- [ ] Se genera código `CM-####` único.

## 11. Errores

| Caso | Mensaje |
|---|---|
| Slot ocupado | "El médico ya tiene una cita en ese horario." |
| Fuera de horario | "El horario no está dentro de la atención del médico." |
| Fecha pasada | "No se pueden programar citas en fechas pasadas." |
| Cupo agotado | "No hay cupos disponibles para este canal en ese horario." |
| No elegible | "La especialidad no está disponible para este paciente." |
| Cancelación sin motivo | "Indica el motivo de la cancelación." |
| Reprogramación sin cambio | "Elige una fecha u hora distinta a la cita original." |
| Reembolso pendiente | "La cita tiene un reembolso pendiente; recepción debe entregarlo antes de reprogramar." |
| Estado inválido | "Esta cita no puede reprogramarse/cancelarse en su estado actual." |

## 12. Tareas de implementación

1. Migración `citas` + índice único parcial + secuencia `CM` + campos de pago (Spec 12).
2. `CitaModel` (CRUD, selección con bloqueo, conteos por canal).
3. `DisponibilidadService` (reutilizado).
4. `CitaService` (registrar, reprogramar, cancelar con transacciones).
5. `CancelacionService` (opciones, reprogramación con transferencia y reembolso).
6. `CitaController` + validaciones.
7. Vistas Gentelella + wizard JS.
8. Integración ficha (Spec 09), cobros (Spec 12) y correo (Spec 11/00).

## 13. Pruebas

| # | Prueba | Regla | Resultado |
|---|---|---|---|
| 1 | Programar en slot libre | RF06 | Registrada |
| 2 | Programar superpuesta | RF07 | Rechazada |
| 3 | Fuera de horario | RF05/RF07 | Rechazada |
| 4 | Fecha pasada | RF06-D | Rechazada |
| 5 | Reprogramar válido | RF08 | Modificada |
| 6 | Reprogramar a slot ocupado | RF08-B | Rechazada |
| 7 | Cancelar el mismo día | RF09-C | Cancelada |
| 8 | Cancelar con adelanto y reprogramar | RF09-E/F | Sin doble cobro |
| 9 | Cancelar con adelanto y reembolsar | RF09-G | Comprobante emitido |
| 10 | Cancelar atendida | RF09-D | Rechazada |
| 11 | Concurrencia misma slot | RF07-B | Solo una cita creada |
