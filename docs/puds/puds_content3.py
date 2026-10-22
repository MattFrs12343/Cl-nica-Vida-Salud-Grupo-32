# -*- coding: utf-8 -*-
import os
from docx.shared import Cm, Pt
from docx.enum.text import WD_ALIGN_PARAGRAPH
from puds_lib import b, ASSETS, _set_run_font, NAVY


def chapter7():
    b.h1("7. IMPLEMENTACIÓN DEL SISTEMA")
    b.h2("7.1 Entorno y tecnologías")
    b.p("La implementación utiliza PHP 8.1+ con arquitectura MVC manual, PostgreSQL 16 como base de datos, HTML5, CSS3 y JavaScript para la interfaz. Los paneles internos se apoyan en la plantilla Gentelella; la landing y el kiosco tienen un diseño propio, responsive y táctil. El correo se envía con PHPMailer y Brevo, y los códigos QR se generan en el servidor.")
    b.table(
        ["Componente", "Tecnología", "Uso"],
        [
            ("Lenguaje backend", "PHP 8.1+", "Controladores, servicios, modelos y vistas."),
            ("Base de datos", "PostgreSQL 16", "Persistencia, enums, JSONB, RLS e índices."),
            ("Acceso a datos", "PDO", "Consultas preparadas y transacciones."),
            ("Frontend", "HTML5, CSS3, JavaScript", "Landing, paneles y kiosco."),
            ("Plantilla administrativa", "Gentelella", "Dashboard y vistas de gestión."),
            ("Correo", "PHPMailer + Brevo", "Recuperación y notificaciones."),
            ("QR", "Librería PHP de QR", "Fichas, pedidos y enlace del repositorio."),
            ("Control de versiones", "Git + GitHub", "Repositorio y trabajo colaborativo."),
            ("Despliegue", "cPanel + Neon/Railway", "Aplicación web y base de datos."),
        ],
        [3.4, 4.8, 7.8],
        caption="Tecnologías de implementación",
        font_size=8.3,
    )

    b.h2("7.2 Estructura del repositorio")
    b.p("El repositorio se organiza por responsabilidades. `public` es la raíz web y contiene el router frontal; `app` agrupa configuración, núcleo, middleware, controladores, servicios, modelos y vistas; `database` contiene migraciones y seeders; `docs` reúne las especificaciones y el presente documento.")
    b.code("""Clinica/
├── public/
│   ├── index.php
│   ├── .htaccess
│   ├── assets/
│   └── uploads/
├── app/
│   ├── config/
│   ├── core/
│   ├── middlewares/
│   ├── controllers/
│   ├── services/
│   ├── models/
│   └── views/
├── database/
│   ├── migrations/
│   └── seeders/
├── docs/
│   ├── specs/
│   └── puds/
├── docker-compose.yml
└── .env.example""")

    b.h2("7.3 Convenciones de código")
    b.bullets([
        ("Nomenclatura: ", "`snake_case` en base de datos, `camelCase` en métodos y `PascalCase` en clases."),
        ("Consultas: ", "siempre preparadas con PDO; prohibida la concatenación de SQL."),
        ("Contraseñas: ", "`password_hash()` al crear y `password_verify()` al autenticar."),
        ("Salida: ", "escape con `htmlspecialchars()` mediante el helper `e()`."),
        ("CSRF: ", "token en formularios y peticiones que mutan estado."),
        ("Errores: ", "mensaje genérico al usuario y detalle en el log interno."),
        ("Capas: ", "el controlador no contiene SQL; el modelo no contiene HTML."),
        ("Transacciones: ", "las operaciones de cita y ficha se ejecutan en una unidad atómica."),
    ])

    b.h2("7.4 Componentes y servicios implementados")
    b.table(
        ["Componente", "Responsabilidad", "Artefactos"],
        [
            ("Router y Request", "Resolver rutas, métodos y parámetros.", "`app/core/Router.php`, `Request.php`"),
            ("Middleware", "Validar sesión, rol y token de kiosco.", "`AuthMiddleware`, `RoleMiddleware`, `KioskMiddleware`"),
            ("AuthService", "Login, bloqueo, recuperación y activación.", "`AuthController`, `UsuarioModel`, `MailService`"),
            ("DisponibilidadService", "Calcular slots y cupos por canal.", "`HorarioModel`, `BloqueoModel`, `CitaModel`"),
            ("CitaService", "Reservar, reprogramar y cancelar.", "`CitaController`, `CitaModel`, `FichaService`"),
            ("EstadoCitaService", "Aplicar transiciones y registrar historial.", "`EstadoCitaService`, `CitasEstadoModel`"),
            ("ReconsultaService", "Crear reconsultas por ausencia o seguimiento.", "`ReconsultaService`, `CitaModel`"),
            ("FichaService", "Emitir ficha, confirmar cita y generar QR.", "`FichaController`, `FichaModel`, `QrService`"),
            ("ConsultaService", "Registrar consulta y pedidos.", "`ConsultaController`, `ConsultaModel`, `PedidoModel`"),
            ("ReporteService", "Agregar métricas y dashboards.", "`ReporteController`, vistas de reportes"),
        ],
        [3.6, 7.4, 5.0],
        caption="Componentes principales de la implementación",
        font_size=8.0,
    )

    b.h2("7.5 Implementación de la base de datos")
    b.p("Las migraciones se ejecutan en orden y crean enums, tablas, índices, políticas RLS y triggers. Los códigos visibles se generan con secuencias por prefijo. La tabla `citas` incorpora un índice único parcial para impedir superposiciones y el servicio complementa la validación por rango cuando la duración no es fija.")
    b.code("""CREATE UNIQUE INDEX ux_cita_no_superpone
  ON citas (medico_id, fecha, hora_inicio)
  WHERE estado <> 'cancelada';

BEGIN;
SELECT ... FOR UPDATE;
-- verificar superposición y cupo del canal
INSERT INTO citas (...) VALUES (...);
COMMIT;""")
    b.p("La auditoría se implementa con la función `fn_auditar()` marcada como `SECURITY DEFINER`. La aplicación no escribe directamente en `auditoria`; el trigger captura la IP y los valores antes y después. Las políticas RLS se activan con `FORCE ROW LEVEL SECURITY` en las tablas sensibles.")

    b.h2("7.6 Seguridad implementada")
    b.table(
        ["Mecanismo", "Implementación", "Verificación"],
        [
            ("Sesión", "Cookie HttpOnly, Secure y SameSite=Lax.", "Inspección de cabeceras y prueba de logout."),
            ("Contraseñas", "Hash con `password_hash()` y costo por defecto.", "Login correcto e incorrecto."),
            ("Bloqueo", "Contador e indicador `bloqueado` tras tres fallos.", "Prueba de tres intentos."),
            ("Recuperación", "Token aleatorio de un solo uso y 30 minutos.", "Token válido, expirado y reutilizado."),
            ("CSRF", "Token por sesión en formularios y fetch.", "Envío sin token rechazado."),
            ("Roles", "Middleware y menú dinámico.", "Acceso cruzado entre roles rechazado."),
            ("RLS", "Contexto `app.usuario_id`, `app.rol`, `app.medico_id`.", "Consulta filtrada por médico y paciente."),
            ("Kiosco", "Token HMAC de 10 minutos.", "Token vencido reinicia el flujo."),
        ],
        [3.0, 8.0, 5.0],
        caption="Implementación de controles de seguridad",
        font_size=8.0,
    )

    b.h2("7.7 Integraciones")
    b.p("El correo transaccional se envía con PHPMailer a través de Brevo. Si el envío falla, el sistema registra el evento y marca el correo como pendiente sin interrumpir la operación. Los pedidos se comparten por un enlace profundo de WhatsApp con mensaje precargado y código de acceso. Los códigos QR se generan para la ficha y para el perfil profesional del médico.")
    b.table(
        ["Integración", "Mecanismo", "Alcance"],
        [
            ("Correo", "SMTP Brevo + PHPMailer", "Recuperación, confirmación y avisos."),
            ("WhatsApp", "Deep link `wa.me`", "Envío de recetas, documentos y órdenes."),
            ("QR", "Generación en servidor", "Fichas y perfil del médico."),
            ("Pago", "Formulario visual Visa/Mastercard", "Solo UI; no procesa tarjetas."),
            ("Cámara", "getUserMedia", "Verificación facial simulada en kiosco."),
        ],
        [3.2, 7.0, 5.8],
        caption="Integraciones del sistema",
    )

    b.h2("7.8 Despliegue y operación")
    b.p("El despliegue previsto utiliza un hosting cPanel para la aplicación PHP y una instancia PostgreSQL en Neon o Railway. Se requiere HTTPS, `pdo_pgsql` habilitado, variables de entorno para la cadena de conexión y credenciales SMTP. El kiosco se ejecuta en un iPad con acceso a `/kiosco` y reinicio automático por inactividad.")
    b.table(
        ["Actividad", "Responsable", "Criterio de salida"],
        [
            ("Configurar hosting y HTTPS", "Equipo de TI", "Dominio accesible por HTTPS."),
            ("Crear base de datos y roles", "DBA / TI", "Migraciones y RLS aplicadas."),
            ("Configurar variables de entorno", "Equipo de TI", "Conexión y SMTP verificados."),
            ("Desplegar código", "Equipo de TI", "Rutas y assets funcionando."),
            ("Cargar seeders", "Admin", "Catálogos y usuarios iniciales."),
            ("Probar kiosco", "Recepción", "Ficha emitida en iPad."),
            ("Capacitar al personal", "Dirección", "Usuarios entrenados."),
        ],
        [5.0, 3.6, 7.4],
        caption="Actividades de despliegue",
    )
    b.note("Riesgo crítico: si el hosting compartido no ofrece `pdo_pgsql`, la conexión a PostgreSQL fallará. Antes de la puesta en producción se debe verificar la extensión o migrar a un hosting que la soporte.", "Verificación previa")

    b.h2("7.9 Estado de avance")
    b.table(
        ["Módulo", "Estado", "Observación"],
        [
            ("Modelo de datos y RLS", "Diseñado", "DDL, enums, índices y políticas definidas."),
            ("Autenticación", "Diseñado", "Flujos de login, bloqueo y recuperación especificados."),
            ("Catálogos", "Diseñado", "Pacientes, médicos y especialidades."),
            ("Horarios y cupos", "Diseñado", "Motor de disponibilidad y cupos 50/50."),
            ("Citas y estados", "Diseñado", "Reserva transaccional, reprogramación y cancelación."),
            ("Kiosco y fichas", "Diseñado", "Flujo táctil y ficha con QR."),
            ("Historia clínica", "Diseñado", "Consulta, recetas, documentos y órdenes."),
            ("Pagos y reportes", "Diseñado", "Registro y métricas operativas."),
            ("Pruebas", "Planificado", "Casos y criterios definidos en el capítulo 8."),
        ],
        [4.2, 3.0, 8.8],
        caption="Estado de avance por módulo",
        font_size=8.3,
    )


