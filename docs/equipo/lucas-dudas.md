# L-01 — Lectura y dudas de Lucas

> Fecha: 2026-10-23
> Fuentes leídas: `docs/specs/00`, `01`, secciones de UI de `02`–`15`,
> `docs/api/contract.md`, `docs/api/view-contract.md`, `docs/api/mocks/*` y
> `docs/equipo/PLAN-TRABAJO.md`.

## 1. Resumen de lo entendido

- Los paneles se renderizan con PHP y consumen la API JSON con `fetch`; no
  llevan SQL ni reglas de negocio en las vistas.
- El layout de panel es mío (`layouts/gentelella.php`), al igual que el menú,
  las vistas de rol y los assets de `public/assets/panel/**`.
- El contrato de sesión, helpers, CSRF, variables de layout y rutas está
  congelado en `docs/api/view-contract.md`.
- Las vistas deben funcionar primero con mocks y luego cambiar al endpoint real.
- Los roles y destinos son: admin → `/admin`, recepción → `/recepcion`,
  médico → `/medico`, paciente → `/portal`.

## 2. Dudas / bloqueos abiertos

| # | Tema | Detalle | Dueño | Impacto |
|---|---|---|---|---|
| 1 | Layout de auth | `AuthController::authView()` renderiza `auth/*` sin layout. El contrato asigna `layouts/auth.php` a Lucas, pero el controlador no lo usa. ¿Matias agrega el layout al render o autoriza que las vistas de auth sean autónomas? | Matias | L-08 / L-09 |
| 2 | Menú de `recepcionista_medico` | Spec 01 pide Agenda, Pacientes atendidos, Asistencia, Pagos y WhatsApp; `view-contract.md` le da el mismo menú que recepción. El plan dice que las specs mandan. ¿Actualizamos el contrato con rutas nuevas? | Matias | L-04 / L-20–L-25 |
| 3 | Gentelella | El directorio `gentelella/` está vacío y es un gitlink sin `.gitmodules`. Usé el artefacto publicado de Gentelella 4.2.0 para extraer CSS e imágenes. ¿Se versiona la fuente completa o solo los assets derivados? | Ambos | L-02 / L-03 |
| 4 | Gráficos | Spec 13 menciona Chart.js “de Gentelella”, pero Gentelella v4 usa ECharts. ¿Usamos ECharts, Chart.js o SVG/CSS propio? | Ambos | L-13 / L-19 |
| 5 | Iconografía | Spec 00 menciona Font Awesome; Gentelella v4 no lo incluye y usa SVG inline. Propongo SVG inline para no agregar dependencias. | Ambos | L-04 / L-05 |
| 6 | Página 403 | Spec 01 pide página 403; `RoleMiddleware` redirige al home del rol. ¿Se cambia el middleware o se resuelve en L-11? | Matias | L-11 |
| 7 | Endpoints de recepción | Las APIs de recepción (M-27/M-28) aún no existen. Las vistas L-20–L-25 se harán con mocks hasta que estén. | Matias | L-20–L-25 |
| 8 | Campo de login | Confirmo que el login usa `identificador` y `password`, y que `wantsJson()` decide entre JSON y redirect. | Matias | L-09 |
| 9 | `APP_URL` | Si el `.env` no define `APP_URL`, `asset()` apunta a `http://localhost:8080`. Hay que confirmar el puerto usado por el equipo para no romper CSS/JS. | Matias | L-04 |
| 10 | Datos iniciales del dashboard | Para L-13 usaré `/api/dashboard/admin`; para recepción/médico/portal no hay dashboard API todavía. Usaré mocks/estado vacío. | Matias | L-13 / L-20 / L-26 / L-32 |

## 3. Decisiones que puedo tomar sin bloquear

- Usar Gentelella 4.2.0 como base visual, con CSS compilado y shell PHP.
- No cargar el JS de demo de Gentelella: el shell se renderiza en PHP y la
  interacción propia vive en `public/assets/panel/js/panel.js`.
- Iconos SVG inline, sin Font Awesome.
- Menú según `view-contract.md` hasta que se resuelva la duda 2.
- Vistas de auth autónomas si Matias no puede cambiar `AuthController` (duda 1).

## 4. Siguiente paso

L-02 y L-03 (ver `gentelella-integracion.md`), luego L-04/L-06/L-07. Las dudas 1,
2, 3 y 6 se reportan en el daily para que Matias responda antes de L-08/L-09.
