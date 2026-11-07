# SPEC 09 — Kiosco y Emisión de Fichas

> Módulo: Kiosco / Fichas · Prioridad: **Alta (núcleo)**
> Depende de: `01-autenticacion.md`, `04-especialidades.md`, `05-horarios-cupos.md`, `06-citas.md`

---

## 1. Objetivo

Disponer de un kiosco (iPad, web responsive) para que los pacientes sin acceso web obtengan/consulten su ficha de atención: ingresa CI, elige especialidad, ve horarios disponibles del médico, paga el **adelanto del 50 %** por QR y, cuando recepción lo confirma, obtiene una ficha con sus datos. La ficha es el ticket de la cita; sin ficha no se atiende (RX08).

## 2. Alcance

- Kiosco público (dispositivo iPad, `/kiosco`).
- Ingreso por **CI**; si el paciente no existe, se registra en el momento (sin cuenta).
- Selección de especialidad (con restricciones sexo/edad) → médico → horario (cupos kiosco).
- Cobro del **adelanto de 150 Bs** con QR estático y confirmación manual de recepción (Spec 12).
- Emisión de **ficha** con código, código de acceso y código QR.
- La emisión confirma la cita (`programada → confirmada`).

**Fuera de alcance:** impresión física (la ficha es pantalla + QR imprimible); pasarela de pago real.

## 3. Actores

- `kiosco` (dispositivo público): emite fichas.
- `recepcionista`: puede reimprimir/consultar fichas.

## 4. Modelo de datos

### Tabla `fichas`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | identity PK | |
| `codigo` | varchar(20) | único, `FIC-0001` |
| `cita_id` | FK `citas.id` | 1–1 |
| `canal` | enum | `web`,`kiosco` |
| `codigo_acceso` | varchar(4) | 4 dígitos |
| `emitida_at` | timestamptz | default now() |
| `created_at` | timestamptz | |

### Sesión de kiosco
- Token firmado (HMAC) emitido al iniciar flujo (`app_kiosk_token`), expiración corta (10 min).
- No usa sesión de usuario.

### Pago del kiosco
La cita reservada en el kiosco guarda en `citas` el `precio_total` (300 Bs), `adelanto` (150 Bs), `saldo` (150 Bs), `estado_pago='pendiente'` y `pago_expira_en` (120 min). El detalle del modelo está en `12-pagos.md`.

## 5. Endpoints (kiosco)

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| POST | `/api/kiosco/sesion` | Inicia sesión de kiosco (token) | pública (device key) |
| POST | `/api/kiosco/identificar` | CI → datos paciente / flag nuevo | token kiosco |
| POST | `/api/kiosco/registrar` | Alta rápida paciente (sin cuenta) | token kiosco |
| GET | `/api/kiosco/especialidades` | Especialidades activas | token kiosco |
| GET | `/api/kiosco/medicos?especialidad=` | Médicos activos por especialidad | token kiosco |
| GET | `/api/kiosco/disponibilidad?medico=&fecha=` | Slots cupo kiosco | token kiosco |
| POST | `/api/kiosco/reservar` | Crear cita (origen=kiosco) + adelanto pendiente | token kiosco |
| GET | `/api/kiosco/citas/{id}/pago` | Estado del adelanto para esperar la confirmación | token kiosco |
| POST | `/api/kiosco/ficha` | Emitir ficha + confirmar cita | token kiosco |
| GET | `/api/kiosco/ficha/{codigo}` | Reconsultar ficha en pantalla | token kiosco |

## 6. Reglas de negocio

| Código | Regla |
|---|---|
| RFK-A | El kiosco crea pacientes sin cuenta (`usuario_id=null`, RX12). |
| RFK-B | Los cupos del kiosco son los de `cupos_kiosco` (RX01). |
| RFK-C | Se aplican restricciones de sexo/edad (RX02/RX03) antes de mostrar médicos. |
| RFK-D | Emitir ficha confirma la cita (RF10-B/RX08). |
| RFK-E | Un código de acceso de 4 dígitos por ficha; la ficha se muestra en pantalla (imprimible). |
| RFK-F | Sin ficha el paciente no es atendido (regla operativa). |
| RFK-G | La reserva desde kiosco revalida disponibilidad en el servidor (RN13). |
| RFK-I | La reserva deja un adelanto de 150 Bs por QR y la cita no emite ficha hasta que recepción lo confirme. |
| RFK-J | La pantalla de pago consulta `/api/kiosco/citas/{id}/pago`; al confirmarse el adelanto emite la ficha. |
| RFK-K | Si el adelanto vence (120 min) la cita se cancela y el cupo se libera; el kiosco vuelve a la bienvenida. |

## 7. Validaciones