def chapter8():
    b.h1("8. PRUEBAS DEL SISTEMA")
    b.h2("8.1 Estrategia de pruebas")
    b.p("La estrategia combina pruebas unitarias, de integración, funcionales, de seguridad y de concurrencia. Las pruebas unitarias validan servicios y reglas; las de integración verifican endpoints con base de datos; las funcionales recorren los flujos de negocio; las de seguridad comprueban autenticación, autorización y RLS; y las de concurrencia verifican que dos reservas simultáneas no generen citas superpuestas.")
    b.figure("pruebas_workflow.png", 15.0, "Flujo de trabajo de pruebas")

    b.h2("8.2 Niveles y técnicas")
    b.table(
        ["Nivel", "Objetivo", "Técnica"],
        [
            ("Unitario", "Validar servicios y cálculos.", "Casos de borde y aserciones."),
            ("Integración", "Validar endpoints y persistencia.", "Base de datos de prueba y transacciones."),
            ("Funcional", "Validar flujos completos.", "Partición de equivalencia y camino básico."),
            ("Seguridad", "Validar acceso y datos.", "Pruebas de rol, CSRF y RLS."),
            ("Concurrencia", "Validar reservas simultáneas.", "Peticiones paralelas y bloqueo."),
            ("Regresión", "Evitar rupturas.", "Suite automatizada y repetible."),
        ],
        [3.2, 6.2, 6.6],
        caption="Niveles de prueba",
    )

    b.h2("8.3 Casos de prueba")
    b.table(
        ["ID", "Caso", "Entrada / acción", "Resultado esperado", "RF"],
        [
            ("P01", "Login correcto", "Credenciales válidas.", "Acceso al panel del rol.", "RF01"),
            ("P02", "Bloqueo por intentos", "Tres contraseñas incorrectas.", "Cuenta bloqueada.", "RF01"),
            ("P03", "Acceso sin sesión", "Abrir URL interna.", "Redirección a login.", "RF01"),
            ("P04", "Desbloqueo administrativo", "Admin desbloquea usuario.", "Usuario puede ingresar.", "RF01"),
            ("P05", "CI duplicada", "Registrar paciente con CI existente.", "Rechazo controlado.", "RF02"),
            ("P06", "Franja inválida", "Hora fin menor que inicio.", "Rechazo de la franja.", "RF05"),
            ("P07", "Franjas solapadas", "Crear franja sobre otra activa.", "Rechazo.", "RF05"),
            ("P08", "Slots de 30 minutos", "Consultar disponibilidad.", "Bloques correctos.", "RF05"),
            ("P09", "Cupo web agotado", "Reservar sin cupo web.", "Sin slots web; kiosco disponible.", "RF05"),
            ("P10", "Reserva válida", "Slot libre y paciente elegible.", "Cita creada con código.", "RF06"),
            ("P11", "Cita superpuesta", "Reservar slot ocupado.", "Rechazo 409.", "RF07"),
            ("P12", "Fuera de horario", "Slot fuera de franja.", "Rechazo 422.", "RF07"),
            ("P13", "Fecha pasada", "Reservar fecha anterior.", "Rechazo.", "RF06"),
            ("P14", "Reprogramación válida", "Cambiar a slot libre.", "Cita actualizada.", "RF08"),
            ("P15", "Cancelación anticipada", "Cancelar dos días antes.", "Estado cancelada.", "RF09"),
            ("P16", "Cancelación el mismo día", "Cancelar el día de la cita.", "Rechazo por RX04.", "RF09"),
            ("P17", "Ficha en kiosco", "Paciente con CI y cupo kiosco.", "Ficha emitida y cita confirmada.", "RF10"),
            ("P18", "Transición inválida", "Cancelada a atendida.", "Rechazo.", "RF10"),
            ("P19", "Reconsulta por ausencia", "Cancelar original y crear nueva.", "Cita prioritaria creada.", "RF10"),
            ("P20", "Consulta médica", "Registrar diagnóstico y tratamiento.", "Consulta y cita atendida.", "RF12"),
            ("P21", "Pedido por WhatsApp", "Enviar receta con teléfono.", "URL wa.me y marca de envío.", "RF12"),
            ("P22", "Pago registrado", "Monto y método válidos.", "Pago almacenado.", "RF13"),
            ("P23", "Reporte por estado", "Filtrar por rango.", "Totales correctos.", "RF13"),
            ("P24", "Auditoría de catálogo", "Editar especialidad.", "Registro con antes/después.", "RNF09"),
            ("P25", "Concurrencia de reserva", "Dos reservas simultáneas.", "Solo una cita creada.", "RF07"),
            ("P26", "RLS de médico", "Médico consulta citas ajenas.", "Solo ve las propias.", "RNF03"),
        ],
        [1.4, 4.0, 5.4, 5.0, 1.8],
        caption="Casos de prueba funcionales y de seguridad",
        font_size=7.6,
        align_center_cols={0, 4},
    )

    b.h2("8.4 Matriz de cobertura")
    b.table(
        ["Requisito", "Casos de prueba", "Cobertura"],
        [
            ("RF01", "P01–P04", "Alta"),
            ("RF02", "P05", "Media"),
            ("RF03 / RF04", "P10, P17, P26", "Media"),
            ("RF05", "P06–P09, P17", "Alta"),
            ("RF06", "P10, P13, P25", "Alta"),
            ("RF07", "P11, P12, P25", "Alta"),
            ("RF08", "P14", "Media"),
            ("RF09", "P15, P16", "Alta"),
            ("RF10", "P17–P19", "Alta"),
            ("RF11 / RF12", "P20, P21", "Media"),
            ("RF13", "P22, P23", "Media"),
            ("RNF03 / RNF09", "P26, P24", "Alta"),
        ],
        [2.8, 7.2, 6.0],
        caption="Cobertura de requisitos por pruebas",
        align_center_cols={0, 2},
    )

    b.h2("8.5 Ejecución y evidencia")
    b.p("Cada caso de prueba se ejecuta sobre una base de datos limpia, con seeders de catálogos y usuarios de los seis roles. Los resultados se registran en una tabla de ejecución con fecha, responsable, resultado obtenido y estado. Las pruebas de concurrencia se ejecutan con dos sesiones simultáneas sobre el mismo slot; las de seguridad verifican que un rol no acceda a rutas ni filas de otro.")
    b.table(
        ["Tipo de evidencia", "Contenido"],
        [
            ("Registro de ejecución", "ID, fecha, responsable, resultado y observaciones."),
            ("Capturas de pantalla", "Flujos de reserva, kiosco, ficha y consulta."),
            ("Salida de base de datos", "Citas, fichas, historial de estados y auditoría."),
            ("Respuestas HTTP", "Códigos 200, 401, 403, 409 y 422."),
            ("Log de correo", "Envío o fallback a cola."),
            ("Reporte de defectos", "Severidad, estado y responsable."),
        ],
        [4.0, 12.0],
        caption="Evidencias de prueba",
    )

    b.h2("8.6 Defectos y acciones correctivas")
    b.table(
        ["Defecto potencial", "Severidad", "Acción"],
        [
            ("Cita superpuesta por concurrencia", "Crítica", "Bloqueo y índice único parcial."),
            ("Cupo negativo", "Alta", "Validación en servicio y CHECK en base de datos."),
            ("Ficha sin confirmar cita", "Alta", "Transacción única en FichaService."),
            ("Acceso a datos de otro médico", "Crítica", "RLS y pruebas de autorización."),
            ("Token de recuperación reutilizable", "Alta", "Invalidación al usarlo y expiración."),
            ("Reporte con rango inválido", "Media", "Validación `desde <= hasta`."),
        ],
        [6.0, 2.6, 7.4],
        caption="Defectos potenciales y acciones correctivas",
    )

    b.h2("8.7 Criterios de aceptación")
    b.bullets([
        "Todos los casos críticos (P01–P19, P25) deben finalizar con el resultado esperado.",
        "No debe existir ninguna cita superpuesta en las pruebas de concurrencia.",
        "Los usuarios solo acceden a las rutas y filas permitidas por su rol.",
        "La ficha debe confirmar la cita y habilitar la atención.",
        "La cancelación debe respetar el plazo y liberar el slot.",
        "La auditoría debe registrar los cambios de catálogos con IP y valores antes/después.",
    ])


