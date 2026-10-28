-- M-38: índices para los listados, reportes y búsquedas más frecuentes.
-- Se crean con IF NOT EXISTS para que la migración sea idempotente.

-- Login por usuario/email sin distinguir mayúsculas.
CREATE INDEX IF NOT EXISTS ix_usuarios_lower_username
    ON usuarios (lower(username));

CREATE INDEX IF NOT EXISTS ix_usuarios_lower_email
    ON usuarios (lower(email));

-- Agenda, listados administrativos y reportes por médico/fecha/estado.
CREATE INDEX IF NOT EXISTS ix_citas_medico_fecha_estado
    ON citas (medico_id, fecha, estado);

CREATE INDEX IF NOT EXISTS ix_citas_especialidad_fecha
    ON citas (especialidad_id, fecha);

CREATE INDEX IF NOT EXISTS ix_citas_fecha_estado
    ON citas (fecha, estado);

-- Portal del paciente, última cita/atención y agenda diaria.
CREATE INDEX IF NOT EXISTS ix_citas_paciente_fecha
    ON citas (paciente_id, fecha DESC, hora_inicio DESC);

CREATE INDEX IF NOT EXISTS ix_citas_paciente_estado_fecha
    ON citas (paciente_id, estado, fecha DESC);

-- Reporte de pacientes registrados por rango de alta.
CREATE INDEX IF NOT EXISTS ix_pacientes_created_at
    ON pacientes (created_at DESC);

-- Reportes clínicos y de pagos por fecha.
CREATE INDEX IF NOT EXISTS ix_consultas_fecha
    ON consultas (fecha DESC);

CREATE INDEX IF NOT EXISTS ix_recetas_fecha
    ON recetas (fecha DESC);

CREATE INDEX IF NOT EXISTS ix_documentos_fecha
    ON documentos (fecha DESC);

CREATE INDEX IF NOT EXISTS ix_ordenes_fecha
    ON ordenes (fecha DESC);

CREATE INDEX IF NOT EXISTS ix_resultados_fecha
    ON resultados (fecha DESC);

CREATE INDEX IF NOT EXISTS ix_pagos_fecha
    ON pagos (fecha DESC);

-- Auditoría filtrada por usuario o tabla dentro de un rango de fechas.
CREATE INDEX IF NOT EXISTS ix_auditoria_usuario_fecha
    ON auditoria (usuario_id, fecha DESC);

CREATE INDEX IF NOT EXISTS ix_auditoria_tabla_fecha
    ON auditoria (tabla_afectada, fecha DESC);
