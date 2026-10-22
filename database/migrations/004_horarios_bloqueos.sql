-- 004 — Horarios, cupos y bloqueos de agenda
-- Depende de 003_medicos_especialidades.sql.

CREATE TABLE IF NOT EXISTS horarios (
    id            INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    medico_id     INTEGER NOT NULL REFERENCES medicos(id) ON DELETE RESTRICT,
    dia_semana    SMALLINT NOT NULL,
    hora_inicio   TIME NOT NULL,
    hora_fin      TIME NOT NULL,
    cupos_web     SMALLINT NOT NULL DEFAULT 0,
    cupos_kiosco  SMALLINT NOT NULL DEFAULT 0,
    activo        BOOLEAN NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_horario_dia CHECK (dia_semana BETWEEN 0 AND 6),
    CONSTRAINT chk_horario_horas CHECK (hora_fin > hora_inicio),
    CONSTRAINT chk_horario_cupos_web CHECK (cupos_web >= 0),
    CONSTRAINT chk_horario_cupos_kiosco CHECK (cupos_kiosco >= 0)
);

CREATE INDEX IF NOT EXISTS ix_horarios_medico_dia
    ON horarios (medico_id, dia_semana)
    WHERE activo = TRUE;

CREATE TABLE IF NOT EXISTS bloqueos (
    id           INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    medico_id    INTEGER NOT NULL REFERENCES medicos(id) ON DELETE RESTRICT,
    fecha        DATE NOT NULL,
    hora_inicio  TIME,
    hora_fin     TIME,
    motivo       VARCHAR(120),
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_bloqueo_horas CHECK (
        (hora_inicio IS NULL AND hora_fin IS NULL)
        OR (hora_inicio IS NOT NULL AND hora_fin IS NOT NULL AND hora_fin > hora_inicio)
    )
);

CREATE INDEX IF NOT EXISTS ix_bloqueos_medico_fecha
    ON bloqueos (medico_id, fecha);

-- Evita franjas activas solapadas para el mismo médico y día.
-- Se usa un trigger porque PostgreSQL no tiene un tipo `timerange` nativo.
CREATE OR REPLACE FUNCTION fn_horarios_no_solape()
RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.activo IS TRUE THEN
        IF EXISTS (
            SELECT 1
            FROM horarios h
            WHERE h.medico_id = NEW.medico_id
              AND h.dia_semana = NEW.dia_semana
              AND h.activo = TRUE
              AND h.id <> COALESCE(NEW.id, 0)
              AND NEW.hora_inicio < h.hora_fin
              AND NEW.hora_fin > h.hora_inicio
        ) THEN
            RAISE EXCEPTION 'La franja se solapa con otra franja activa del mismo médico y día.'
                USING ERRCODE = '23P01';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_horarios_no_solape ON horarios;
CREATE TRIGGER trg_horarios_no_solape
    BEFORE INSERT OR UPDATE ON horarios
    FOR EACH ROW
    EXECUTE FUNCTION fn_horarios_no_solape();

DROP TRIGGER IF EXISTS trg_horarios_updated_at ON horarios;
CREATE TRIGGER trg_horarios_updated_at
    BEFORE UPDATE ON horarios
    FOR EACH ROW
    EXECUTE FUNCTION fn_set_updated_at();
