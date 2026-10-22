-- 010 — RLS, roles de aplicación y contexto de sesión
-- La aplicación usa `clinica_app` (no superuser) para las consultas de dominio.
-- `clinica_kiosk` se crea aquí y recibe sus permisos/políticas en la fase del kiosco.
--
-- Nota: no se usa FORCE ROW LEVEL SECURITY para que el rol propietario
-- (migraciones/seeders) pueda seguir administrando la base. El rol de la app
-- no es propietario, por lo que las políticas sí se aplican.

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'clinica_app') THEN
        CREATE ROLE clinica_app NOLOGIN;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'clinica_kiosk') THEN
        CREATE ROLE clinica_kiosk NOLOGIN;
    END IF;
END $$;

-- Helpers de contexto. `app.*` se setea por request desde PHP.
CREATE OR REPLACE FUNCTION fn_app_rol()
RETURNS TEXT
LANGUAGE sql
STABLE AS $$
    SELECT nullif(current_setting('app.rol', true), '');
$$;

CREATE OR REPLACE FUNCTION fn_app_usuario_id()
RETURNS INTEGER
LANGUAGE sql
STABLE AS $$
    SELECT nullif(current_setting('app.usuario_id', true), '')::int;
$$;

CREATE OR REPLACE FUNCTION fn_app_medico_id()
RETURNS INTEGER
LANGUAGE sql
STABLE AS $$
    SELECT nullif(current_setting('app.medico_id', true), '')::int;
$$;

-- SECURITY DEFINER para evitar recursión al consultar pacientes desde
-- políticas de citas/fichas (el dueño de la función no está sujeto a RLS).
CREATE OR REPLACE FUNCTION fn_app_paciente_id()
RETURNS INTEGER
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
    SELECT id
    FROM pacientes
    WHERE usuario_id = fn_app_usuario_id()
    LIMIT 1;
$$;

GRANT USAGE ON SCHEMA public TO clinica_app, clinica_kiosk;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO clinica_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO clinica_app;
GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO clinica_app;

REVOKE ALL ON schema_migrations FROM clinica_app;
REVOKE INSERT, UPDATE, DELETE ON auditoria FROM clinica_app;
GRANT SELECT ON auditoria TO clinica_app;

-- ---------------------------------------------------------------------------
-- Habilitar RLS
-- ---------------------------------------------------------------------------

ALTER TABLE usuarios ENABLE ROW LEVEL SECURITY;
ALTER TABLE pacientes ENABLE ROW LEVEL SECURITY;
ALTER TABLE medicos ENABLE ROW LEVEL SECURITY;
ALTER TABLE especialidades ENABLE ROW LEVEL SECURITY;
ALTER TABLE medico_especialidad ENABLE ROW LEVEL SECURITY;
ALTER TABLE recepcionista_medico ENABLE ROW LEVEL SECURITY;
ALTER TABLE horarios ENABLE ROW LEVEL SECURITY;
ALTER TABLE bloqueos ENABLE ROW LEVEL SECURITY;
ALTER TABLE citas ENABLE ROW LEVEL SECURITY;
ALTER TABLE citas_estado_historial ENABLE ROW LEVEL SECURITY;
ALTER TABLE fichas ENABLE ROW LEVEL SECURITY;
ALTER TABLE consultas ENABLE ROW LEVEL SECURITY;
ALTER TABLE recetas ENABLE ROW LEVEL SECURITY;
ALTER TABLE receta_detalle ENABLE ROW LEVEL SECURITY;
ALTER TABLE documentos ENABLE ROW LEVEL SECURITY;
ALTER TABLE ordenes ENABLE ROW LEVEL SECURITY;
ALTER TABLE orden_detalle ENABLE ROW LEVEL SECURITY;
ALTER TABLE resultados ENABLE ROW LEVEL SECURITY;
ALTER TABLE pagos ENABLE ROW LEVEL SECURITY;
ALTER TABLE auditoria ENABLE ROW LEVEL SECURITY;
ALTER TABLE cola_correos ENABLE ROW LEVEL SECURITY;

