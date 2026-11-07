# SPEC 10 — Historia Clínica, Consultas, Recetas, Documentos y Órdenes

> Módulo: Atención médica · Prioridad: **Media**
> Depende de: `07-estados-asistencia.md`, `03-medicos.md`, `02-pacientes.md`, `11-whatsapp-pedidos.md`

---

## 1. Objetivo

Registrar la atención médica derivada de una cita: consulta (motivo, anamnesis, signos vitales, examen físico, diagnóstico, tratamiento, indicaciones), historia clínica cronológica, recetas, documentos médicos y órdenes de estudio con resultados. Las órdenes de laboratorio/imagen toman el **precio del catálogo administrado por recepción** y se cobran en caja (Spec 12).

## 2. Alcance

- **Consultas médicas** al atender una cita.
- **Historia clínica electrónica**: cronología de consultas por paciente.
- **Recetas** (medicamentos con detalle).
- **Documentos** (certificados, informes, constancias, interconsultas).
- **Órdenes** de laboratorio/imágenes con **resultados** y **precio de catálogo**.
- Catálogo `examenes_laboratorio` con precios definidos por recepción.
- Cobro de órdenes en recepción (Spec 12).
- Generación de **código único + código de acceso** por pedido (para Spec 11).
- Vista imprimible HTML de recetas/documentos.

**Fuera de alcance:** PDF con librería (se usa print del navegador); envío real a WhatsApp (Spec 11).

## 3. Actores

- `medico`: crea consultas, recetas, documentos, órdenes, revisa resultados y elige exámenes del catálogo con precio.
- `recepcionista_medico`: administra el catálogo de exámenes y cobra órdenes (Spec 12); puede enviar pedidos por WhatsApp (Spec 11).
- `paciente`: lee su historial y pedidos (según permisos).

## 4. Modelo de datos

### Tabla `consultas`
| Campo | Tipo |
|---|---|
| `id` | identity PK |
| `cita_id` | FK `citas.id` |
| `paciente_id` | FK `pacientes.id` |
| `medico_id` | FK `medicos.id` |
| `motivo` | varchar(200) |
| `anamnesis` | text |
| `signos_vitales` | jsonb ({pa, fc, temp, peso, spo2, talla}) |
| `examen_fisico` | text |
| `diagnostico` | text |
| `tratamiento` | text |
| `indicaciones` | text |
| `fecha` | timestamptz default now() |

### Tabla `recetas`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | identity PK | |
| `codigo` | varchar(20) | único `RSV-XXXXXX` |
| `codigo_acceso` | varchar(4) | |
| `consulta_id` | FK `consultas.id` | |
| `paciente_id` | FK `pacientes.id` | |
| `medico_id` | FK `medicos.id` | |
| `enviado_whatsapp` | boolean | default false |
| `fecha` | timestamptz | |

### Tabla `receta_detalle`
| Campo | Tipo |
|---|---|
| `id` | identity PK |
| `receta_id` | FK `recetas.id` |
| `medicamento` | varchar(150) |
| `dosis` | varchar(80) |
| `via` | varchar(50) |
| `frecuencia` | varchar(80) |
| `duracion` | varchar(50) |
| `instrucciones` | varchar(200) |

### Tabla `documentos`
| Campo | Tipo |
|---|---|
| `id` | identity PK |
| `codigo` | único `DOC-XXXXXX` |
| `codigo_acceso` | varchar(4) |
| `consulta_id` | FK |
| `paciente_id` | FK |
| `medico_id` | FK |
| `tipo` | enum (`certificado`,`informe`,`constancia`,`interconsulta`) |
| `contenido` | text |
| `enviado_whatsapp` | boolean |
| `fecha` | timestamptz |

### Tabla `ordenes`
| Campo | Tipo |
|---|---|
| `id` | identity PK |
| `codigo` | único `ORD-XXXXXX` |
| `codigo_acceso` | varchar(4) |
| `consulta_id` | FK |
| `paciente_id` | FK |
| `medico_id` | FK |
| `tipo` | enum (`laboratorio`,`imagen`,`otro`) |
| `total` | numeric(10,2) | suma del detalle con precios de catálogo |
| `enviado_whatsapp` | boolean |
| `fecha` | timestamptz |

