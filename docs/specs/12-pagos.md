# SPEC 12 — Cobros, Adelantos, Reembolsos y Catálogo de Laboratorio

> Módulo: Pagos · Prioridad: **Alta (núcleo del modelo de negocio)**
> Depende de: `03-medicos.md`, `02-pacientes.md`, `06-citas.md`, `09-kiosco-fichas.md`, `10-historia-clinica.md`, `15-landing.md`

---

## 1. Objetivo

Cobrar la consulta con especialista en dos momentos: un **adelanto del 50 %** al reservar (web, kiosco o recepción) y el **saldo restante al terminar la consulta**. La cancelación permite **reprogramar sin perder el adelanto** o **pedir un reembolso con comprobante**; la inasistencia **retiene el adelanto**. Recepción administra además el **catálogo de exámenes con precios**, que se aplica a las órdenes del médico.

## 2. Alcance

- Precio único de consulta con especialista: **300 Bs** (configurable).
- Adelanto del **50 % = 150 Bs** al sacar ficha/reservar en web y kiosco.
- Cobro del **saldo** en recepción al finalizar la consulta.
- QR **estático** con referencia única por cita y **confirmación manual** en recepción (sin pasarela real).
- Pagos ligados a la cita (`cita_id`) o a la orden (`orden_id`), con auditoría de quién confirma.
- Cancelación con reprogramación sugerida (+7 días) o reembolso con comprobante.
- Retención del adelanto en inasistencia.
- Catálogo `examenes_laboratorio` con precio por examen, administrado por recepción.
- Cobro de órdenes de laboratorio/imagen desde recepción.

**Fuera de alcance:** pasarela de pago real, conciliación bancaria automática, facturación fiscal, devolución automática del dinero (el reembolso se entrega presencialmente).

## 3. Actores

- `paciente`: inicia el adelanto de su cita, solicita reprogramación o reembolso.
- `kiosco`: reserva y muestra el QR del adelanto; espera la confirmación de recepción.
- `recepcionista` / `recepcionista_medico`: confirma adelantos, cobra saldos y órdenes, entrega reembolsos, administra el catálogo.
- `medico`: lee el catálogo activo con precios y arma órdenes.
- `admin` / `super_admin`: todo.

## 4. Modelo de datos

### 4.1 Tabla `configuracion`
| Clave | Valor por defecto | Uso |
|---|---|---|
| `precio_consulta` | `300.00` | Precio de la consulta con especialista. |
| `adelanto_porcentaje` | `50` | Porcentaje que se cobra al reservar. |
| `pago_expira_minutos` | `120` | Vigencia del adelanto antes de liberar el cupo. |
| `qr_pago_contenido` | `VIDASALUD\|PAGO\|CUENTA-CLINICA-001` | Texto del QR estático. |
| `qr_pago_titular` | `Clínica Vida Salud` | Titular mostrado junto al QR. |

`ConfiguracionModel` expone `pago()`, `precioConsulta()`, `adelantoPorcentaje()` y la lectura/escritura de claves. El rol `clinica_app` solo tiene `SELECT`; las escrituras pasan por el rol propietario.

### 4.2 Campos de pago en `citas`
| Campo | Tipo | Notas |
|---|---|---|
| `precio_total` | numeric(10,2) | Precio de la consulta al reservar. |
| `adelanto` | numeric(10,2) | Monto exigido al reservar (50 %). |
| `saldo` | numeric(10,2) | Pendiente al terminar la consulta. |
| `estado_pago` | enum `estado_pago_cita` | Ver §4.4. |
| `pago_adelanto_id` | FK `pagos.id` | Pago que confirma el adelanto. |
| `pago_expira_en` | timestamptz | Vencimiento del adelanto pendiente. |
| `reembolso_codigo` | varchar(20) | Único, `REE-XXXXXX`. |
| `reembolso_monto` | numeric(10,2) | Monto a devolver. |
| `reembolso_solicitado_en` | timestamptz | Cuándo se generó el comprobante. |
| `reembolso_entregado_en` | timestamptz | Cuándo recepción entregó el dinero. |
| `reembolso_registrado_por` | FK `usuarios.id` | Quién entregó el reembolso. |

Las citas anteriores a la migración `013` se backfillean a `estado_pago='sin_pago'` para no aparecer como adelantos pendientes.