-- ---------------------------------------------------------------------------
-- usuarios
-- ---------------------------------------------------------------------------

CREATE POLICY usuarios_admin ON usuarios
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin'));

CREATE POLICY usuarios_self_select ON usuarios
    FOR SELECT
    USING (id = fn_app_usuario_id());

CREATE POLICY usuarios_self_update ON usuarios
    FOR UPDATE
    USING (id = fn_app_usuario_id())
    WITH CHECK (id = fn_app_usuario_id());

-- ---------------------------------------------------------------------------
-- pacientes
-- ---------------------------------------------------------------------------

CREATE POLICY pacientes_admin ON pacientes
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'));

CREATE POLICY pacientes_medico ON pacientes
    FOR SELECT
    USING (
        fn_app_rol() IN ('medico', 'recepcionista_medico')
        AND id IN (
            SELECT paciente_id
            FROM citas
            WHERE medico_id = fn_app_medico_id()
        )
    );

CREATE POLICY pacientes_self_select ON pacientes
    FOR SELECT
    USING (
        fn_app_rol() = 'paciente'
        AND usuario_id = fn_app_usuario_id()
    );

CREATE POLICY pacientes_self_update ON pacientes
    FOR UPDATE
    USING (
        fn_app_rol() = 'paciente'
        AND usuario_id = fn_app_usuario_id()
    )
    WITH CHECK (
        fn_app_rol() = 'paciente'
        AND usuario_id = fn_app_usuario_id()
    );

-- ---------------------------------------------------------------------------
-- medicos
-- ---------------------------------------------------------------------------

CREATE POLICY medicos_admin ON medicos
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin'));

CREATE POLICY medicos_read ON medicos
    FOR SELECT
    USING (
        (fn_app_rol() = 'recepcionista')
        OR (fn_app_rol() = 'medico' AND usuario_id = fn_app_usuario_id())
        OR (
            fn_app_rol() = 'recepcionista_medico'
            AND id IN (
                SELECT medico_id
                FROM recepcionista_medico
                WHERE usuario_id = fn_app_usuario_id()
            )
        )
        OR (fn_app_rol() = 'paciente' AND estado = 'activo')
    );

CREATE POLICY medicos_self_update ON medicos
    FOR UPDATE
    USING (
        fn_app_rol() = 'medico'
        AND usuario_id = fn_app_usuario_id()
    )
    WITH CHECK (
        fn_app_rol() = 'medico'
        AND usuario_id = fn_app_usuario_id()
    );

-- ---------------------------------------------------------------------------
-- especialidades
-- ---------------------------------------------------------------------------

CREATE POLICY especialidades_admin ON especialidades
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin'));

CREATE POLICY especialidades_read ON especialidades
    FOR SELECT
    USING (
        fn_app_rol() IN ('recepcionista', 'recepcionista_medico', 'medico')
        OR (fn_app_rol() = 'paciente' AND estado = 'activo')
    );

-- ---------------------------------------------------------------------------
-- medico_especialidad
-- ---------------------------------------------------------------------------

CREATE POLICY medico_especialidad_admin ON medico_especialidad
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin'));

CREATE POLICY medico_especialidad_read ON medico_especialidad
    FOR SELECT
    USING (
        fn_app_rol() = 'recepcionista'
        OR (fn_app_rol() = 'medico' AND medico_id = fn_app_medico_id())
        OR (
            fn_app_rol() = 'recepcionista_medico'
            AND medico_id IN (
                SELECT medico_id
                FROM recepcionista_medico
                WHERE usuario_id = fn_app_usuario_id()
            )
        )
        OR fn_app_rol() = 'paciente'
    );

-- ---------------------------------------------------------------------------
-- recepcionista_medico
-- ---------------------------------------------------------------------------

CREATE POLICY recepcionista_medico_admin ON recepcionista_medico
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin'));

