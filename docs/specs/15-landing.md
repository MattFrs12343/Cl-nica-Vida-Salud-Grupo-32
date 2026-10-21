# SPEC 15 — Landing Pública

> Módulo: Sitio público · Prioridad: **Media (Presentación 1 base)**
> Depende de: `04-especialidades.md`, `03-medicos.md`, `09-kiosco-fichas.md`

---

## 1. Objetivo

Ofrecer la página pública de la Clínica Vida Salud con información institucional, buscador de especialidades y llamados a la acción para **agendar cita** (reserva online) y **sacar ficha** (kiosco/presencial).

## 2. Alcance

- Hero con slider + CTA.
- Menú: Inicio, Nosotros, Especialidades, Médicos, Servicios, Noticias, Contacto.
- Buscador de especialidades (con disponibilidad de médicos).
- CTA "Agendar cita" (registro/login paciente → reserva web) y CTA "Sacar ficha" (explica kiosco).
- Sección de especialidades (destacadas: pediatría, medicina general, geriatría).
- Directorio de médicos activos por especialidad.
- Sección de noticias/campañas.
- Contacto (teléfono, WhatsApp, mapa), footer.
- Registro y login de paciente; activación de cuenta (Spec 01).

## 3. Actores

- Público (sin sesión): informarse, buscar, registrarse, reservar.
- Paciente (con sesión): reservar y gestionar sus citas.

## 4. Modelo de datos

Consume endpoints públicos: `especialidades` (activas), `medicos` (activos), `disponibilidad`. No crea tablas nuevas.

## 5. Endpoints (públicos)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/public/especialidades` | Especialidades activas (destacadas primero) |
| GET | `/api/public/medicos?especialidad=` | Médicos activos por especialidad |
| GET | `/api/public/disponibilidad?medico=&fecha=` | Slots web disponibles |
| POST | `/api/public/reservar` | Reserva (requiere sesión paciente) |
| GET | `/` | Landing HTML |
| GET | `/kiosco` | Redirección/instrucción del kiosco |

## 6. Reglas de negocio

| Código | Regla |
|---|---|
| RFL-A | Solo se muestran especialidades/médicos activos. |
| RFL-B | El buscador filtra por especialidad y muestra médicos disponibles. |
| RFL-C | "Agendar cita" requiere registro/login (o activar cuenta). |
| RFL-D | La reserva web consume cupos web (RX01). |
| RFL-E | "Sacar ficha" informa sobre el kiosco presencial. |
| RFL-F | Las 3 especialidades destacadas van primero. |

## 7. Validaciones

**Servidor:** parámetros de búsqueda válidos; sesión para reservar.
**Cliente (JS):** filtros del buscador, formularios de registro/login.

## 8. Flujos

### 8.1 Buscar especialidad
```
[landing] buscar "pediatría"
  → GET /api/public/medicos?especialidad=1
  → cards de médicos con "Agendar" (→ login/registro)
```

### 8.2 Registrarse y reservar
```
[Registrarse] → Spec 01 → sesión paciente
[Agendar] → seleccionar especialidad/médico/fecha/slot (cupo web) → POST /api/public/reservar
  → cita + ficha (web) + correo
```

## 9. UI / Secciones

| Sección | Contenido |
|---|---|
| Header | Logo (aportado), menú, botones Login/Registrarse |
| Hero | Slider + CTA "Agendar cita" / "Sacar ficha" |
| Accesos rápidos | Tarjetas: Horarios, Especialidades, Pedidos, Contacto |
| Especialidades | Grid con destacadas + buscador |
| Médicos | Directorio por especialidad |
| Nosotros | Historia, misión/visión (texto) |
| Noticias | Cards de campañas |
| Contacto | Teléfono, WhatsApp, mapa, footer |
| Footer | Enlaces, redes, copyright |

- Paleta azul médico + verde/teal; estilo moderno y limpio.
- Imágenes y logo aportados por el equipo (placeholders hasta integrarlos).

## 10. Criterios de aceptación

- [ ] Landing carga con hero, menú y secciones.
- [ ] Buscador de especialidades muestra médicos activos.
- [ ] CTA "Agendar cita" lleva a registro/login.
- [ ] Paciente registrado reserva online (cupo web).
- [ ] CTA "Sacar ficha" informa del kiosco.
- [ ] Responsive (móvil/tablet).

## 11. Errores

| Caso | Mensaje |
|---|---|
| Sin resultados | "No hay médicos disponibles para esa especialidad." |
| Reserva sin sesión | Redirige a login/registro. |

## 12. Tareas de implementación

1. Layout público (header, footer, paleta).
2. Secciones HTML/CSS + assets.
3. Buscador (fetch a endpoints públicos).
4. Integración con registro/login y reserva web.
5. Responsive + accesibilidad básica.
6. Integración de logo/imágenes del equipo.

## 13. Pruebas

| # | Prueba | Resultado |
|---|---|---|
| 1 | Cargar landing | OK |
| 2 | Buscar "geriatría" | Médicos listados |
| 3 | Agendar cita | Reserva web |
| 4 | Responsive móvil | Correcto |
| 5 | Especialidad inactiva | No aparece |