### 4.3 Tabla `pagos` (ampliada)
| Campo | Tipo | Notas |
|---|---|---|
| `id` | identity PK | |
| `paciente_id` / `medico_id` / `registrado_por` | FK | |
| `monto` | numeric(10,2) | `> 0` (también en reembolsos). |
| `metodo` | enum `metodo_pago` | `efectivo`, `visa`, `mastercard`, `qr`, `pasarela`. |
| `referencia_paciente` | varchar(150) | Nota libre. |
| `cita_id` | FK `citas.id` | Pago de consulta. |
| `orden_id` | FK `ordenes.id` | Pago de laboratorio/imagen. |
| `tipo` | varchar(20) | `consulta` (legacy), `adelanto`, `saldo`, `laboratorio`, `reembolso`. |
| `estado` | varchar(20) | `confirmado` por defecto. |
| `referencia` | varchar(80) | Referencia del QR o comprobante. |
| `confirmado_por` | FK `usuarios.id` | Quién validó el dinero. |
| `confirmado_en` | timestamptz | |
| `reembolso_de` | FK `pagos.id` | Pago original que se devuelve. |

Los reembolsos se registran como pago **positivo** con `tipo='reembolso'` y `reembolso_de` apuntando al adelanto original.

### 4.4 Estados de pago de la cita

| Estado | Significado |
|---|---|
| `pendiente` | Adelanto exigido y aún no confirmado. |
| `adelanto_pagado` | Adelanto confirmado; queda saldo. |
| `pagado` | Consulta totalmente pagada. |
| `reembolso_pendiente` | Cancelación definitiva con comprobante emitido. |
| `reembolsado` | Recepción entregó el reembolso. |
| `transferido` | Adelanto movido a una cita reprogramada. |
| `perdido` | Inasistencia: el adelanto se retiene. |
| `expirado` | El plazo del adelanto venció y el cupo se liberó. |
| `sin_pago` | Cita legacy o sin cobro asociado. |

### 4.5 Catálogo `examenes_laboratorio`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | identity PK | |
| `codigo` | varchar(20) | Único, opcional. |
| `nombre` | varchar(150) | |
| `tipo` | enum `tipo_orden` | `laboratorio`, `imagen`, `otro`. |
| `precio` | numeric(10,2) | `>= 0`. |
| `activo` | boolean | default `true`. |

`orden_detalle` guarda `examen_id` y `precio` **copiado al emitir la orden**; `ordenes.total` suma el detalle. Un cambio posterior de precio no altera órdenes ya emitidas.

## 5. Endpoints

| Método | Ruta | Descripción | Rol |
|---|---|---|---|
| GET | `/api/citas/{id}/cobro` | Resumen de precio, adelanto, saldo, QR y pagos. | dueño de la cita, recepción, médico |
| POST | `/api/citas/{id}/adelanto` | Inicia/recalcula el adelanto (idempotente). | dueño de la cita, recepción, médico |
| POST | `/api/citas/{id}/adelanto/confirmar` | Recepción confirma el adelanto. | recepción, admin |
| POST | `/api/citas/{id}/saldo` | Cobra el saldo restante. | recepción, admin |
| GET | `/api/cobros/pendientes` | Cola de adelantos, saldos y reembolsos. | recepción, admin |
| POST | `/api/cobros/expirar` | Libera adelantos vencidos (idempotente). | recepción, admin |
| GET | `/api/cobros/ordenes-pendientes` | Órdenes de laboratorio por cobrar. | recepción, admin |
| POST | `/api/ordenes/{id}/pago` | Cobra una orden. | recepción, admin |
| POST | `/api/citas/{id}/reembolso/entregar` | Marca el reembolso como entregado. | recepción, admin |
| GET | `/api/examenes?tipo=&q=&activo=&solo_activos=&page=&per_page=` | Catálogo. | recepción, admin, médico |
| POST | `/api/examenes` | Crea examen con precio. | recepción, admin |
| GET | `/api/examenes/{id}` | Detalle. | recepción, admin, médico |
| PUT | `/api/examenes/{id}` | Actualiza nombre, tipo, precio y estado. | recepción, admin |
| PATCH | `/api/examenes/{id}/estado` | Activa/desactiva. | recepción, admin |
| POST | `/api/pagos` | Registro manual legacy de pago. | recepción médica, médico |

La reserva web (`POST /api/public/reservar`) y la del kiosco (`POST /api/kiosco/reservar`) devuelven además un bloque `pago` con `precio_total`, `adelanto`, `saldo`, `expira_en`, `referencia`, `qr_contenido` y `qr_titular`.

## 6. Reglas de negocio