CREATE POLICY recepcionista_medico_self ON recepcionista_medico
    FOR SELECT
    USING (
        fn_app_rol() = 'recepcionista_medico'
        AND usuario_id = fn_app_usuario_id()
    );

CREATE POLICY recepcionista_medico_medico ON recepcionista_medico
    FOR SELECT
    USING (
        fn_app_rol() = 'medico'
        AND medico_id = fn_app_medico_id()
    );

-- ---------------------------------------------------------------------------
-- horarios
-- ---------------------------------------------------------------------------

CREATE POLICY horarios_admin ON horarios
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin'));

CREATE POLICY horarios_read ON horarios
    FOR SELECT
    USING (
        fn_app_rol() = 'recepcionista'
        OR (fn_app_rol() = 'medico' AND medico_id = fn_app_medico_id())
        OR (
            fn_app_rol() = 'recepcionista_medico'
            AND medico_id IN (
                SELECT medico_id
                FROM recepcionista_medico
                WHERE usuario_id = fn_app_usuario_id()
            )
        )
        OR (fn_app_rol() = 'paciente' AND activo = TRUE)
    );

-- ---------------------------------------------------------------------------
-- bloqueos
-- ---------------------------------------------------------------------------

CREATE POLICY bloqueos_admin ON bloqueos
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin'));

CREATE POLICY bloqueos_read ON bloqueos
    FOR SELECT
    USING (
        fn_app_rol() = 'recepcionista'
        OR (fn_app_rol() = 'medico' AND medico_id = fn_app_medico_id())
        OR (
            fn_app_rol() = 'recepcionista_medico'
            AND medico_id IN (
                SELECT medico_id
                FROM recepcionista_medico
                WHERE usuario_id = fn_app_usuario_id()
            )
        )
    );

-- ---------------------------------------------------------------------------
-- citas
-- ---------------------------------------------------------------------------

CREATE POLICY citas_admin ON citas
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'));

CREATE POLICY citas_recepcionista_medico ON citas
    FOR ALL
    USING (
        fn_app_rol() = 'recepcionista_medico'
        AND medico_id = fn_app_medico_id()
    )
    WITH CHECK (
        fn_app_rol() = 'recepcionista_medico'
        AND medico_id = fn_app_medico_id()
    );

CREATE POLICY citas_medico ON citas
    FOR ALL
    USING (
        fn_app_rol() = 'medico'
        AND medico_id = fn_app_medico_id()
    )
    WITH CHECK (
        fn_app_rol() = 'medico'
        AND medico_id = fn_app_medico_id()
    );

CREATE POLICY citas_paciente ON citas
    FOR SELECT
    USING (
        fn_app_rol() = 'paciente'
        AND paciente_id = fn_app_paciente_id()
    );

-- ---------------------------------------------------------------------------
-- citas_estado_historial
-- ---------------------------------------------------------------------------

CREATE POLICY citas_historial_admin ON citas_estado_historial
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'));

CREATE POLICY citas_historial_recepcionista_medico ON citas_estado_historial
    FOR ALL
    USING (
        fn_app_rol() = 'recepcionista_medico'
        AND EXISTS (
            SELECT 1 FROM citas c
            WHERE c.id = cita_id AND c.medico_id = fn_app_medico_id()
        )
    )
    WITH CHECK (
        fn_app_rol() = 'recepcionista_medico'
        AND EXISTS (
            SELECT 1 FROM citas c
            WHERE c.id = cita_id AND c.medico_id = fn_app_medico_id()
        )
    );

CREATE POLICY citas_historial_medico ON citas_estado_historial
    FOR ALL
    USING (
        fn_app_rol() = 'medico'
        AND EXISTS (
            SELECT 1 FROM citas c
            WHERE c.id = cita_id AND c.medico_id = fn_app_medico_id()
        )
    )
    WITH CHECK (
        fn_app_rol() = 'medico'
        AND EXISTS (
            SELECT 1 FROM citas c
            WHERE c.id = cita_id AND c.medico_id = fn_app_medico_id()
        )
    );

