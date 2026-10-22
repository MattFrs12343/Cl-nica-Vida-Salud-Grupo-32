-- 005 — Citas, historial de estados y fichas
-- Depende de 002_pacientes.sql, 003_medicos_especialidades.sql y 004_horarios_bloqueos.sql.

DO $$
BEGIN
    CREATE TYPE estado_cita AS ENUM (
        'programada', 'confirmada', 'atendida', 'cancelada', 'inasistencia'
    );
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

DO $$
BEGIN
    CREATE TYPE origen_cita AS ENUM ('web', 'kiosco', 'recepcion');
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

DO $$
BEGIN
    CREATE TYPE tipo_reconsulta AS ENUM ('ausencia_medico', 'seguimiento');
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

DO $$
BEGIN
    CREATE TYPE canal_ficha AS ENUM ('web', 'kiosco');
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

CREATE TABLE IF NOT EXISTS citas (
    id                 INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo             VARCHAR(20) NOT NULL UNIQUE,
    paciente_id        INTEGER NOT NULL REFERENCES pacientes(id) ON DELETE RESTRICT,
    medico_id          INTEGER NOT NULL REFERENCES medicos(id) ON DELETE RESTRICT,
    especialidad_id    INTEGER NOT NULL REFERENCES especialidades(id) ON DELETE RESTRICT,
    fecha              DATE NOT NULL,
    hora_inicio        TIME NOT NULL,
    hora_fin           TIME NOT NULL,
    estado             estado_cita NOT NULL DEFAULT 'programada',
    origen             origen_cita NOT NULL,
    es_prioritaria     BOOLEAN NOT NULL DEFAULT FALSE,
    es_reconsulta      BOOLEAN NOT NULL DEFAULT FALSE,
    reconsulta_tipo    tipo_reconsulta,
    cita_origen_id     INTEGER REFERENCES citas(id) ON DELETE SET NULL,
    motivo             VARCHAR(200),
    motivo_cancelacion VARCHAR(200),
    fecha_registro     TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_cita_horas CHECK (hora_fin > hora_inicio),
    CONSTRAINT chk_cita_reconsulta CHECK (
        (es_reconsulta = FALSE AND reconsulta_tipo IS NULL)
        OR (es_reconsulta = TRUE AND reconsulta_tipo IS NOT NULL)
    )
);

-- Respaldo contra duplicados exactos del mismo slot (RN07).
-- La validación de rangos con duraciones variables se hace en el servicio.
CREATE UNIQUE INDEX IF NOT EXISTS ux_cita_no_superpone
    ON citas (medico_id, fecha, hora_inicio)
    WHERE estado <> 'cancelada';

CREATE INDEX IF NOT EXISTS ix_citas_fecha ON citas (fecha);
CREATE INDEX IF NOT EXISTS ix_citas_paciente ON citas (paciente_id);
CREATE INDEX IF NOT EXISTS ix_citas_medico ON citas (medico_id);
CREATE INDEX IF NOT EXISTS ix_citas_estado ON citas (estado);
CREATE INDEX IF NOT EXISTS ix_citas_origen ON citas (origen);

CREATE TABLE IF NOT EXISTS citas_estado_historial (
    id              INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    cita_id         INTEGER NOT NULL REFERENCES citas(id) ON DELETE RESTRICT,
    estado_anterior estado_cita,
    estado_nuevo    estado_cita NOT NULL,
    usuario_id      INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    observacion     VARCHAR(200),
    fecha           TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS ix_citas_historial_cita
    ON citas_estado_historial (cita_id, fecha);

CREATE TABLE IF NOT EXISTS fichas (
    id                       INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo                   VARCHAR(20) NOT NULL UNIQUE,
    cita_id                  INTEGER NOT NULL UNIQUE REFERENCES citas(id) ON DELETE RESTRICT,
    canal                    canal_ficha NOT NULL,
    codigo_acceso            VARCHAR(4) NOT NULL,
    verificacion_biometrica  BOOLEAN NOT NULL DEFAULT FALSE,
    emitida_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_at               TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_ficha_acceso CHECK (codigo_acceso ~ '^[0-9]{4}$')
);

CREATE INDEX IF NOT EXISTS ix_fichas_emitida ON fichas (emitida_at);

DROP TRIGGER IF EXISTS trg_citas_updated_at ON citas;
CREATE TRIGGER trg_citas_updated_at
    BEFORE UPDATE ON citas
    FOR EACH ROW
    EXECUTE FUNCTION fn_set_updated_at();
