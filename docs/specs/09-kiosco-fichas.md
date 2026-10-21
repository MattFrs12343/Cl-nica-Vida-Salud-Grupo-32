# SPEC 09 — Kiosco y Emisión de Fichas

> Módulo: Kiosco / Fichas · Prioridad: **Alta (núcleo)**
> Depende de: `01-autenticacion.md`, `04-especialidades.md`, `05-horarios-cupos.md`, `06-citas.md`

---

## 1. Objetivo

Disponer de un kiosco (iPad, web responsive) para que los pacientes sin acceso web obtengan/consulten su ficha de atención: ingresa CI, elige especialidad, ve horarios disponibles del médico y obtiene una ficha con sus datos. La ficha es el ticket de la cita; sin ficha no se atiende (RX08).

## 2. Alcance

- Kiosco público (dispositivo iPad, `/kiosco`).
- Ingreso por **CI**; si el paciente no existe, se registra en el momento (sin cuenta).
- Selección de especialidad (con restricciones sexo/edad) → médico → horario (cupos kiosco).
- Emisión de **ficha** con código, código de acceso y código QR.
- La emisión confirma la cita (`programada → confirmada`).
- Verificación facial **simulada** (interfaz lista para hardware; identidad por CI).

**Fuera de alcance:** matching biométrico real; impresión física (la ficha es pantalla + QR imprimible).

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
| `verificacion_biometrica` | boolean | default false (simulada) |
| `emitida_at` | timestamptz | default now() |
| `created_at` | timestamptz | |

### Sesión de kiosco
- Token firmado (HMAC) emitido al iniciar flujo (`app_kiosk_token`), expiración corta (10 min).
- No usa sesión de usuario.

## 5. Endpoints (kiosco)

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| POST | `/api/kiosco/sesion` | Inicia sesión de kiosco (token) | pública (device key) |
| POST | `/api/kiosco/identificar` | CI → datos paciente / flag nuevo | token kiosco |
| POST | `/api/kiosco/registrar` | Alta rápida paciente (sin cuenta) | token kiosco |
| GET | `/api/kiosco/especialidades` | Especialidades activas | token kiosco |
| GET | `/api/kiosco/medicos?especialidad=` | Médicos activos por especialidad | token kiosco |
| GET | `/api/kiosco/disponibilidad?medico=&fecha=` | Slots cupo kiosco | token kiosco |
| POST | `/api/kiosco/reservar` | Crear cita (origen=kiosco) | token kiosco |
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
| RFK-H | Verificación biométrica es simulada (`verificacion_biometrica=true` tras "escaneo"). |

## 7. Validaciones

**Servidor:**
- Formato CI; si existe → reconocer; si no → permitir registro rápido (campos mínimos: CI, nombres, apellidos, fecha_nac, sexo, teléfono).
- Elegibilidad sexo/edad.
- Slot válido, no superpuesto, con cupo kiosco.
- Token de kiosco válido y no expirado.

**Cliente (JS):**
- Teclado numérico para CI; flujo guiado; botones grandes.

## 8. Flujo del kiosco (touch-friendly)

```
1. Pantalla de bienvenida "Toque para sacar su ficha"
2. Ingreso CI (teclado numérico en pantalla)
   → POST /api/kiosco/identificar
      → existe: mostrar nombre (confirmación) + "escaneo facial" simulado (getUserMedia)
      → no existe: formulario rápido de registro
3. Elegir especialidad (botones grandes)
4. Elegir médico (cards)
5. Elegir fecha y horario (chips de slots disponibles)
6. Confirmar → POST /api/kiosco/reservar + POST /api/kiosco/ficha
7. Pantalla de FICHA:
   "Paciente: ___ | Especialidad: ___ | Dr(a): ___ | Fecha: ___ | Hora: ___ 
    Código: FIC-0001 | Acceso: 8994 | [QR]  [Imprimir]"
8. Auto-reset a bienvenida (timer 30s)
```

## 9. UI / Pantallas (iPad)

| # | Pantalla | Elemento |
|---|---|---|
| 1 | Bienvenida | Ícono + CTA grande |
| 2 | Identificación | Teclado numérico CI |
| 3 | Escaneo facial (simulado) | Círculo animado + "Verificando..." |
| 4 | Registro rápido | Formulario simplificado |
| 5 | Especialidades | Botones grandes (destacadas primero) |
| 6 | Médicos | Cards |
| 7 | Fecha/hora | Calendario + chips de slots |
| 8 | Confirmación | Resumen + botón confirmar |
| 9 | Ficha | Datos + QR + imprimir |

- Indicador de progreso: `1. Especialidad → 2. Médico → 3. Hora → 4. Ficha`.
- Paleta azul/verde, tipografía grande.

## 10. Criterios de aceptación

- [ ] Paciente con CI existente se reconoce y puede sacar ficha.
- [ ] Paciente nuevo se registra en el kiosco (sin cuenta) y saca ficha.
- [ ] Solo aparecen especialidades compatibles con sexo/edad.
- [ ] Solo aparecen slots con cupo kiosco disponible.
- [ ] La ficha muestra paciente, especialidad, médico, fecha, hora, código y acceso.
- [ ] Emitir ficha deja la cita en `confirmada`.
- [ ] Restricción: ginecología no aparece para sexo masculino.
- [ ] Cupos kiosco agotados → sin slots en kiosco (pero web sí).

## 11. Errores

| Caso | Mensaje (kiosco) |
|---|---|
| CI inválida | "Ingrese una cédula válida." |
| Sin cupos | "No hay horarios disponibles. Intente otra fecha o especialidad." |
| No elegible | "Especialidad no disponible para el paciente." |
| Token expirado | Reinicia el flujo (vuelve a bienvenida). |

## 12. Tareas de implementación

1. Migración `fichas` + secuencia `FIC`.
2. `KioskMiddleware` (token firmado HMAC).
3. `KioscoController` (identificar, registrar, especialidades, médicos, disponibilidad, reservar, ficha).
4. `FichaService` (emitir ficha + confirmar cita + QR).
5. Vistas de kiosco touch-friendly + teclado numérico + jsQR/QR generador.
6. Simulación de escaneo facial (getUserMedia opcional/placeholder).
7. Auto-reset por inactividad.

## 13. Pruebas

| # | Prueba | Resultado |
|---|---|---|
| 1 | CI existente → ficha | Emitida y confirmada |
| 2 | CI nueva → registro + ficha | Emitidos |
| 3 | Especialidad no elegible | No mostrada |
| 4 | Cupos kiosco agotados | Sin slots |
| 5 | Ficha muestra datos correctos | Correcto |
| 6 | Cita queda `confirmada` | Sí |