CREATE POLICY citas_historial_paciente ON citas_estado_historial
    FOR SELECT
    USING (
        fn_app_rol() = 'paciente'
        AND EXISTS (
            SELECT 1 FROM citas c
            WHERE c.id = cita_id AND c.paciente_id = fn_app_paciente_id()
        )
    );

-- ---------------------------------------------------------------------------
-- fichas
-- ---------------------------------------------------------------------------

CREATE POLICY fichas_admin ON fichas
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'));

CREATE POLICY fichas_recepcionista_medico ON fichas
    FOR ALL
    USING (
        fn_app_rol() = 'recepcionista_medico'
        AND EXISTS (
            SELECT 1 FROM citas c
            WHERE c.id = cita_id AND c.medico_id = fn_app_medico_id()
        )
    )
    WITH CHECK (
        fn_app_rol() = 'recepcionista_medico'
        AND EXISTS (
            SELECT 1 FROM citas c
            WHERE c.id = cita_id AND c.medico_id = fn_app_medico_id()
        )
    );

CREATE POLICY fichas_medico ON fichas
    FOR ALL
    USING (
        fn_app_rol() = 'medico'
        AND EXISTS (
            SELECT 1 FROM citas c
            WHERE c.id = cita_id AND c.medico_id = fn_app_medico_id()
        )
    )
    WITH CHECK (
        fn_app_rol() = 'medico'
        AND EXISTS (
            SELECT 1 FROM citas c
            WHERE c.id = cita_id AND c.medico_id = fn_app_medico_id()
        )
    );

CREATE POLICY fichas_paciente ON fichas
    FOR SELECT
    USING (
        fn_app_rol() = 'paciente'
        AND EXISTS (
            SELECT 1 FROM citas c
            WHERE c.id = cita_id AND c.paciente_id = fn_app_paciente_id()
        )
    );

-- ---------------------------------------------------------------------------
-- consultas, recetas, documentos, ordenes y pagos
-- ---------------------------------------------------------------------------

CREATE POLICY consultas_admin ON consultas
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'));

CREATE POLICY consultas_recepcionista_medico ON consultas
    FOR ALL
    USING (
        fn_app_rol() = 'recepcionista_medico'
        AND medico_id = fn_app_medico_id()
    )
    WITH CHECK (
        fn_app_rol() = 'recepcionista_medico'
        AND medico_id = fn_app_medico_id()
    );

CREATE POLICY consultas_medico ON consultas
    FOR ALL
    USING (
        fn_app_rol() = 'medico'
        AND medico_id = fn_app_medico_id()
    )
    WITH CHECK (
        fn_app_rol() = 'medico'
        AND medico_id = fn_app_medico_id()
    );

CREATE POLICY consultas_paciente ON consultas
    FOR SELECT
    USING (
        fn_app_rol() = 'paciente'
        AND paciente_id = fn_app_paciente_id()
    );

CREATE POLICY recetas_admin ON recetas
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'));

CREATE POLICY recetas_recepcionista_medico ON recetas
    FOR ALL
    USING (
        fn_app_rol() = 'recepcionista_medico'
        AND medico_id = fn_app_medico_id()
    )
    WITH CHECK (
        fn_app_rol() = 'recepcionista_medico'
        AND medico_id = fn_app_medico_id()
    );

CREATE POLICY recetas_medico ON recetas
    FOR ALL
    USING (
        fn_app_rol() = 'medico'
        AND medico_id = fn_app_medico_id()
    )
    WITH CHECK (
        fn_app_rol() = 'medico'
        AND medico_id = fn_app_medico_id()
    );

CREATE POLICY recetas_paciente ON recetas
    FOR SELECT
    USING (
        fn_app_rol() = 'paciente'
        AND paciente_id = fn_app_paciente_id()
    );

CREATE POLICY documentos_admin ON documentos
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'));

