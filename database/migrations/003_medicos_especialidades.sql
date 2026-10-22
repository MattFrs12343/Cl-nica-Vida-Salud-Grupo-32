-- 003 — Catálogo de médicos y especialidades
-- Depende de 002_pacientes.sql (enum estado_paciente y sexo).

DO $$
BEGIN
    CREATE TYPE sexo_permitido AS ENUM ('ambos', 'femenino', 'masculino');
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

CREATE TABLE IF NOT EXISTS especialidades (
    id               INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    nombre           VARCHAR(80) NOT NULL,
    descripcion      TEXT,
    sexo_permitido   sexo_permitido NOT NULL DEFAULT 'ambos',
    edad_min         SMALLINT,
    edad_max         SMALLINT,
    destacada        BOOLEAN NOT NULL DEFAULT FALSE,
    icono            VARCHAR(50),
    estado           estado_paciente NOT NULL DEFAULT 'activo',
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_especialidad_edad_min CHECK (edad_min IS NULL OR edad_min >= 0),
    CONSTRAINT chk_especialidad_edad_max CHECK (edad_max IS NULL OR edad_max >= 0),
    CONSTRAINT chk_especialidad_rango CHECK (
        edad_min IS NULL OR edad_max IS NULL OR edad_min <= edad_max
    )
);

-- Unicidad por nombre normalizado (trim + lower).
CREATE UNIQUE INDEX IF NOT EXISTS ux_especialidad_nombre
    ON especialidades (lower(nombre));

CREATE INDEX IF NOT EXISTS ix_especialidades_estado ON especialidades (estado);
CREATE INDEX IF NOT EXISTS ix_especialidades_destacada
    ON especialidades (destacada)
    WHERE destacada = TRUE;

CREATE TABLE IF NOT EXISTS medicos (
    id                   INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo               VARCHAR(20) NOT NULL UNIQUE,
    usuario_id           INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    registro_profesional VARCHAR(50) NOT NULL UNIQUE,
    nombres              VARCHAR(100) NOT NULL,
    apellidos            VARCHAR(100) NOT NULL,
    telefono             VARCHAR(20),
    email                VARCHAR(120),
    duracion_cita_min    SMALLINT NOT NULL DEFAULT 30,
    qr_imagen            VARCHAR(255),
    estado               estado_paciente NOT NULL DEFAULT 'activo',
    created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT ux_medicos_usuario UNIQUE (usuario_id),
    CONSTRAINT chk_medico_duracion CHECK (duracion_cita_min > 0)
);

CREATE INDEX IF NOT EXISTS ix_medicos_estado ON medicos (estado);
CREATE INDEX IF NOT EXISTS ix_medicos_nombre ON medicos (apellidos, nombres);

CREATE TABLE IF NOT EXISTS medico_especialidad (
    medico_id       INTEGER NOT NULL REFERENCES medicos(id) ON DELETE CASCADE,
    especialidad_id INTEGER NOT NULL REFERENCES especialidades(id) ON DELETE CASCADE,
    PRIMARY KEY (medico_id, especialidad_id)
);

CREATE INDEX IF NOT EXISTS ix_medico_especialidad_especialidad
    ON medico_especialidad (especialidad_id);

CREATE TABLE IF NOT EXISTS recepcionista_medico (
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    medico_id  INTEGER NOT NULL REFERENCES medicos(id) ON DELETE CASCADE,
    PRIMARY KEY (usuario_id, medico_id)
);

CREATE INDEX IF NOT EXISTS ix_recepcionista_medico_medico
    ON recepcionista_medico (medico_id);

DROP TRIGGER IF EXISTS trg_especialidades_updated_at ON especialidades;
CREATE TRIGGER trg_especialidades_updated_at
    BEFORE UPDATE ON especialidades
    FOR EACH ROW
    EXECUTE FUNCTION fn_set_updated_at();

DROP TRIGGER IF EXISTS trg_medicos_updated_at ON medicos;
CREATE TRIGGER trg_medicos_updated_at
    BEFORE UPDATE ON medicos
    FOR EACH ROW
    EXECUTE FUNCTION fn_set_updated_at();