| Código | Regla |
|---|---|
| RFP-A | La consulta con especialista cuesta un precio único configurable (300 Bs por defecto). |
| RFP-B | Al reservar se exige un adelanto del 50 % (150 Bs) en todos los canales. |
| RFP-C | El QR es estático; la referencia única es `PAGO-{codigo_cita}` y recepción confirma manualmente. |
| RFP-D | El adelanto se confirma una sola vez; repetir la confirmación es idempotente. |
| RFP-E | El saldo se cobra al terminar la consulta y no puede superar el pendiente. |
| RFP-F | Confirmar el adelanto deja la cita `confirmada` y habilita la ficha del kiosco. |
| RFP-G | El adelanto pendiente vence a los 120 min y libera el cupo (`expirado`). |
| RFP-H | Cancelar con adelanto pagado permite reprogramar sin volver a cobrar (se transfiere el pago). |
| RFP-I | La reprogramación crea una reconsulta ligada a la cita origen y deja la origen `transferido`. |
| RFP-J | La cancelación definitiva con adelanto genera comprobante `REE-XXXXXX` y avisa a recepción. |
| RFP-K | La inasistencia marca el adelanto como `perdido` (sin reembolso). |
| RFP-L | Las órdenes usan el precio del catálogo al emitirse y se cobran completas o por partes. |
| RFP-M | Solo recepción/admin confirman dinero y entregan reembolsos; el paciente solo inicia su adelanto. |
| RFP-N | Todo movimiento queda ligado a `cita_id` u `orden_id` y a quién lo registró/confirmó. |
| RFP-O | `monto > 0`; los reembolsos se registran como pago positivo con `tipo='reembolso'`. |

## 7. Validaciones

**Servidor:**
- `metodo` en `efectivo`, `visa`, `mastercard`, `qr`, `pasarela`.
- El monto del adelanto debe coincidir con el adelanto de la cita (tolerancia 0,01).
- El monto del saldo no puede superar el saldo pendiente.
- El monto de la orden no puede superar su saldo.
- No se confirma adelanto de una cita `cancelada`/`inasistencia`; si venció → `409 PAYMENT_EXPIRED`.
- Cobrar saldo sin adelanto confirmado → `409 PAYMENT_REQUIRED`.
- Entregar un reembolso que no está `reembolso_pendiente` → `409 INVALID_STATE`.
- El catálogo exige nombre ≤ 150, tipo válido y precio ≥ 0; el médico solo lee.

**Cliente (JS):**
- Formatea montos y deshabilita el botón hasta tener referencia/QR.
- El kiosco consulta el estado del adelanto por polling antes de emitir la ficha.

## 8. Flujos

### 8.1 Reserva web con adelanto
```
[POST /api/public/reservar]
  → crea cita + ficha (la ficha confirma la cita)
  → aplica precio_total=300, adelanto=150, saldo=150, estado_pago='pendiente'
  → devuelve bloque pago con QR, referencia y vencimiento
  → el paciente paga por QR y recepción confirma
```

### 8.2 Reserva en kiosco
```
[POST /api/kiosco/reservar] → cita programada + adelanto pendiente + bloque pago
[Pantalla pago] → muestra QR y hace polling a /api/kiosco/citas/{id}/pago
[POST /api/citas/{id}/adelanto/confirmar] (recepción)
  → estado_pago='adelanto_pagado', cita='confirmada'
[POST /api/kiosco/ficha] → emite la ficha
```

### 8.3 Confirmar adelanto
```
[POST /api/citas/{id}/adelanto/confirmar] {metodo, monto?, referencia?}
  → valida monto = adelanto
  → INSERT pagos (tipo='adelanto', estado='confirmado', cita_id)
  → citas.estado_pago='adelanto_pagado', pago_adelanto_id
  → si estaba programada → confirmada + historial
```

### 8.4 Cobrar saldo
```
[POST /api/citas/{id}/saldo] {metodo, monto?}
  → exige estado_pago ∈ {adelanto_pagado, sin_pago}
  → INSERT pagos (tipo='saldo', cita_id)
  → estado_pago='pagado', saldo=0
```

### 8.5 Cobrar orden de laboratorio
```
[POST /api/ordenes/{id}/pago] {metodo, monto?}
  → saldo = orden.total - SUM(pagos confirmados de la orden)
  → INSERT pagos (tipo='laboratorio', orden_id)
  → si cubre el total, la orden sale de la cola de cobro
```

### 8.6 Cancelación con reprogramación
```
[GET /api/citas/{id}/cancelacion/opciones]
  → sugiere misma hora + 7 días, slots libres y política
[POST /api/citas/{id}/cancelacion/reprogramar] {fecha?, hora?, motivo?}
  → crea reconsulta (reconsulta_tipo='reprogramacion', cita_origen_id)
  → transfiere el adelanto como nuevo pago ligado a la nueva cita
  → origen: estado='cancelada', estado_pago='transferido'
```