CREATE POLICY documentos_recepcionista_medico ON documentos
    FOR ALL
    USING (
        fn_app_rol() = 'recepcionista_medico'
        AND medico_id = fn_app_medico_id()
    )
    WITH CHECK (
        fn_app_rol() = 'recepcionista_medico'
        AND medico_id = fn_app_medico_id()
    );

CREATE POLICY documentos_medico ON documentos
    FOR ALL
    USING (
        fn_app_rol() = 'medico'
        AND medico_id = fn_app_medico_id()
    )
    WITH CHECK (
        fn_app_rol() = 'medico'
        AND medico_id = fn_app_medico_id()
    );

CREATE POLICY documentos_paciente ON documentos
    FOR SELECT
    USING (
        fn_app_rol() = 'paciente'
        AND paciente_id = fn_app_paciente_id()
    );

CREATE POLICY ordenes_admin ON ordenes
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'));

CREATE POLICY ordenes_recepcionista_medico ON ordenes
    FOR ALL
    USING (
        fn_app_rol() = 'recepcionista_medico'
        AND medico_id = fn_app_medico_id()
    )
    WITH CHECK (
        fn_app_rol() = 'recepcionista_medico'
        AND medico_id = fn_app_medico_id()
    );

CREATE POLICY ordenes_medico ON ordenes
    FOR ALL
    USING (
        fn_app_rol() = 'medico'
        AND medico_id = fn_app_medico_id()
    )
    WITH CHECK (
        fn_app_rol() = 'medico'
        AND medico_id = fn_app_medico_id()
    );

CREATE POLICY ordenes_paciente ON ordenes
    FOR SELECT
    USING (
        fn_app_rol() = 'paciente'
        AND paciente_id = fn_app_paciente_id()
    );

CREATE POLICY pagos_admin ON pagos
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'));

CREATE POLICY pagos_recepcionista_medico ON pagos
    FOR ALL
    USING (
        fn_app_rol() = 'recepcionista_medico'
        AND medico_id = fn_app_medico_id()
    )
    WITH CHECK (
        fn_app_rol() = 'recepcionista_medico'
        AND medico_id = fn_app_medico_id()
    );

CREATE POLICY pagos_medico ON pagos
    FOR ALL
    USING (
        fn_app_rol() = 'medico'
        AND medico_id = fn_app_medico_id()
    )
    WITH CHECK (
        fn_app_rol() = 'medico'
        AND medico_id = fn_app_medico_id()
    );

CREATE POLICY pagos_paciente ON pagos
    FOR SELECT
    USING (
        fn_app_rol() = 'paciente'
        AND paciente_id = fn_app_paciente_id()
    );

-- ---------------------------------------------------------------------------
-- Detalles y resultados: acceso a través del documento padre
-- ---------------------------------------------------------------------------

CREATE POLICY receta_detalle_admin ON receta_detalle
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'));

CREATE POLICY receta_detalle_recepcionista_medico ON receta_detalle
    FOR ALL
    USING (
        fn_app_rol() = 'recepcionista_medico'
        AND EXISTS (
            SELECT 1 FROM recetas r
            WHERE r.id = receta_id AND r.medico_id = fn_app_medico_id()
        )
    )
    WITH CHECK (
        fn_app_rol() = 'recepcionista_medico'
        AND EXISTS (
            SELECT 1 FROM recetas r
            WHERE r.id = receta_id AND r.medico_id = fn_app_medico_id()
        )
    );

CREATE POLICY receta_detalle_medico ON receta_detalle
    FOR ALL
    USING (
        fn_app_rol() = 'medico'
        AND EXISTS (
            SELECT 1 FROM recetas r
            WHERE r.id = receta_id AND r.medico_id = fn_app_medico_id()
        )
    )
    WITH CHECK (
        fn_app_rol() = 'medico'
        AND EXISTS (
            SELECT 1 FROM recetas r
            WHERE r.id = receta_id AND r.medico_id = fn_app_medico_id()
        )
    );

