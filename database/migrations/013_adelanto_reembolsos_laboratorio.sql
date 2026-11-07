-- 013 — Adelanto de consulta, reembolsos y catálogo de laboratorio
--
-- Modelo de negocio:
--   * Consulta con especialista: 300 Bs para todas las especialidades.
--   * Al reservar (web/kiosco) se paga un adelanto del 50% (150 Bs) por QR.
--   * Recepción confirma el adelanto y, al terminar la consulta, cobra el saldo.
--   * Cancelar antes de la cita permite reprogramar (reconsulta) o pedir
--     reembolso con comprobante; la inasistencia retiene el adelanto.
--   * Recepción define el catálogo de laboratorio con precios.

CREATE TABLE IF NOT EXISTS configuracion (
    clave      VARCHAR(60) PRIMARY KEY,
    valor      TEXT NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

INSERT INTO configuracion (clave, valor) VALUES
    ('precio_consulta', '300.00'),
    ('adelanto_porcentaje', '50'),
    ('pago_expira_minutos', '120'),
    ('qr_pago_contenido', 'VIDASALUD|PAGO|CUENTA-CLINICA-001'),
    ('qr_pago_titular', 'Clínica Vida Salud')
ON CONFLICT (clave) DO NOTHING;

DO $$
BEGIN
    CREATE TYPE estado_pago_cita AS ENUM (
        'pendiente',
        'adelanto_pagado',
        'pagado',
        'reembolso_pendiente',
        'reembolsado',
        'transferido',
        'perdido',
        'expirado',
        'sin_pago'
    );
EXCEPTION
    WHEN duplicate_object THEN NULL;
END $$;

ALTER TABLE citas
    ADD COLUMN IF NOT EXISTS precio_total NUMERIC(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS adelanto NUMERIC(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS saldo NUMERIC(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS estado_pago estado_pago_cita NOT NULL DEFAULT 'pendiente',
    ADD COLUMN IF NOT EXISTS pago_adelanto_id INTEGER,
    ADD COLUMN IF NOT EXISTS pago_expira_en TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS reembolso_codigo VARCHAR(20),
    ADD COLUMN IF NOT EXISTS reembolso_monto NUMERIC(10,2) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS reembolso_solicitado_en TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS reembolso_entregado_en TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS reembolso_registrado_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_citas_pago_adelanto'
    ) THEN
        ALTER TABLE citas
            ADD CONSTRAINT fk_citas_pago_adelanto
            FOREIGN KEY (pago_adelanto_id) REFERENCES pagos(id) ON DELETE SET NULL;
    END IF;
END $$;

CREATE UNIQUE INDEX IF NOT EXISTS ux_citas_reembolso_codigo
    ON citas (reembolso_codigo)
    WHERE reembolso_codigo IS NOT NULL;

-- Las citas anteriores al modelo de adelanto no generan cobro pendiente.
UPDATE citas
SET estado_pago = 'sin_pago'
WHERE precio_total = 0
  AND adelanto = 0
  AND estado_pago = 'pendiente';

CREATE INDEX IF NOT EXISTS ix_citas_pago_pendiente
    ON citas (pago_expira_en)
    WHERE estado_pago = 'pendiente';

ALTER TYPE metodo_pago ADD VALUE IF NOT EXISTS 'qr';
ALTER TYPE metodo_pago ADD VALUE IF NOT EXISTS 'pasarela';

ALTER TYPE tipo_reconsulta ADD VALUE IF NOT EXISTS 'reprogramacion';

ALTER TABLE pagos
    ADD COLUMN IF NOT EXISTS cita_id INTEGER REFERENCES citas(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS orden_id INTEGER REFERENCES ordenes(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS tipo VARCHAR(20) NOT NULL DEFAULT 'consulta',
    ADD COLUMN IF NOT EXISTS estado VARCHAR(20) NOT NULL DEFAULT 'confirmado',
    ADD COLUMN IF NOT EXISTS referencia VARCHAR(80),
    ADD COLUMN IF NOT EXISTS confirmado_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS confirmado_en TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS reembolso_de INTEGER;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_pagos_reembolso_de'
    ) THEN
        ALTER TABLE pagos
            ADD CONSTRAINT fk_pagos_reembolso_de
            FOREIGN KEY (reembolso_de) REFERENCES pagos(id) ON DELETE SET NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS ix_pagos_cita ON pagos (cita_id);
CREATE INDEX IF NOT EXISTS ix_pagos_orden ON pagos (orden_id);
CREATE INDEX IF NOT EXISTS ix_pagos_tipo_estado ON pagos (tipo, estado);

CREATE TABLE IF NOT EXISTS examenes_laboratorio (
    id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    codigo      VARCHAR(20) UNIQUE,
    nombre      VARCHAR(150) NOT NULL,
    tipo        tipo_orden NOT NULL DEFAULT 'laboratorio',
    precio      NUMERIC(10,2) NOT NULL DEFAULT 0,
    activo      BOOLEAN NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT chk_examen_precio CHECK (precio >= 0)
);

CREATE INDEX IF NOT EXISTS ix_examenes_activo ON examenes_laboratorio (activo, nombre);

ALTER TABLE orden_detalle
    ADD COLUMN IF NOT EXISTS examen_id INTEGER REFERENCES examenes_laboratorio(id) ON DELETE SET NULL,
    ADD COLUMN IF NOT EXISTS precio NUMERIC(10,2) NOT NULL DEFAULT 0;

ALTER TABLE ordenes
    ADD COLUMN IF NOT EXISTS total NUMERIC(10,2) NOT NULL DEFAULT 0;

UPDATE ordenes o
SET total = COALESCE(
    (SELECT SUM(d.precio) FROM orden_detalle d WHERE d.orden_id = o.id),
    0
)
WHERE o.total = 0;

DROP TRIGGER IF EXISTS trg_examenes_updated_at ON examenes_laboratorio;
CREATE TRIGGER trg_examenes_updated_at
    BEFORE UPDATE ON examenes_laboratorio
    FOR EACH ROW
    EXECUTE FUNCTION fn_set_updated_at();

-- Catálogo demo: recepción puede ajustar precios y desactivar exámenes.
INSERT INTO examenes_laboratorio (codigo, nombre, tipo, precio) VALUES
    ('LAB-HEMO', 'Hemograma completo', 'laboratorio', 80.00),
    ('LAB-GLUC', 'Glucosa en ayunas', 'laboratorio', 30.00),
    ('LAB-LIPI', 'Perfil lipídico', 'laboratorio', 120.00),
    ('LAB-ORIN', 'Examen de orina', 'laboratorio', 40.00),
    ('LAB-TSH',  'TSH (tiroides)', 'laboratorio', 90.00),
    ('IMG-TORAX', 'Radiografía de tórax', 'imagen', 150.00),
    ('IMG-ABD',  'Ecografía abdominal', 'imagen', 200.00)
ON CONFLICT (codigo) DO NOTHING;

-- El rol de aplicación solo lee catálogo y configuración. La escritura pasa
-- por la capa administrativa, que opera con el rol propietario.
GRANT SELECT ON configuracion TO clinica_app;
GRANT SELECT ON examenes_laboratorio TO clinica_app;
