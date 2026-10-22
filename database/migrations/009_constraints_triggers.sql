-- 009 — Generación de códigos, checks adicionales y auditoría
-- Depende de 001–008.

-- ---------------------------------------------------------------------------
-- Generación automática de códigos visibles
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION fn_generar_codigo()
RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.codigo IS NULL OR btrim(NEW.codigo) = '' THEN
        NEW.codigo := TG_ARGV[0] || '-' || lpad(NEW.id::text, TG_ARGV[1]::int, '0');
    END IF;

    RETURN NEW;
END;
$$;

DO $$
DECLARE
    configuracion RECORD;
BEGIN
    FOR configuracion IN
        SELECT *
        FROM (VALUES
            ('pacientes', 'PAC', '4'),
            ('medicos', 'MED', '4'),
            ('citas', 'CM', '4'),
            ('fichas', 'FIC', '4'),
            ('recetas', 'RSV', '6'),
            ('documentos', 'DOC', '6'),
            ('ordenes', 'ORD', '6')
        ) AS tabla_config(tabla, prefijo, ancho)
    LOOP
        EXECUTE format(
            'DROP TRIGGER IF EXISTS trg_%I_codigo ON %I',
            configuracion.tabla,
            configuracion.tabla
        );
        EXECUTE format(
            'CREATE TRIGGER trg_%I_codigo
             BEFORE INSERT ON %I
             FOR EACH ROW
             EXECUTE FUNCTION fn_generar_codigo(%L, %L)',
            configuracion.tabla,
            configuracion.tabla,
            configuracion.prefijo,
            configuracion.ancho
        );
    END LOOP;
END $$;

-- ---------------------------------------------------------------------------
-- Checks adicionales
-- ---------------------------------------------------------------------------

ALTER TABLE pacientes
    ADD CONSTRAINT chk_paciente_fecha_nacimiento
    CHECK (fecha_nacimiento >= DATE '1900-01-01');

ALTER TABLE consultas
    ADD CONSTRAINT chk_consulta_signos_vitales
    CHECK (signos_vitales IS NULL OR jsonb_typeof(signos_vitales) = 'object');

-- ---------------------------------------------------------------------------
-- Auditoría
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION fn_auditar()
RETURNS TRIGGER
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
DECLARE
    datos_antes JSONB;
    datos_despues JSONB;
BEGIN
    IF TG_OP <> 'INSERT' THEN
        datos_antes := to_jsonb(OLD) - 'password_hash' - 'token_reset';
    END IF;

    IF TG_OP <> 'DELETE' THEN
        datos_despues := to_jsonb(NEW) - 'password_hash' - 'token_reset';
    END IF;

    INSERT INTO auditoria (
        usuario_id,
        ip,
        tabla_afectada,
        accion,
        registro_id,
        datos_antes,
        datos_despues
    ) VALUES (
        nullif(current_setting('app.usuario_id', true), '')::int,
        nullif(current_setting('app.ip', true), '')::inet,
        TG_TABLE_NAME,
        TG_OP::accion_auditoria,
        COALESCE(
            (to_jsonb(NEW) ->> 'id')::bigint,
            (to_jsonb(OLD) ->> 'id')::bigint
        ),
        datos_antes,
        datos_despues
    );

    RETURN COALESCE(NEW, OLD);
END;
$$;

DO $$
DECLARE
    tabla TEXT;
BEGIN
    FOREACH tabla IN ARRAY ARRAY[
        'usuarios',
        'pacientes',
        'medicos',
        'especialidades',
        'horarios',
        'bloqueos',
        'medico_especialidad',
        'recepcionista_medico',
        'citas',
        'citas_estado_historial',
        'fichas',
        'consultas',
        'recetas',
        'receta_detalle',
        'documentos',
        'ordenes',
        'orden_detalle',
        'resultados',
        'pagos'
    ]
    LOOP
        EXECUTE format(
            'DROP TRIGGER IF EXISTS trg_%I_auditoria ON %I',
            tabla,
            tabla
        );
        EXECUTE format(
            'CREATE TRIGGER trg_%I_auditoria
             AFTER INSERT OR UPDATE OR DELETE ON %I
             FOR EACH ROW
             EXECUTE FUNCTION fn_auditar()',
            tabla,
            tabla
        );
    END LOOP;
END $$;
