# SPEC 11 — Envío por WhatsApp y Página Pública de Pedidos

> Módulo: Pedidos / Notificaciones · Prioridad: **Media**
> Depende de: `10-historia-clinica.md`, `02-pacientes.md`

---

## 1. Objetivo

Permitir al médico (y a su recepcionista) enviar recetas, documentos y órdenes de estudio por **WhatsApp** mediante deep link con mensaje precargado, y ofrecer al paciente una **página pública** (con código de acceso) para ver y descargar/imprimir sus pedidos.

## 2. Alcance

- Generación de `codigo` único + `codigo_acceso` (4 dígitos) por pedido (Spec 10).
- Botón "Enviar por WhatsApp" que abre `wa.me/<telefono>?text=<mensaje>` con mensaje pegado.
- Validación de **teléfono registrado** antes de enviar.
- Página pública sin login: enlace con código de prescripción + código de acceso.
- Vista imprimible / descarga (HTML print).
- Marca de `enviado_whatsapp=true`.

**Fuera de alcance:** WhatsApp Business API real; PDF con librería.

## 3. Actores

- `medico`: envía pedidos.
- `recepcionista_medico`: envía pedidos de su médico.
- `paciente`: accede a la página pública con su código.

## 4. Modelo de datos

Reutiliza `recetas`, `documentos`, `ordenes` (`codigo`, `codigo_acceso`, `enviado_whatsapp`).
Opcional: tabla `envios_pedidos` (log).

### Tabla `envios_pedidos` (opcional)
| Campo | Tipo |
|---|---|
| `id` | identity PK |
| `tipo` | enum (`receta`,`documento`,`orden`) |
| `pedido_id` | int |
| `telefono` | varchar(20) |
| `usuario_id` | FK `usuarios.id` |
| `fecha` | timestamptz |

## 5. Endpoints

| Método | Ruta | Descripción | Rol |
|---|---|---|---|
| POST | `/api/pedidos/{tipo}/{id}/enviar` | Marca envío (y devuelve URL wa.me) | medico, recep_medico |
| GET | `/api/public/pedido?codigo=&acceso=` | Consulta pública del pedido | pública |
| GET | `/api/pedidos/{tipo}/{id}/imprimir` | Vista imprimible (Spec 10) | medico, paciente |

### Respuesta de `enviar`
```json
{
  "ok": true,
  "whatsapp_url": "https://wa.me/59171234567?text=...",
  "mensaje": "Olá Lucas Cortez, seu pedido de exames de Dr(a) ..."
}
```
El front hace `window.open(whatsapp_url)`.

## 6. Reglas de negocio

| Código | Regla |
|---|---|
| RXW-A | Un enlace y código de acceso **por pedido** (receta/examen/lab separados). |
| RXW-B | Validar teléfono del paciente antes de enviar; si no, aviso y el pedido queda en historial. |
| RXW-C | El mensaje incluye: nombre paciente, tipo de pedido, nombre del médico, enlace y códigos. |
| RXW-D | La página pública valida `codigo` + `codigo_acceso`; sin código correcto no muestra datos. |
| RXW-E | La página descarga/imprime en HTML (print del navegador). |
| RXW-F | Marcar `enviado_whatsapp=true` al confirmar el envío. |

## 7. Mensaje (plantilla)

```
Olá {{paciente}}, seu pedido de {{tipo}} de {{medico}} está disponível.

Acesse pelo link:
{{url_publica}}

Código da prescrição: {{codigo}}
Código de acesso: {{codigo_acceso}}
```
- `url_publica = https://<dominio>/pedido/{{codigo}}`
- Placeholders resueltos en el servidor.

## 8. Página pública

```
[GET /pedido/{codigo}] → formulario código de acceso
[GET /api/public/pedido?codigo=&acceso=]
  → validar
  → mostrar: datos generales de la atención + receta/exámenes + botón imprimir
```

## 9. Validaciones

**Servidor:**
- `codigo` y `codigo_acceso` obligatorios y correctos.
- Teléfono del paciente presente para el envío.
- Tipo de pedido válido.

**Cliente (JS):**
- Formulario de acceso (4 dígitos), impresión.

## 10. UI / Pantallas

| Vista | Rol | Descripción |
|---|---|---|
| Botón "Enviar por WhatsApp" | medico/recep | Copia/abre wa.me con mensaje |
| Modal "sin teléfono" | recep | Aviso y opción de registrar teléfono |
| Página pública pedido | paciente | Código de acceso → datos + imprimir |
| Vista imprimible | varios | Receta/examen estilo documento (logo, QR) |

## 11. Criterios de aceptación

- [ ] Botón abre WhatsApp con mensaje precargado correcto.
- [ ] Sin teléfono: aviso y no se envía.
- [ ] Página pública exige código de acceso correcto.
- [ ] Página permite imprimir/descargar.
- [ ] `enviado_whatsapp` se marca tras el envío.
- [ ] Un enlace/código por pedido (no compartir múltiples pedidos).

## 12. Errores

| Caso | Mensaje |
|---|---|
| Sin teléfono | "El paciente no tiene teléfono registrado. Regístrelo para enviar." |
| Código incorrecto | "Código de acceso inválido." |
| Pedido inexistente | "Pedido no encontrado." |

## 13. Tareas de implementación

1. `PedidoService` (construir mensaje + URL wa.me).
2. `PublicPedidoController` (código + acceso).
3. Endpoint `enviar` (validar teléfono, marcar flag).
4. Vista pública + imprimible.
5. (Opcional) tabla `envios_pedidos`.

## 14. Pruebas

| # | Prueba | Resultado |
|---|---|---|
| 1 | Enviar receta con teléfono | Abre wa.me correcto |
| 2 | Enviar sin teléfono | Aviso |
| 3 | Página pública con acceso correcto | Muestra pedido |
| 4 | Acceso incorrecto | Rechazado |
| 5 | Imprimir pedido | Vista correcta |
