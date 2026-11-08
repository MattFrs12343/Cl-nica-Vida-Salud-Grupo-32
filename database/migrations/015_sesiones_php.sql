-- Sesiones de PHP persistidas en base de datos.
--
-- El filesystem de un contenedor en Render (plan free) no es persistente:
-- cada vez que el servicio se duerme por inactividad y despierta (o se
-- redeploya), se pierde todo lo que el handler de sesiones por defecto de
-- PHP guarda en disco local. Eso invalidaba silenciosamente la sesión de
-- cualquiera que hubiera cargado una pagina antes del reinicio, y con ella
-- el token CSRF — lo que se veia como "Token CSRF invalido o expirado" al
-- enviar un formulario (activar cuenta, recuperar contraseña, login, etc.)
-- aunque el usuario no hubiera hecho nada mal.
--
-- Guardar las sesiones en Postgres (que sí persiste) resuelve esto de raíz.

CREATE TABLE IF NOT EXISTS sesiones (
    id VARCHAR(128) PRIMARY KEY,
    datos BYTEA NOT NULL DEFAULT ''::bytea,
    actualizado_en TIMESTAMPTZ NOT NULL DEFAULT now()
);

GRANT SELECT, INSERT, UPDATE, DELETE ON sesiones TO clinica_app, clinica_kiosk;
