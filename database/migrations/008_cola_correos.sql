-- 008 — Cola de correos (fallback de SMTP)
-- Depende de 001_usuarios.sql para fn_set_updated_at.

DO $$
BEGIN
    CREATE TYPE estado_correo AS ENUM ('pendiente', 'enviado', 'fallido');
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

CREATE TABLE IF NOT EXISTS cola_correos (
    id             INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    destinatario   VARCHAR(120) NOT NULL,
    asunto         VARCHAR(200) NOT NULL,
    cuerpo         TEXT NOT NULL,
    estado         estado_correo NOT NULL DEFAULT 'pendiente',
    intentos       SMALLINT NOT NULL DEFAULT 0,
    ultimo_error   TEXT,
    programado_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    enviado_at     TIMESTAMPTZ,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_cola_correo_intentos CHECK (intentos >= 0)
);

CREATE INDEX IF NOT EXISTS ix_cola_correos_pendientes
    ON cola_correos (programado_at)
    WHERE estado = 'pendiente';

DROP TRIGGER IF EXISTS trg_cola_correos_updated_at ON cola_correos;
CREATE TRIGGER trg_cola_correos_updated_at
    BEFORE UPDATE ON cola_correos
    FOR EACH ROW
    EXECUTE FUNCTION fn_set_updated_at();
