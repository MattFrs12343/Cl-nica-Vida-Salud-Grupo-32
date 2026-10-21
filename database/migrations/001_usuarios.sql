-- 001 — Usuarios y roles
-- Base mínima para autenticación. Las demás tablas se agregan en las
-- migraciones 002+ (Fase 1).

DO $$
BEGIN
    CREATE TYPE rol_usuario AS ENUM (
        'super_admin',
        'admin',
        'recepcionista',
        'recepcionista_medico',
        'medico',
        'paciente'
    );
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

CREATE TABLE IF NOT EXISTS usuarios (
    id                 INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    username           VARCHAR(60) UNIQUE,
    email              VARCHAR(120) UNIQUE,
    password_hash      VARCHAR(255) NOT NULL,
    rol                rol_usuario NOT NULL,
    activo             BOOLEAN NOT NULL DEFAULT TRUE,
    intentos_fallidos  SMALLINT NOT NULL DEFAULT 0,
    bloqueado          BOOLEAN NOT NULL DEFAULT FALSE,
    token_reset        VARCHAR(128),
    token_reset_expira TIMESTAMPTZ,
    ultimo_acceso      TIMESTAMPTZ,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_usuario_identificador CHECK (username IS NOT NULL OR email IS NOT NULL),
    CONSTRAINT chk_usuario_intentos CHECK (intentos_fallidos >= 0)
);

CREATE INDEX IF NOT EXISTS idx_usuarios_rol ON usuarios (rol);
CREATE INDEX IF NOT EXISTS idx_usuarios_activo ON usuarios (activo);
CREATE INDEX IF NOT EXISTS idx_usuarios_token_reset ON usuarios (token_reset)
    WHERE token_reset IS NOT NULL;

CREATE OR REPLACE FUNCTION fn_set_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_usuarios_updated_at ON usuarios;
CREATE TRIGGER trg_usuarios_updated_at
    BEFORE UPDATE ON usuarios
    FOR EACH ROW
    EXECUTE FUNCTION fn_set_updated_at();