### 8.7 Cancelación definitiva con reembolso
```
[POST /api/citas/{id}/cancelacion/definitiva] {motivo}
  → exige motivo
  → si hay adelanto/pago: genera REE-XXXXXX, estado_pago='reembolso_pendiente',
    cita='cancelada', encola aviso a recepción
  → devuelve cita + comprobante
[POST /api/citas/{id}/reembolso/entregar] {metodo}
  → INSERT pagos (tipo='reembolso', reembolso_de=adelanto)
  → estado_pago='reembolsado'
```

### 8.8 Expiración e inasistencia
```
[POST /api/cobros/expirar] → citas pendientes vencidas: estado_pago='expirado', cita='cancelada'
[PATCH /api/citas/{id}/inasistencia] → estado='inasistencia', estado_pago='perdido'
```

## 9. UI / Pantallas

| Vista | Rol | Descripción |
|---|---|---|
| Reserva web | paciente | Resumen con precio, adelanto, saldo, QR y vencimiento. |
| Kiosco · pago | kiosco | QR, referencia, saldo y espera de confirmación. |
| Caja de recepción | recepción | Adelantos, saldos, reembolsos y órdenes pendientes; modales de cobro. |
| Catálogo de exámenes | recepción | Alta/edición de precio, activar/desactivar. |
| Pedidos del médico | médico | Selector de catálogo con precio y total estimado. |
| Portal del paciente | paciente | Cancelar: reprogramar (+7 días u otro horario) o reembolso con comprobante. |

## 10. Criterios de aceptación

- [ ] La consulta cuesta 300 Bs en todas las especialidades.
- [ ] Web y kiosco exigen un adelanto de 150 Bs y muestran QR + referencia.
- [ ] Recepción confirma el adelanto una sola vez y el kiosco habilita la ficha.
- [ ] El saldo se cobra al terminar la consulta y la cita queda `pagado`.
- [ ] Cancelar permite reprogramar sin volver a pagar el adelanto.
- [ ] Cancelar definitivamente genera comprobante de reembolso y avisa a recepción.
- [ ] La inasistencia retiene el adelanto (`perdido`).
- [ ] El adelanto vencido libera el cupo (`expirado`).
- [ ] Recepción administra precios del catálogo y el médico los ve al ordenar.
- [ ] Una orden conserva el precio con el que se emitió.

## 11. Errores

| Caso | Código / mensaje |
|---|---|
| Monto distinto al adelanto | `422 VALIDATION_ERROR` · "El adelanto es de 150,00 Bs." |
| Saldo mayor al pendiente | `422 VALIDATION_ERROR` · "El saldo es de 150,00 Bs." |
| Adelanto vencido | `409 PAYMENT_EXPIRED` |
| Saldo sin adelanto | `409 PAYMENT_REQUIRED` |
| Confirmar cita cancelada | `409 INVALID_STATE` |
| Reembolso ya entregado | `409 INVALID_STATE` |
| Cita ajena | `403 FORBIDDEN` |
| Examen inexistente | `404 NOT_FOUND` |

## 12. Tareas de implementación

1. Migración `013` (`configuracion`, campos de pago, `pagos`, `examenes_laboratorio`, totales de órdenes, grants y backfill).
2. `ConfiguracionModel` y `ExamenModel`.
3. `CobroService` (adelanto, saldo, orden, reembolso, expiración, pendientes).
4. `CancelacionService` (opciones, reprogramación, cancelación definitiva, comprobante).
5. `CobroApiController` y `ExamenApiController`.
6. Caja de recepción, catálogo y modal de cancelación del portal.
7. Integración en reserva web, kiosco, ficha y órdenes del médico.
8. Suite `bin/test-cobros.php`.

## 13. Pruebas

| # | Prueba | Resultado |
|---|---|---|
| 1 | Configuración 300/50 %/150 Bs | Correcta |
| 2 | Iniciar adelanto con CSRF e idempotencia | Correcto |
| 3 | Kiosco bloquea ficha sin adelanto | Bloqueado |
| 4 | Recepción confirma adelanto y cita | Confirmada |
| 5 | Cobro de saldo y cita `pagado` | Correcto |
| 6 | Catálogo: crear, editar precio, desactivar | Correcto |
| 7 | Orden copia precio y sale de la cola al pagar | Correcto |
| 8 | Cancelación definitiva + comprobante | Emitido |
| 9 | Reprogramación transfiere adelanto | Sin doble cobro |
| 10 | Expiración libera cupo | Correcto |
| 11 | Inasistencia retiene adelanto | `perdido` |
| 12 | Paciente no confirma dinero ni accede a citas ajenas | `403` |