### Tabla `orden_detalle`
| Campo | Tipo |
|---|---|
| `id` | identity PK |
| `orden_id` | FK |
| `examen_id` | FK `examenes_laboratorio.id` (nullable) |
| `estudio` | varchar(150) |
| `precio` | numeric(10,2) | copiado del catálogo al emitir |
| `observaciones` | varchar(200) |

### Tabla `examenes_laboratorio`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | identity PK | |
| `codigo` | varchar(20) | único, opcional |
| `nombre` | varchar(150) | |
| `tipo` | enum `tipo_orden` | `laboratorio`,`imagen`,`otro` |
| `precio` | numeric(10,2) | `>= 0`, definido por recepción |
| `activo` | boolean | default true |

### Tabla `resultados`
| Campo | Tipo |
|---|---|
| `id` | identity PK |
| `orden_id` | FK |
| `archivo` | varchar(255) | nullable |
| `texto` | text | nullable |
| `revisado` | boolean default false |
| `fecha` | timestamptz |

## 5. Endpoints

| Método | Ruta | Descripción | Rol |
|---|---|---|---|
| POST | `/api/citas/{id}/consulta` | Crear consulta (al atender) | medico |
| GET | `/api/consultas/{id}` | Detalle consulta | medico, paciente(propio) |
| GET | `/api/pacientes/{id}/historia` | Historia clínica cronológica | medico, paciente(propio) |
| POST | `/api/consultas/{id}/recetas` | Crear receta + detalle | medico |
| GET | `/api/recetas/{id}` | Detalle receta | medico, paciente |
| POST | `/api/consultas/{id}/documentos` | Crear documento | medico |
| POST | `/api/consultas/{id}/ordenes` | Crear orden + detalle con precio de catálogo | medico |
| GET | `/api/examenes?solo_activos=1` | Catálogo activo con precios | medico, recepción |
| POST/PUT/PATCH | `/api/examenes` | Administrar catálogo y precios | recepción, admin |
| POST | `/api/ordenes/{id}/pago` | Cobrar la orden (Spec 12) | recepción |
| POST | `/api/ordenes/{id}/resultados` | Subir/registrar resultado | medico |
| GET | `/api/recetas/{id}/imprimir` | Vista imprimible | medico, paciente |
| GET | `/api/documentos/{id}/imprimir` | Vista imprimible | medico, paciente |

## 6. Reglas de negocio

| Código | Regla |
|---|---|
| RFHC-A | La consulta se crea asociada a una cita `confirmada`/`atendida`. |
| RFHC-B | Al crear la consulta, la cita pasa a `atendida` (o lo hace el botón atender, Spec 07). |
| RFHC-C | Cada receta/documento/orden obtiene `codigo` único + `codigo_acceso` de 4 dígitos. |
| RFHC-D | El médico solo edita sus propias consultas/pedidos. |
| RFHC-E | El paciente lee solo lo suyo (RLS). |
| RFHC-F | Los pedidos pueden enviarse por WhatsApp si el paciente tiene teléfono (Spec 11). |
| RFHC-G | Los resultados se asocian a una orden y pueden marcarse `revisado`. |
| RFHC-H | El médico elige exámenes del catálogo activo; el detalle copia `examen_id` y `precio` al emitir la orden. |
| RFHC-I | Un cambio posterior de precio no altera órdenes ya emitidas; `ordenes.total` suma el detalle. |
| RFHC-J | Recepción cobra la orden total o parcialmente y la orden sale de la cola al cubrir el total. |

## 7. Validaciones

**Servidor:**
- La cita pertenece al médico y existe.
- Campos de texto con longitud máxima.
- Signos vitales: numéricos y en rangos plausibles (validación blanda).
- Una receta debe tener al menos un detalle; una orden al menos un estudio.

## 8. Flujos

