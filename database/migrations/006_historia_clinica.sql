-- 006 — Historia clínica, recetas, documentos y órdenes
-- Depende de 005_citas_fichas.sql.

DO $$
BEGIN
    CREATE TYPE tipo_documento AS ENUM (
        'certificado', 'informe', 'constancia', 'interconsulta'
    );
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

DO $$
BEGIN
    CREATE TYPE tipo_orden AS ENUM ('laboratorio', 'imagen', 'otro');
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

CREATE TABLE IF NOT EXISTS consultas (
    id             INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    cita_id        INTEGER NOT NULL UNIQUE REFERENCES citas(id) ON DELETE RESTRICT,
    paciente_id    INTEGER NOT NULL REFERENCES pacientes(id) ON DELETE RESTRICT,
    medico_id      INTEGER NOT NULL REFERENCES medicos(id) ON DELETE RESTRICT,
    motivo         VARCHAR(200),
    anamnesis      TEXT,
    signos_vitales JSONB,
    examen_fisico  TEXT,
    diagnostico    TEXT,
    tratamiento    TEXT,
    indicaciones   TEXT,
    fecha          TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS ix_consultas_paciente ON consultas (paciente_id, fecha);
CREATE INDEX IF NOT EXISTS ix_consultas_medico ON consultas (medico_id, fecha);

CREATE TABLE IF NOT EXISTS recetas (
    id                INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo            VARCHAR(20) NOT NULL UNIQUE,
    codigo_acceso     VARCHAR(4) NOT NULL,
    consulta_id       INTEGER NOT NULL REFERENCES consultas(id) ON DELETE RESTRICT,
    paciente_id       INTEGER NOT NULL REFERENCES pacientes(id) ON DELETE RESTRICT,
    medico_id         INTEGER NOT NULL REFERENCES medicos(id) ON DELETE RESTRICT,
    enviado_whatsapp  BOOLEAN NOT NULL DEFAULT FALSE,
    fecha             TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_receta_acceso CHECK (codigo_acceso ~ '^[0-9]{4}$')
);

CREATE INDEX IF NOT EXISTS ix_recetas_consulta ON recetas (consulta_id);
CREATE INDEX IF NOT EXISTS ix_recetas_paciente ON recetas (paciente_id, fecha);

CREATE TABLE IF NOT EXISTS receta_detalle (
    id             INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    receta_id      INTEGER NOT NULL REFERENCES recetas(id) ON DELETE CASCADE,
    medicamento    VARCHAR(150) NOT NULL,
    dosis          VARCHAR(80),
    via            VARCHAR(50),
    frecuencia     VARCHAR(80),
    duracion       VARCHAR(50),
    instrucciones  VARCHAR(200)
);

CREATE INDEX IF NOT EXISTS ix_receta_detalle_receta
    ON receta_detalle (receta_id);

CREATE TABLE IF NOT EXISTS documentos (
    id                INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo            VARCHAR(20) NOT NULL UNIQUE,
    codigo_acceso     VARCHAR(4) NOT NULL,
    consulta_id       INTEGER NOT NULL REFERENCES consultas(id) ON DELETE RESTRICT,
    paciente_id       INTEGER NOT NULL REFERENCES pacientes(id) ON DELETE RESTRICT,
    medico_id         INTEGER NOT NULL REFERENCES medicos(id) ON DELETE RESTRICT,
    tipo              tipo_documento NOT NULL,
    contenido         TEXT,
    enviado_whatsapp  BOOLEAN NOT NULL DEFAULT FALSE,
    fecha             TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_documento_acceso CHECK (codigo_acceso ~ '^[0-9]{4}$')
);

CREATE INDEX IF NOT EXISTS ix_documentos_consulta ON documentos (consulta_id);
CREATE INDEX IF NOT EXISTS ix_documentos_paciente ON documentos (paciente_id, fecha);

CREATE TABLE IF NOT EXISTS ordenes (
    id                INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo            VARCHAR(20) NOT NULL UNIQUE,
    codigo_acceso     VARCHAR(4) NOT NULL,
    consulta_id       INTEGER NOT NULL REFERENCES consultas(id) ON DELETE RESTRICT,
    paciente_id       INTEGER NOT NULL REFERENCES pacientes(id) ON DELETE RESTRICT,
    medico_id         INTEGER NOT NULL REFERENCES medicos(id) ON DELETE RESTRICT,
    tipo              tipo_orden NOT NULL,
    enviado_whatsapp  BOOLEAN NOT NULL DEFAULT FALSE,
    fecha             TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_orden_acceso CHECK (codigo_acceso ~ '^[0-9]{4}$')
);

CREATE INDEX IF NOT EXISTS ix_ordenes_consulta ON ordenes (consulta_id);
CREATE INDEX IF NOT EXISTS ix_ordenes_paciente ON ordenes (paciente_id, fecha);

CREATE TABLE IF NOT EXISTS orden_detalle (
    id             INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    orden_id       INTEGER NOT NULL REFERENCES ordenes(id) ON DELETE CASCADE,
    estudio        VARCHAR(150) NOT NULL,
    observaciones  VARCHAR(200)
);

CREATE INDEX IF NOT EXISTS ix_orden_detalle_orden
    ON orden_detalle (orden_id);

CREATE TABLE IF NOT EXISTS resultados (
    id         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    orden_id   INTEGER NOT NULL REFERENCES ordenes(id) ON DELETE CASCADE,
    archivo    VARCHAR(255),
    texto      TEXT,
    revisado   BOOLEAN NOT NULL DEFAULT FALSE,
    fecha      TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_resultado_contenido CHECK (archivo IS NOT NULL OR texto IS NOT NULL)
);

CREATE INDEX IF NOT EXISTS ix_resultados_orden ON resultados (orden_id, fecha);
