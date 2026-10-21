# Specs SSD — Clínica Vida Salud

Documentación de especificación por módulo (estilo SSD: Software/System Design Description).
Cada spec es el **contrato de implementación**: se implementa el módulo siguiendo su spec y se marcan sus criterios de aceptación.

> Convención de nombres: `NN-modulo.md`. Las specs `00` y `99` son transversales.

---

## Índice

| Spec | Módulo | RF | Prioridad |
|---|---|---|---|
| [`00-vision-general.md`](00-vision-general.md) | Visión, convenciones y arquitectura | — | Base |
| [`01-autenticacion.md`](01-autenticacion.md) | Autenticación y control de acceso | RF01 | Alta |
| [`02-pacientes.md`](02-pacientes.md) | Gestión de pacientes | RF02 | Alta |
| [`03-medicos.md`](03-medicos.md) | Gestión de médicos | RF03 | Alta |
| [`04-especialidades.md`](04-especialidades.md) | Gestión de especialidades | RF04 | Alta |
| [`05-horarios-cupos.md`](05-horarios-cupos.md) | Horarios y cupos (50/50) | RF05 | Alta |
| [`06-citas.md`](06-citas.md) | Citas: registro, duplicados, reprogramación, cancelación | RF06–RF09 | Alta |
| [`07-estados-asistencia.md`](07-estados-asistencia.md) | Estados, asistencia y reconsultas | RF10 | Alta |
| [`08-agenda-historial.md`](08-agenda-historial.md) | Agenda médica e historial de paciente | RF11–RF12 | Media |
| [`09-kiosco-fichas.md`](09-kiosco-fichas.md) | Kiosco y emisión de fichas | — | Alta |
| [`10-historia-clinica.md`](10-historia-clinica.md) | Consultas, recetas, documentos y órdenes | — | Media |
| [`11-whatsapp-pedidos.md`](11-whatsapp-pedidos.md) | Envío por WhatsApp y página pública | — | Media |
| [`12-pagos.md`](12-pagos.md) | Registro de pagos | — | Media |
| [`13-reportes.md`](13-reportes.md) | Reportes y dashboards | RF13 | Media |
| [`14-auditoria.md`](14-auditoria.md) | Auditoría y bitácora | — | Media |
| [`15-landing.md`](15-landing.md) | Landing pública | — | Media |
| [`99-modelo-datos-rls.md`](99-modelo-datos-rls.md) | Modelo de datos consolidado + RLS | — | Base |

## Roles

`super_admin` · `admin` · `recepcionista` · `recepcionista_medico` · `medico` · `paciente`

## Orden de implementación (Ciclo 1 → 24/10)

1. `99` modelo de datos + RLS
2. `01` autenticación
3. `02` pacientes · `03` médicos · `04` especialidades
4. `05` horarios y cupos
5. `15` landing (base)

## Orden de implementación (Ciclo 2 → 07/11)

6. `06` citas · `07` estados y reconsultas
7. `09` kiosco y fichas
8. `08` agenda e historial
9. `10` historia clínica y pedidos
10. `11` WhatsApp y página pública
11. `12` pagos
12. `13` reportes
13. `14` auditoría

## Reglas transversales (ver `00`)

- RN01–RN14 (enunciado) y RX01–RX13 (ampliaciones acordadas).
- Seguridad: RLS, bloqueo de login (3 intentos), token de recuperación, CSRF, token firmado de kiosco.
- Deploy: hosting cPanel (web) + PostgreSQL nube (Neon/Railway), HTTPS obligatorio.
- SMTP: Brevo free tier + PHPMailer + fallback a log.