def closing():
    b.h1("CONCLUSIONES")
    b.p("El proyecto demuestra que un sistema web de gestión de citas puede resolver los problemas operativos de la Clínica Vida Salud mediante una arquitectura sencilla, una base de datos relacional robusta y un conjunto claro de reglas de negocio. La separación entre requisitos, análisis, diseño, implementación y pruebas permite comprender y mantener la solución.")
    b.p("La validación de disponibilidad en el servidor, el uso de transacciones y el índice único parcial constituyen la defensa principal contra citas superpuestas. La distribución de cupos 50/50, la emisión de fichas y la reconsulta prioritaria responden a las necesidades reales de la clínica. La seguridad se refuerza con roles, RLS, CSRF, bloqueo de cuentas y auditoría.")
    b.p("El documento evidencia la trazabilidad entre los requisitos RF01–RF13, los casos de uso UC01–UC24, las clases de análisis, los componentes de diseño y los casos de prueba. Esta trazabilidad facilita la evaluación académica y sirve como base para la implementación y la puesta en producción.")

    b.h1("RECOMENDACIONES")
    b.numbered([
        "Verificar en el hosting la disponibilidad de `pdo_pgsql` antes del despliegue y documentar el plan alternativo.",
        "Implementar pruebas automatizadas de regresión para los flujos de cita, ficha y cancelación.",
        "Monitorear el rendimiento del motor de disponibilidad y agregar índices según el crecimiento de datos.",
        "Capacitar a recepción y médicos en el uso de la agenda, los estados y las reconsultas.",
        "Definir una política de respaldo y recuperación de la base de datos PostgreSQL.",
        "Revisar periódicamente la auditoría y los intentos de acceso fallidos.",
        "Evaluar en una siguiente fase la integración con WhatsApp Business API y una pasarela de pago real.",
        "Incorporar métricas de satisfacción y tiempos de atención para mejorar el servicio.",
    ])

    b.h1("BIBLIOGRAFÍA")
    refs = [
        "Jacobson, I., Booch, G. y Rumbaugh, J. (1999). The Unified Software Development Process. Addison-Wesley.",
        "Rumbaugh, J., Jacobson, I. y Booch, G. (2004). The Unified Modeling Language Reference Manual (2.ª ed.). Addison-Wesley.",
        "Larman, C. (2004). Applying UML and Patterns: An Introduction to Object-Oriented Analysis and Design and Iterative Development (3.ª ed.). Prentice Hall.",
        "Sommerville, I. (2016). Software Engineering (10.ª ed.). Pearson.",
        "Pressman, R. S. (2014). Software Engineering: A Practitioner's Approach (8.ª ed.). McGraw-Hill.",
        "Wiegers, K. y Beatty, J. (2013). Software Requirements (3.ª ed.). Microsoft Press.",
        "Fowler, M. (2002). Patterns of Enterprise Application Architecture. Addison-Wesley.",
        "Gamma, E., Helm, R., Johnson, R. y Vlissides, J. (1994). Design Patterns: Elements of Reusable Object-Oriented Software. Addison-Wesley.",
        "OWASP Foundation. (2021). OWASP Top 10:2021. https://owasp.org/Top10/",
        "PostgreSQL Global Development Group. (2024). PostgreSQL 16 Documentation. https://www.postgresql.org/docs/16/",
        "The PHP Group. (2024). PHP Manual. https://www.php.net/manual/",
        "Clínica Vida Salud. (2026). Especificaciones SSD del sistema de gestión de citas (docs/specs).",
    ]
    for ref in refs:
        p = b.doc.add_paragraph()
        p.paragraph_format.left_indent = Cm(0.8)
        p.paragraph_format.first_line_indent = Cm(-0.8)
        p.paragraph_format.space_after = Pt(6)
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        r = p.add_run(ref)
        _set_run_font(r, size=9.5, color=NAVY)