CREATE POLICY receta_detalle_paciente ON receta_detalle
    FOR SELECT
    USING (
        fn_app_rol() = 'paciente'
        AND EXISTS (
            SELECT 1 FROM recetas r
            WHERE r.id = receta_id AND r.paciente_id = fn_app_paciente_id()
        )
    );

CREATE POLICY orden_detalle_admin ON orden_detalle
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'));

CREATE POLICY orden_detalle_recepcionista_medico ON orden_detalle
    FOR ALL
    USING (
        fn_app_rol() = 'recepcionista_medico'
        AND EXISTS (
            SELECT 1 FROM ordenes o
            WHERE o.id = orden_id AND o.medico_id = fn_app_medico_id()
        )
    )
    WITH CHECK (
        fn_app_rol() = 'recepcionista_medico'
        AND EXISTS (
            SELECT 1 FROM ordenes o
            WHERE o.id = orden_id AND o.medico_id = fn_app_medico_id()
        )
    );

CREATE POLICY orden_detalle_medico ON orden_detalle
    FOR ALL
    USING (
        fn_app_rol() = 'medico'
        AND EXISTS (
            SELECT 1 FROM ordenes o
            WHERE o.id = orden_id AND o.medico_id = fn_app_medico_id()
        )
    )
    WITH CHECK (
        fn_app_rol() = 'medico'
        AND EXISTS (
            SELECT 1 FROM ordenes o
            WHERE o.id = orden_id AND o.medico_id = fn_app_medico_id()
        )
    );

CREATE POLICY orden_detalle_paciente ON orden_detalle
    FOR SELECT
    USING (
        fn_app_rol() = 'paciente'
        AND EXISTS (
            SELECT 1 FROM ordenes o
            WHERE o.id = orden_id AND o.paciente_id = fn_app_paciente_id()
        )
    );

CREATE POLICY resultados_admin ON resultados
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin', 'recepcionista'));

CREATE POLICY resultados_recepcionista_medico ON resultados
    FOR ALL
    USING (
        fn_app_rol() = 'recepcionista_medico'
        AND EXISTS (
            SELECT 1 FROM ordenes o
            WHERE o.id = orden_id AND o.medico_id = fn_app_medico_id()
        )
    )
    WITH CHECK (
        fn_app_rol() = 'recepcionista_medico'
        AND EXISTS (
            SELECT 1 FROM ordenes o
            WHERE o.id = orden_id AND o.medico_id = fn_app_medico_id()
        )
    );

CREATE POLICY resultados_medico ON resultados
    FOR ALL
    USING (
        fn_app_rol() = 'medico'
        AND EXISTS (
            SELECT 1 FROM ordenes o
            WHERE o.id = orden_id AND o.medico_id = fn_app_medico_id()
        )
    )
    WITH CHECK (
        fn_app_rol() = 'medico'
        AND EXISTS (
            SELECT 1 FROM ordenes o
            WHERE o.id = orden_id AND o.medico_id = fn_app_medico_id()
        )
    );

CREATE POLICY resultados_paciente ON resultados
    FOR SELECT
    USING (
        fn_app_rol() = 'paciente'
        AND EXISTS (
            SELECT 1 FROM ordenes o
            WHERE o.id = orden_id AND o.paciente_id = fn_app_paciente_id()
        )
    );

-- ---------------------------------------------------------------------------
-- auditoría y cola de correos
-- ---------------------------------------------------------------------------

CREATE POLICY auditoria_admin ON auditoria
    FOR SELECT
    USING (fn_app_rol() IN ('super_admin', 'admin'));

CREATE POLICY cola_correos_admin ON cola_correos
    FOR ALL
    USING (fn_app_rol() IN ('super_admin', 'admin'))
    WITH CHECK (fn_app_rol() IN ('super_admin', 'admin'));

CREATE POLICY cola_correos_insert ON cola_correos
    FOR INSERT
    WITH CHECK (true);
