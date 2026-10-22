-- 002 — Pacientes
-- Depende de 001_usuarios.sql (rol_usuario y fn_set_updated_at).

DO $$
BEGIN
    CREATE TYPE estado_paciente AS ENUM ('activo', 'inactivo');
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

DO $$
BEGIN
    CREATE TYPE sexo AS ENUM ('masculino', 'femenino');
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

CREATE TABLE IF NOT EXISTS pacientes (
    id                     INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo                 VARCHAR(20) NOT NULL UNIQUE,
    usuario_id             INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    ci                     VARCHAR(20) NOT NULL UNIQUE,
    nombres                VARCHAR(100) NOT NULL,
    apellidos              VARCHAR(100) NOT NULL,
    fecha_nacimiento       DATE NOT NULL,
    sexo                   sexo NOT NULL,
    telefono               VARCHAR(20),
    email                  VARCHAR(120),
    direccion              VARCHAR(200),
    tipo_sangre            VARCHAR(5),
    alergias               TEXT,
    enfermedades_cronicas  TEXT,
    medicamentos           TEXT,
    antecedentes           TEXT,
    emergencia_nombre      VARCHAR(100),
    emergencia_parentesco  VARCHAR(50),
    emergencia_telefono    VARCHAR(20),
    estado                 estado_paciente NOT NULL DEFAULT 'activo',
    created_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT ux_pacientes_usuario UNIQUE (usuario_id),
    CONSTRAINT chk_paciente_tipo_sangre CHECK (
        tipo_sangre IS NULL OR tipo_sangre ~ '^(A|B|AB|O)[+-]$'
    )
);

CREATE INDEX IF NOT EXISTS ix_pacientes_estado ON pacientes (estado);
CREATE INDEX IF NOT EXISTS ix_pacientes_nombre ON pacientes (apellidos, nombres);

DROP TRIGGER IF EXISTS trg_pacientes_updated_at ON pacientes;
CREATE TRIGGER trg_pacientes_updated_at
    BEFORE UPDATE ON pacientes
    FOR EACH ROW
    EXECUTE FUNCTION fn_set_updated_at();
