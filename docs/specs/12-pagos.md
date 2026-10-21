# SPEC 12 — Registro de Pagos

> Módulo: Pagos · Prioridad: **Media**
> Depende de: `03-medicos.md`, `02-pacientes.md`, `01-autenticacion.md`

---

## 1. Objetivo

Registrar los pagos de atención por parte de la recepcionista del médico (y el propio médico), con monto, método y referencia al paciente. El formulario de tarjeta Visa/Mastercard es únicamente UI (no funcional).

## 2. Alcance

- Registrar pago: `monto`, `metodo`, referencia al paciente, médico, quién lo registró.
- Formulario de tarjeta **solo visual** (Visa/Mastercard).
- Listado de pagos para el recepcionista del médico y el médico.
- QR del doctor visible (solo el médico lo sube; Spec 03).

**Fuera de alcance:** pasarela de pago real, conciliación, facturación fiscal.

## 3. Actores

- `recepcionista_medico`: registra pagos de su médico.
- `medico`: ve (y puede registrar) pagos propios.
- `admin`: ve todos (opcional).

## 4. Modelo de datos

### Tabla `pagos`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | identity PK | |
| `paciente_id` | FK `pacientes.id` | |
| `medico_id` | FK `medicos.id` | |
| `registrado_por` | FK `usuarios.id` | |
| `monto` | numeric(10,2) | > 0 |
| `metodo` | enum | `efectivo`,`visa`,`mastercard` |
| `referencia_paciente` | varchar(150) | detalle libre (nº comprobante, nota) |
| `fecha` | timestamptz | default now() |

## 5. Endpoints

| Método | Ruta | Descripción | Rol |
|---|---|---|---|
| POST | `/api/pagos` | Registrar pago | recep_medico, medico |
| GET | `/api/pagos?medico=&desde=&hasta=` | Listar pagos | recep_medico, medico |
| GET | `/api/pagos/{id}` | Detalle | recep_medico, medico |

## 6. Reglas de negocio

| Código | Regla |
|---|---|
| RFP-A | `monto > 0`. |
| RFP-B | `medico_id` debe estar asignado al recepcionista (o ser el propio médico). |
| RFP-C | La tarjeta (Visa/Mastercard) es UI; el pago queda registrado igual (monto + método). |
| RFP-D | El recepcionista de médico solo ve pagos de sus médicos (RLS). |
| RFP-E | El QR del doctor solo lo sube el médico (Spec 03). |

## 7. Validaciones

**Servidor:**
- `monto` numérico > 0, ≤ límite razonable.
- `metodo` en enum.
- Paciente y médico existen y están en el alcance del usuario.

**Cliente (JS):**
- Monto numérico; formato de tarjeta (visual, Luhn opcional) — no se envía.

## 8. Flujos

### 8.1 Registrar pago
```
[POST /api/pagos] {paciente_id, medico_id, monto, metodo, referencia_paciente}
  → validar alcance (recepcionista → su médico)
  → INSERT pagos (registrado_por = sesión)
  → 201
```

### 8.2 UI tarjeta (no funcional)
```
Formulario con campos número/vencimiento/CVV + logos Visa/Mastercard
  → al confirmar, solo se registran montó + método (sin datos de tarjeta)
  → nunca almacenar datos de tarjeta
```

## 9. UI / Pantallas

| Vista | Rol | Descripción |
|---|---|---|
| Registrar pago | recep_medico/medico | Monto, método (efectivo/tarjeta UI), paciente, referencia |
| Lista de pagos | recep_medico/medico | Tabla filtrable por fecha/médico |
| QR del doctor | medico (sube) / recep (ve) | Imagen QR + imprimir |

## 10. Criterios de aceptación

- [ ] Registrar pago guarda monto y método.
- [ ] Formulario de tarjeta es solo visual (no persiste datos de tarjeta).
- [ ] Recepcionista solo ve pagos de su médico.
- [ ] Médico ve sus pagos.
- [ ] QR visible; solo el médico puede subirlo.

## 11. Errores

| Caso | Mensaje |
|---|---|
| Monto inválido | "Ingrese un monto válido mayor a 0." |
| Fuera de alcance | 403 |
| Método inválido | "Seleccione un método de pago." |

## 12. Tareas de implementación

1. Migración `pagos`.
2. `PagoModel` + `PagoController`.
3. Vista de registro con UI de tarjeta (no funcional).
4. Listado con filtros.
5. (Reutiliza) QR del médico de Spec 03.
6. Políticas RLS.

## 13. Pruebas

| # | Prueba | Resultado |
|---|---|---|
| 1 | Registrar pago efectivo | Guardado |
| 2 | Registrar pago tarjeta (UI) | Guardado sin datos de tarjeta |
| 3 | Recepcionista de otro médico | Rechazado |
| 4 | Médico ve sus pagos | Correcto |