def annexes():
    b.h1("ANEXO A. GLOSARIO")
    b.table(
        ["Término", "Definición"],
        [
            ("Cita", "Reserva de atención entre un paciente y un médico en fecha y hora determinadas."),
            ("Cupo", "Unidad de disponibilidad de una franja para un canal (web o kiosco)."),
            ("Ficha", "Ticket de la cita con código, acceso y QR; habilita la atención."),
            ("Franja", "Rango horario semanal de atención de un médico."),
            ("Slot", "Bloque concreto de fecha y hora calculado para reservar."),
            ("Reconsulta", "Cita prioritaria por ausencia del médico o seguimiento."),
            ("Pedido", "Receta, documento u orden de estudio generado en la consulta."),
            ("RLS", "Row Level Security; aislamiento de filas por rol en PostgreSQL."),
            ("RUP", "Rational Unified Process; proceso iterativo e incremental."),
            ("UML", "Unified Modeling Language; lenguaje de modelado de software."),
        ],
        [3.4, 12.6],
        caption="Glosario de términos",
    )

    b.h1("ANEXO B. DICCIONARIO DE DATOS RESUMIDO")
    b.table(
        ["Tabla", "Campo", "Tipo", "Descripción"],
        [
            ("usuarios", "rol", "enum", "Rol del usuario en el sistema."),
            ("pacientes", "ci", "varchar(20)", "Identificador único del paciente."),
            ("medicos", "registro_profesional", "varchar(50)", "Registro único del médico."),
            ("especialidades", "sexo_permitido", "enum", "Restricción de sexo para la especialidad."),
            ("horarios", "cupos_web / cupos_kiosco", "smallint", "Cupos por canal en la franja."),
            ("citas", "estado", "enum", "Estado del ciclo de vida de la cita."),
            ("citas", "origen", "enum", "Canal de creación: web, kiosco o recepción."),
            ("fichas", "codigo_acceso", "varchar(4)", "Código de acceso de la ficha."),
            ("consultas", "signos_vitales", "jsonb", "PA, FC, temperatura, peso, SpO2 y talla."),
            ("pagos", "monto", "numeric(10,2)", "Importe registrado, mayor que cero."),
        ],
        [3.2, 4.0, 3.4, 5.4],
        caption="Diccionario de datos resumido",
        font_size=8.1,
    )

    b.h1("ANEXO C. MATRIZ DE CONSISTENCIA")
    b.table(
        ["Requisito", "Caso de uso", "Clase/servicio", "Prueba"],
        [
            ("RF01", "UC03, UC04, UC22", "AuthService, AuthController", "P01–P04"),
            ("RF02", "UC08", "PacienteModel, PacienteService", "P05"),
            ("RF03 / RF04", "UC02, UC20", "MedicoModel, EspecialidadModel", "P10, P17, P26"),
            ("RF05", "UC05, UC09, UC15, UC21", "DisponibilidadService", "P06–P09"),
            ("RF06 / RF07", "UC05, UC09, UC15", "CitaService, CitaModel", "P10–P13, P25"),
            ("RF08", "UC10", "CitaService", "P14"),
            ("RF09", "UC06, UC11", "CitaService", "P15, P16"),
            ("RF10", "UC12, UC13", "EstadoCitaService, ReconsultaService", "P17–P19"),
            ("RF11 / RF12", "UC07, UC14, UC16–UC18", "ConsultaService, PedidoModel", "P20, P21"),
            ("RF13", "UC19, UC23", "PagoModel, ReporteService", "P22, P23"),
            ("RNF09", "UC24", "fn_auditar(), AuditoriaModel", "P24"),
        ],
        [2.2, 5.0, 6.4, 2.4],
        caption="Matriz de consistencia requisito–caso de uso–clase–prueba",
        font_size=7.9,
    )

    b.h1("ANEXO D. INVENTARIO DE DIAGRAMAS")
    b.table(
        ["Archivo", "Diagrama", "Capítulo"],
        [
            ("rup_fases.png", "Fases RUP", "2"),
            ("rup_disciplinas.png", "Disciplinas RUP", "2"),
            ("02_actividad_negocio.png", "Actividad de negocio", "3"),
            ("01_casos_uso_publico.png", "Casos de uso públicos", "4"),
            ("01b_casos_uso_citas.png", "Casos de uso de citas", "4"),
            ("01c_casos_uso_atencion.png", "Casos de uso de atención", "4"),
            ("05_analisis_clases.png", "Clases de análisis", "5"),
            ("03_clases_dominio_core.png", "Dominio núcleo", "5"),
            ("04_clases_dominio_clinico.png", "Dominio clínico", "5"),
            ("08_sec_login.png", "Secuencia de login", "5"),
            ("09_sec_reserva.png", "Secuencia de reserva", "5"),
            ("10_sec_kiosco.png", "Secuencia de kiosco", "5"),
            ("11_sec_cancelar.png", "Secuencia de cancelación", "5"),
            ("12_sec_consulta.png", "Secuencia de consulta", "5"),
            ("13_estados_cita.png", "Estados de la cita", "5"),
            ("06_arquitectura_logica.png", "Arquitectura lógica", "6"),
            ("07_despliegue.png", "Despliegue", "6"),
            ("16_mapa_navegacion.png", "Navegación", "6"),
            ("14_er_nucleo.png", "ER núcleo", "6"),
            ("15_er_clinico.png", "ER clínico", "6"),
            ("pruebas_workflow.png", "Flujo de pruebas", "8"),
            ("qr_repositorio.png", "QR del repositorio", "Anexo E"),
        ],
        [7.0, 6.0, 3.0],
        caption="Inventario de figuras del documento",
        font_size=8.0,
        align_center_cols={2},
    )

    b.h1("ANEXO E. REPOSITORIO DEL PROYECTO")
    b.p("El código fuente y la documentación se encuentran en el repositorio público del grupo. El acceso puede realizarse mediante el siguiente enlace o escaneando el código QR.")
    p = b.doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(6)
    p.paragraph_format.space_after = Pt(4)
    p.add_run().add_picture(os.path.join(ASSETS, "qr_repositorio.png"), width=Cm(5.2))
    p = b.doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run("https://github.com/MattFrs12343/Cl-nica-Vida-Salud-Grupo-32")
    _set_run_font(r, size=10, bold=True, color=NAVY)
    b.caption("Figura", "Código QR de acceso al repositorio del proyecto")

    b.h1("ANEXO F. LISTA DE VERIFICACIÓN")
    b.table(
        ["Ítem", "Estado"],
        [
            ("Repositorio con código y documentación.", "Cumplido"),
            ("Diagramas UML en formato editable y PNG.", "Cumplido"),
            ("Especificación de requisitos RF01–RF13.", "Cumplido"),
            ("Catálogo de casos de uso UC01–UC24.", "Cumplido"),
            ("Modelo de datos y políticas RLS.", "Cumplido"),
            ("Diseño de arquitectura y despliegue.", "Cumplido"),
            ("Plan y casos de prueba.", "Cumplido"),
            ("Documento en formato Word y PDF.", "Pendiente de compilación"),
            ("Defensa preparada.", "Pendiente"),
        ],
        [12.0, 4.0],
        caption="Lista de verificación de entrega",
        align_center_cols={1},
    )