### 8.1 Atender consulta
```
[POST /api/citas/{id}/consulta]
  → validar cita del médico, estado confirmada
  → INSERT consultas (motivo, anamnesis, signos, examen, dx, tx, indicaciones)
  → UPDATE cita estado='atendida' (o dejar que Spec 07 lo haga)
  → abrir opciones: receta / documento / orden / reconsulta
```

### 8.2 Generar receta
```
[POST /api/consultas/{id}/recetas] {detalle:[...]}
  → INSERT recetas (codigo RSV-XXXXXX, codigo_acceso)
  → INSERT receta_detalle
  → ofrecer imprimir / enviar WhatsApp
```

### 8.3 Subir resultado
```
[POST /api/ordenes/{id}/resultados] (multipart)
  → guardar archivo en /uploads/resultados/
  → INSERT resultados
```

### 8.4 Orden con precios de catálogo
```
[GET /api/examenes?solo_activos=1] → catálogo con precio
[POST /api/consultas/{id}/ordenes] {detalle:[{examen_id, observaciones}]}
  → copia examen_id + precio a orden_detalle
  → ordenes.total = suma de precios
[POST /api/ordenes/{id}/pago] (recepción)
  → registra pago tipo='laboratorio' ligado a la orden
```

## 9. UI / Pantallas

| Vista | Rol | Descripción |
|---|---|---|
| Consulta (formulario) | medico | Motivo, signos, examen, dx, tx, indicaciones |
| Historia clínica | medico/paciente | Timeline cronológico de consultas |
| Receta | medico | Constructor de medicamentos + imprimir/enviar |
| Documento | medico | Editor + imprimir/enviar |
| Orden de estudios | medico | Catálogo con precio + total estimado + imprimir/enviar |
| Resultados | medico | Subida y revisión |
| Receta imprimible | medico/paciente | HTML print (logo, datos, medicamentos, QR) |

## 10. Criterios de aceptación

- [ ] Registrar consulta persiste todos los campos.
- [ ] Historia clínica muestra consultas en orden cronológico.
- [ ] Receta genera código único + código de acceso.
- [ ] Receta/documento imprimible en HTML.
- [ ] Orden admite múltiples estudios y resultados.
- [ ] La orden toma el precio del catálogo y conserva el precio emitido.
- [ ] Recepción cobra la orden y esta sale de la cola de cobro.
- [ ] RLS: médico solo ve sus consultas; paciente solo las suyas.

## 11. Errores

| Caso | Mensaje |
|---|---|
| Cita no del médico | 403 |
| Receta vacía | "Agregue al menos un medicamento." |
| Orden vacía | "Agregue al menos un estudio." |
| Examen inexistente/inactivo | "El examen seleccionado no está disponible." |
| Monto mayor al saldo de la orden | "El saldo es de X Bs." |
| Archivo inválido | "Formato de archivo no permitido." |

## 12. Tareas de implementación

1. Migraciones (consultas, recetas, receta_detalle, documentos, ordenes, orden_detalle, resultados, examenes_laboratorio).
2. `ConsultaModel`, `RecetaModel`, `DocumentoModel`, `OrdenModel`, `ResultadoModel`, `ExamenModel`.
3. `ConsultaController` + `PedidoService` (códigos únicos/acceso).
4. `OrdenModel` con precios de catálogo y totales; `CobroService::pagarOrden`.
5. Vistas Gentelella + imprimibles HTML + selector de catálogo con precio.
6. Upload seguro de resultados.
7. Políticas RLS.

## 13. Pruebas

| # | Prueba | Resultado |
|---|---|---|
| 1 | Crear consulta | Guardada |
| 2 | Historia clínica | Cronológica |
| 3 | Receta con código | Único |
| 4 | Imprimir receta | Vista correcta |
| 5 | Orden + resultado | Asociados |
| 6 | Orden con precio de catálogo | Total correcto |
| 7 | Cambio de precio posterior | Orden conserva su precio |
| 8 | Paciente ve su historia | Solo la suya |
