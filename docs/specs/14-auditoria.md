# SPEC 14 — Auditoría y Bitácora

> Módulo: Auditoría · Prioridad: **Media**
> Depende de: `01-autenticacion.md`, `99-modelo-datos-rls.md`

---

## 1. Objetivo

Registrar y visualizar (solo admin/super_admin) los cambios realizados sobre los catálogos del sistema: IP, usuario, acción, tabla afectada y valores antes/después. Es una bitácora de **monitoreo**; no registra accesos/login (decisión del equipo, RX13).

## 2. Alcance

- Registro automático de cambios (INSERT/UPDATE/DELETE) en tablas de catálogo y entidades principales.
- Almacena `ip`, `usuario_id`, `tabla_afectada`, `accion`, `registro_id`, `datos_antes` (JSONB), `datos_despues` (JSONB), `fecha`.
- Vista de auditoría para admin/super_admin con filtros.
- Escritura vía trigger `SECURITY DEFINER` (el usuario no puede alterar la auditoría).

**Fuera de alcance:** login/logout, intentos fallidos, eventos de seguridad.

## 3. Actores

- `admin` / `super_admin`: ver la bitácora.
- Sistema (trigger): escribir la bitácora.

## 4. Modelo de datos

### Tabla `auditoria`
| Campo | Tipo | Notas |
|---|---|---|
| `id` | identity PK | |
| `usuario_id` | FK `usuarios.id` | quién ejecutó |
| `ip` | inet | `current_setting('app.ip')` o `inet_client_addr()` |
| `tabla_afectada` | varchar(60) | |
| `accion` | enum | `INSERT`,`UPDATE`,`DELETE` |
| `registro_id` | bigint | PK del registro afectado |
| `datos_antes` | jsonb | null en INSERT |
| `datos_despues` | jsonb | null en DELETE |
| `fecha` | timestamptz | default now() |

### Función trigger (resumen)
```sql
CREATE OR REPLACE FUNCTION fn_auditar() RETURNS trigger
LANGUAGE plpgsql SECURITY DEFINER AS $$
BEGIN
  INSERT INTO auditoria(usuario_id, ip, tabla_afectada, accion, registro_id, datos_antes, datos_despues)
  VALUES (
    nullif(current_setting('app.usuario_id', true),'')::int,
    nullif(current_setting('app.ip', true),'')::inet,
    TG_TABLE_NAME,
    TG_OP,
    COALESCE(NEW.id, OLD.id),
    CASE WHEN TG_OP='INSERT' THEN NULL ELSE to_jsonb(OLD) END,
    CASE WHEN TG_OP='DELETE' THEN NULL ELSE to_jsonb(NEW) END
  );
  RETURN COALESCE(NEW, OLD);
END $$;
```

### Tablas auditadas (catálogos/entidades)
`pacientes`, `medicos`, `especialidades`, `horarios`, `bloqueos`, `usuarios`, `medico_especialidad`, `recepcionista_medico`.

## 5. Endpoints

| Método | Ruta | Descripción | Rol |
|---|---|---|---|
| GET | `/api/auditoria?tabla=&usuario=&accion=&desde=&hasta=` | Listar bitácora | admin, super_admin |
| GET | `/api/auditoria/{id}` | Detalle con diff | admin, super_admin |

## 6. Reglas de negocio

| Código | Regla |
|---|---|
| RFAUD-A | Solo admin/super_admin ven la bitácora. |
| RFAUD-B | La bitácora registra IP + datos antes/después (RX13). |
| RFAUD-C | No se registran accesos/login. |
| RFAUD-D | El usuario no puede modificar/borrar la auditoría (trigger SECURITY DEFINER, RLS de solo lectura). |
| RFAUD-E | Se auditan los catálogos/entidades listados. |

## 7. Validaciones

**Servidor:**
- Filtros válidos; permisos admin.
- Solo lectura (ningún endpoint de escritura expuesto).

## 8. Flujos

### 8.1 Registro automático
```
UPDATE pacientes SET telefono='...' WHERE id=10
  → trigger fn_auditar() inserta fila con datos_antes/datos_despues
  → usuario_id e ip desde la sesión (SET LOCAL app.usuario_id/app.ip)
```

### 8.2 Visualización (admin)
```
[GET /api/auditoria?tabla=pacientes&accion=UPDATE]
  → lista con diff resaltado (antes vs después)
```

## 9. UI / Pantallas

| Vista | Rol | Descripción |
|---|---|---|
| Bitácora | admin | Tabla filtrable (tabla, usuario, acción, fecha) |
| Detalle de cambio | admin | Comparación antes/después (JSON diff) |

> Nota del equipo: la pregunta "¿Quién va a trabajar, Matias o Lucas?" es un **flujo de trabajo externo** (seguimiento del chat con la IA), no una funcionalidad del sistema.

## 10. Criterios de aceptación

- [ ] Un cambio en un catálogo genera fila de auditoría con IP y diff.
- [ ] Solo admin/super_admin ven la bitácora.
- [ ] No aparecen eventos de login en la bitácora.
- [ ] La auditoría no es editable/borrable por usuarios normales.
- [ ] Filtros por tabla/usuario/acción/fecha funcionan.

## 11. Errores

| Caso | Mensaje |
|---|---|
| Sin permisos | 403 |
| Filtro inválido | "Filtro no válido." |

## 12. Tareas de implementación

1. Migración `auditoria` + enum + índices (tabla, fecha, usuario).
2. Función `fn_auditar()` + triggers en tablas de catálogo.
3. `SET LOCAL app.usuario_id/app.ip` en el bootstrap de conexión.
4. `AuditoriaController` (solo lectura) + vista Gentelella.
5. RLS: select solo admin; sin update/delete.

## 13. Pruebas

| # | Prueba | Resultado |
|---|---|---|
| 1 | Editar paciente | Fila auditoría con antes/después |
| 2 | Crear especialidad | Fila INSERT |
| 3 | Ver bitácora como admin | Visible |
| 4 | Ver bitácora como recepción | 403 |
| 5 | Intentar borrar auditoría | Bloqueado |
