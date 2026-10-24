-- 011 - M-28: control de reimpresiones de fichas
-- Permite registrar cuantas veces se reimprimio una ficha y la ultima fecha.

ALTER TABLE fichas
    ADD COLUMN IF NOT EXISTS reimpresiones INTEGER NOT NULL DEFAULT 0;

ALTER TABLE fichas
    ADD COLUMN IF NOT EXISTS ultima_reimpresion_at TIMESTAMPTZ;

ALTER TABLE fichas
    DROP CONSTRAINT IF EXISTS chk_ficha_reimpresiones;

ALTER TABLE fichas
    ADD CONSTRAINT chk_ficha_reimpresiones CHECK (reimpresiones >= 0);
