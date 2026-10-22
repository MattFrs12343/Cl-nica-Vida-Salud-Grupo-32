-- 007 — Pagos y auditoría
-- Depende de 005_citas_fichas.sql y de usuarios/roles.

DO $$
BEGIN
    CREATE TYPE metodo_pago AS ENUM ('efectivo', 'visa', 'mastercard');
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

DO $$
BEGIN
    CREATE TYPE accion_auditoria AS ENUM ('INSERT', 'UPDATE', 'DELETE');
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

CREATE TABLE IF NOT EXISTS pagos (
    id                  INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    paciente_id         INTEGER NOT NULL REFERENCES pacientes(id) ON DELETE RESTRICT,
    medico_id           INTEGER NOT NULL REFERENCES medicos(id) ON DELETE RESTRICT,
    registrado_por      INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE RESTRICT,
    monto               NUMERIC(10,2) NOT NULL,
    metodo              metodo_pago NOT NULL,
    referencia_paciente VARCHAR(150),
    fecha               TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_pago_monto CHECK (monto > 0)
);

CREATE INDEX IF NOT EXISTS ix_pagos_medico_fecha ON pagos (medico_id, fecha);
CREATE INDEX IF NOT EXISTS ix_pagos_paciente ON pagos (paciente_id, fecha);

CREATE TABLE IF NOT EXISTS auditoria (
    id             INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    usuario_id     INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    ip             INET,
    tabla_afectada VARCHAR(60) NOT NULL,
    accion         accion_auditoria NOT NULL,
    registro_id    BIGINT,
    datos_antes    JSONB,
    datos_despues  JSONB,
    fecha          TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS ix_auditoria_tabla ON auditoria (tabla_afectada);
CREATE INDEX IF NOT EXISTS ix_auditoria_fecha ON auditoria (fecha);
CREATE INDEX IF NOT EXISTS ix_auditoria_usuario ON auditoria (usuario_id);
