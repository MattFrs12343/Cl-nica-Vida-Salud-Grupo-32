# SPEC 13 — Reportes y Dashboards (RF13)

> Módulo: Reportes · Prioridad: **Media**
> Depende de: `06-citas.md`, `07-estados-asistencia.md`, `02-pacientes.md`

---

## 1. Objetivo

Generar reportes administrativos y dashboards de métricas filtrando por fecha/rango, mostrando totales, para apoyar la gestión de la clínica.

## 2. Alcance

**Reportes (RF13):**
- Citas programadas para una fecha.
- Citas por médico.
- Citas por especialidad.
- Citas por estado.
- Pacientes registrados.
- Atendidas / canceladas / inasistencias en un período.
- Agenda diaria por médico.

**Dashboards:**
- **Admin/super_admin:** citas del día, atendidas, canceladas, inasistencias, origen web/kiosco/recepción.
- **Médico:** resumen diario (citas, en espera, próximas, pendientes).

**Opcional:** exportar a CSV.

## 3. Actores

- `admin` / `super_admin`: todos los reportes.
- `medico`: dashboard propio y agenda diaria propia.
- `recepcionista`: reportes operativos (opcional).

## 4. Modelo de datos

Consultas agregadas sobre `citas`, `pacientes`, `medicos`, `especialidades`. Sin tablas nuevas (opcional: vistas materializadas para performance).

## 5. Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/reportes/citas?fecha=&medico=&especialidad=&estado=` | Citas filtradas |
| GET | `/api/reportes/citas-por-medico?desde=&hasta=` | Conteo por médico |
| GET | `/api/reportes/citas-por-especialidad?desde=&hasta=` | Conteo por especialidad |
| GET | `/api/reportes/citas-por-estado?desde=&hasta=` | Conteo por estado |
| GET | `/api/reportes/pacientes?desde=&hasta=` | Pacientes registrados |
| GET | `/api/reportes/asistencia?desde=&hasta=` | Atendidas/canceladas/inasistencias |
| GET | `/api/reportes/agenda-diaria?medico=&fecha=` | Agenda diaria |
| GET | `/api/dashboard/admin` | Métricas admin |
| GET | `/api/dashboard/medico` | Métricas médico |
| GET | `/api/reportes/{tipo}/csv` | Exportar CSV (opcional) |

## 6. Reglas de negocio

| Código | Regla |
|---|---|
| RFR-A | Los reportes permiten filtrar por fecha o rango. |
| RFR-B | Muestran totales cuando corresponde. |
| RFR-C | El médico solo ve reportes/dashboard propres. |
| RFR-D | El admin ve todos. |
| RFR-E | Rango por defecto: mes actual si no se especifica. |
| RFR-F | Export CSV opcional (mejora). |

## 7. Validaciones

**Servidor:**
- `desde <= hasta`; fechas válidas.
- `medico`/`especialidad`/`estado` existentes si se filtran.
- Permisos por rol.

## 8. Flujos

### 8.1 Citas por estado (período)
```
[GET /api/reportes/citas-por-estado?desde=2026-11-01&hasta=2026-11-30]
  → SELECT estado, COUNT(*) FROM citas
     WHERE fecha BETWEEN ? AND ? GROUP BY estado
  → {programada:n, confirmada:n, atendida:n, cancelada:n, inasistencia:n}
```

### 8.2 Dashboard admin
```
[GET /api/dashboard/admin]
  → citas_hoy, atendidas, canceladas, inasistencias
  → origen: {web, kiosco, recepcion}
```

### 8.3 Dashboard médico
```
[GET /api/dashboard/medico]
  → citas_hoy, en_espera(confirmadas), proximas, pendientes(programadas)
  → notificaciones (reconsultas, ausencias)
```

## 9. UI / Pantallas

| Vista | Rol | Descripción |
|---|---|---|
| Dashboard admin | admin | Tarjetas de métricas + gráficos (Chart.js de Gentelella) |
| Reportes | admin | Filtros + tabla + totals |
| Dashboard médico | medico | Resumen diario + lista |
| Agenda diaria | admin/medico | Tabla por médico |
| Exportar CSV | admin | Botón (opcional) |

## 10. Criterios de aceptación

- [ ] Reporte de citas por fecha muestra resultado correcto.
- [ ] Reportes por médico/especialidad/estado con totales.
- [ ] Rango de fechas filtra correctamente.
- [ ] Dashboard admin muestra métricas del día y origen.
- [ ] Dashboard médico muestra su resumen.
- [ ] Médico no accede a reportes globales.

## 11. Errores

| Caso | Mensaje |
|---|---|
| Rango inválido | "El rango de fechas no es válido." |
| Sin permisos | 403 |

## 12. Tareas de implementación

1. `ReporteController` + consultas agregadas.
2. `DashboardController`.
3. Vistas Gentelella + Chart.js.
4. Filtros y export CSV opcional.
5. RLS por rol.

## 13. Pruebas

| # | Prueba | Resultado |
|---|---|---|
| 1 | Citas por fecha | Correcto |
| 2 | Citas por médico | Conteo correcto |
| 3 | Citas por especialidad | Conteo correcto |
| 4 | Citas por estado | Totales |
| 5 | Pacientes registrados | Correcto |
| 6 | Printería por período | Totales |
| 7 | Dashboard admin | Métricas |
| 8 | Médico sin acceso global | 403 |