**Servidor:**
- Formato CI; si existe → reconocer; si no → permitir registro rápido (campos mínimos: CI, nombres, apellidos, fecha_nac, sexo, teléfono).
- Elegibilidad sexo/edad.
- Slot válido, no superpuesto, con cupo kiosco.
- Adelanto confirmado antes de emitir la ficha (`estado_pago` ∈ `adelanto_pagado`, `pagado`, `sin_pago`).
- Token de kiosco válido y no expirado.

**Cliente (JS):**
- Teclado numérico para CI; flujo guiado; botones grandes.

## 8. Flujo del kiosco (touch-friendly)

```
1. Pantalla de bienvenida "Toque para sacar su ficha"
2. Ingreso CI (teclado numérico en pantalla)
   → POST /api/kiosco/identificar
      → existe: mostrar nombre (confirmación)
      → no existe: formulario rápido de registro
3. Elegir especialidad (botones grandes)
4. Elegir médico (cards)
5. Elegir fecha y horario (chips de slots disponibles)
6. Confirmar → POST /api/kiosco/reservar
   → pantalla de PAGO: precio 300 Bs, adelanto 150 Bs, saldo 150 Bs, QR y referencia
   → polling a /api/kiosco/citas/{id}/pago hasta que recepción confirme el adelanto
7. Al confirmar el adelanto → POST /api/kiosco/ficha
8. Pantalla de FICHA:
   "Paciente: ___ | Especialidad: ___ | Dr(a): ___ | Fecha: ___ | Hora: ___ 
    Código: FIC-0001 | Acceso: 8994 | [QR]  [Imprimir]"
9. Auto-reset a bienvenida (timer 30s)
```

## 9. UI / Pantallas (iPad)

| # | Pantalla | Elemento |
|---|---|---|
| 1 | Bienvenida | Ícono + CTA grande |
| 2 | Identificación | Teclado numérico CI |
| 3 | Registro rápido | Formulario simplificado |
| 4 | Especialidades | Botones grandes (destacadas primero) |
| 5 | Médicos | Cards |
| 6 | Fecha/hora | Calendario + chips de slots |
| 7 | Confirmación | Resumen + botón confirmar |
| 8 | Pago del adelanto | Precio, adelanto, saldo, QR, referencia y "esperando confirmación" |
| 9 | Ficha | Datos + QR + imprimir |

- Indicador de progreso: `1. Especialidad → 2. Médico → 3. Hora → 4. Pago → 5. Ficha`.
- Paleta azul/verde, tipografía grande.

## 10. Criterios de aceptación

- [ ] Paciente con CI existente se reconoce y puede sacar ficha.
- [ ] Paciente nuevo se registra en el kiosco (sin cuenta) y saca ficha.
- [ ] Solo aparecen especialidades compatibles con sexo/edad.
- [ ] Solo aparecen slots con cupo kiosco disponible.
- [ ] La ficha muestra paciente, especialidad, médico, fecha, hora, código y acceso.
- [ ] La reserva muestra el adelanto de 150 Bs con QR, referencia y saldo.
- [ ] Sin adelanto confirmado no se emite ficha; al confirmarlo se emite automáticamente.
- [ ] Emitir ficha deja la cita en `confirmada`.
- [ ] Restricción: ginecología no aparece para sexo masculino.
- [ ] Cupos kiosco agotados → sin slots en kiosco (pero web sí).

## 11. Errores

| Caso | Mensaje (kiosco) |
|---|---|
| CI inválida | "Ingrese una cédula válida." |
| Sin cupos | "No hay horarios disponibles. Intente otra fecha o especialidad." |
| No elegible | "Especialidad no disponible para el paciente." |
| Adelanto pendiente | "Paga el adelanto en recepción para obtener tu ficha." |
| Adelanto vencido | "El plazo venció. Vuelve a reservar." |
| Token expirado | Reinicia el flujo (vuelve a bienvenida). |

## 12. Tareas de implementación

1. Migración `fichas` + secuencia `FIC`.
2. `KioskMiddleware` (token firmado HMAC).
3. `KioscoController` (identificar, registrar, especialidades, médicos, disponibilidad, reservar, estado de pago, ficha).
4. `FichaService` (emitir ficha + confirmar cita + QR).
5. `KioscoService::estadoPago` para esperar la confirmación del adelanto (Spec 12).
6. Vistas de kiosco touch-friendly + pantalla de pago + teclado numérico + generador QR.
7. Auto-reset por inactividad.

## 13. Pruebas

| # | Prueba | Resultado |
|---|---|---|
| 1 | CI existente → ficha | Emitida y confirmada |
| 2 | CI nueva → registro + ficha | Emitidos |
| 3 | Especialidad no elegible | No mostrada |
| 4 | Cupos kiosco agotados | Sin slots |
| 5 | Ficha muestra datos correctos | Correcto |
| 6 | Reserva muestra adelanto y QR | Correcto |
| 7 | Ficha bloqueada hasta confirmar adelanto | Bloqueada |
| 8 | Cita queda `confirmada` al emitir | Sí |
