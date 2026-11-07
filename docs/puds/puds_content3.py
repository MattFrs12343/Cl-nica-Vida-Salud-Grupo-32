# -*- coding: utf-8 -*-
import os
from docx.shared import Cm, Pt
from docx.enum.text import WD_ALIGN_PARAGRAPH
from puds_lib import b, ASSETS, _set_run_font, NAVY


def chapter7():
    b.h1("7. FT. IMPLEMENTACIÓN")
    b.h2("7.1 Herramientas de desarrollo de la aplicación web")
    b.p("La implementación utiliza PHP 8.1+ con arquitectura MVC manual, PostgreSQL 16 como base de datos, HTML5, CSS3 y JavaScript para la interfaz. Los paneles internos se apoyan en la plantilla Gentelella; la landing y el kiosco tienen un diseño propio, responsive y táctil. El correo se envía con PHPMailer y Brevo, y los códigos QR se generan en el servidor.")
    b.p("El equipo de desarrollo complementa el stack con un editor de código, contenedores para reproducir el entorno local, control de versiones y una herramienta de modelado UML. Estas herramientas no forman parte de la aplicación en producción, pero sostienen la construcción, la revisión y la documentación del sistema.")
    b.table(
        ["Componente", "Tecnología", "Uso"],
        [
            ("Editor de código", "Visual Studio Code", "Edición y depuración de PHP, HTML, CSS y JavaScript."),
            ("Lenguaje backend", "PHP 8.1+", "Controladores, servicios, modelos y vistas."),
            ("Base de datos", "PostgreSQL 16", "Persistencia, enums, JSONB, RLS e índices."),
            ("Acceso a datos", "PDO", "Consultas preparadas y transacciones."),
            ("Frontend", "HTML5, CSS3, JavaScript", "Landing, paneles, kiosco y chatbot de síntomas Dr. Alex."),
            ("Plantilla administrativa", "Gentelella", "Dashboard y vistas de gestión."),
            ("Correo", "PHPMailer + Brevo", "Recuperación y notificaciones."),
            ("QR", "Librería PHP de QR", "Fichas, pedidos y enlace del repositorio."),
            ("Entorno local", "Docker Compose", "Apache, PHP y PostgreSQL reproducibles."),
            ("Modelado", "PlantUML", "Diagramas UML del documento."),
            ("Control de versiones", "Git + GitHub", "Repositorio y trabajo colaborativo."),
            ("Despliegue", "cPanel + Neon/Railway", "Aplicación web y base de datos."),
        ],
        [3.4, 4.8, 7.8],
        caption="Herramientas y tecnologías de desarrollo de la aplicación web",
        font_size=8.1,
    )

    b.h3("7.1.1 Estructura del repositorio")
    b.p("El repositorio se organiza por responsabilidades. `public` es la raíz web y contiene el router frontal; `app` agrupa configuración, núcleo, middleware, controladores, servicios, modelos y vistas; `database` contiene migraciones y seeders; `docs` reúne las especificaciones y el presente documento.")
    b.code("""Clinica/
├── public/
│   ├── index.php
│   ├── .htaccess
│   ├── assets/
│   │   └── kiosco/
│   │       ├── css/kiosco.css
│   │       └── js/
│   │           ├── kiosco-sintomas.js
│   │           └── kiosco-chat-buddy.js
│   └── uploads/
├── app/
│   ├── config/
│   ├── core/
│   ├── middlewares/
│   ├── controllers/
│   ├── services/
│   ├── models/
│   └── views/
│       ├── kiosco/index.php
│       └── layouts/kiosco.php
├── database/
│   ├── migrations/
│   └── seeders/
├── docs/
│   ├── specs/
│   └── puds/
├── docker-compose.yml
└── .env.example""")

    b.h3("7.1.2 Implementación del chatbot de síntomas Dr. Alex (UC25)")
    b.p("El chatbot \"Dr. Alex\" se implementa como un módulo de frontend del kiosco. Se activa automáticamente en la pantalla de bienvenida y guía al paciente mediante un árbol de decisión fijo, sin IA generativa ni llamadas a servicios externos. Su objetivo es orientar la selección de especialidad y, opcionalmente, conducir al paciente al flujo existente de agendamiento.")
    b.table(
        ["Componente", "Archivo", "Responsabilidad"],
        [
            ("Árbol de síntomas", "`public/assets/kiosco/js/kiosco-sintomas.js`", "Captura texto libre, detecta palabras clave, clasifica en 10 categorías generales, formula al menos 3 preguntas de confirmación de opción múltiple, gestiona ambigüedad/fallback y recomienda especialidad."),
            ("Animación Dr. Alex", "`public/assets/kiosco/js/kiosco-chat-buddy.js`", "Presenta al personaje en la apertura del chat y lo mantiene acoplado al modal durante la conversación; comportamiento visual sin lógica de negocio."),
            ("Estilos del chat", "`public/assets/kiosco/css/kiosco.css`", "Define la apariencia del modal y componentes `.chat-*` del asistente."),
            ("Markup del modal", "`app/views/kiosco/index.php`", "Contiene la estructura HTML del chatbot en la bienvenida del kiosco."),
            ("Carga de scripts", "`app/views/layouts/kiosco.php`", "Incluye los scripts y estilos necesarios para el kiosco y el chatbot."),
        ],
        [3.4, 7.0, 5.6],
        caption="Componentes implementados del chatbot Dr. Alex",
        font_size=8.0,
    )
    b.bullets([
        ("Clasificación inicial: ", "el paciente describe síntomas en texto libre; el motor busca palabras clave y ubica el caso en una de 10 categorías generales (por ejemplo, dolor de cabeza, estómago, piel, garganta, niño o embarazo)."),
        ("Confirmación mínima: ", "antes de recomendar, el chatbot formula siempre al menos 3 preguntas de confirmación de opción múltiple para reducir errores por coincidencia aislada de palabras."),
        ("Manejo de ambigüedad: ", "si la descripción no se comprende, solicita una nueva descripción una vez; si persiste, muestra una lista de categorías generales para que el paciente elija y el flujo no quede bloqueado."),
        ("Control de entrada: ", "los números se bloquean fuera del paso de cédula, porque los demás pasos corresponden a la descripción de síntomas u opciones."),
        ("Handoff al agendamiento: ", "al aceptar la recomendación, el chat solicita la cédula, llama a `/api/kiosco/identificar` y continúa con el flujo existente de agendar cita con la especialidad preseleccionada. No duplica pantallas ni agrega lógica PHP nueva en el servidor."),
    ])
    b.note("Toda la lógica del chatbot reside en el frontend del kiosco. El backend solo participa mediante la reutilización de la API de identificación de pacientes ya existente, por lo que no se requieren migraciones, tablas ni servicios nuevos para UC25.", "Impacto en backend")

    b.h3("7.1.3 Convenciones de código")
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

    b.h2("7.2 Implementación de la arquitectura del sistema")
    b.p("La arquitectura del sistema se materializa en una aplicación web monolítica modular cuyo punto de entrada es `public/index.php`. Ese componente, identificado como `main`, carga las variables de entorno, registra las rutas de `app/routes`, aplica los middlewares de sesión, rol y CSRF, y despacha la petición hacia el controlador correspondiente. A partir de allí la solicitud recorre controlador, servicio y modelo antes de llegar a PostgreSQL.")
    b.p("Alrededor del núcleo se organizan cuatro paquetes. P1 concentra la gestión de consultas médicas; P2 la gestión de citas y agenda; P3 la administración, seguridad y reportes; y P4 los servicios compartidos de configuración, correo, generación de QR y auditoría. Los paquetes P1, P2 y P3 dependen del núcleo y de P4, pero no se invocan directamente entre sí: la comunicación se realiza mediante servicios y modelos, lo que reduce el acoplamiento y facilita las pruebas.")
    b.p("Los archivos de configuración y las librerías externas se ubican en los extremos del diagrama. La base de datos se representa con las tablas principales y los reportes con las salidas PDF y XLSX que consume la dirección. La figura siguiente muestra la vista de componentes del sistema completo.")
    b.figure("43_impl_arquitectura_sistema.png", 16.2,
             "Diagrama de componentes de la arquitectura del sistema",
             max_height_cm=20.5)
    b.p("La tabla siguiente describe la responsabilidad de cada componente y el artefacto que lo implementa.")
    b.table(
        ["Componente", "Tipo", "Implementación", "Responsabilidad"],
        [
            ("Sistema Web de Gestión de Consultas Médicas", "«main»",
             "`public/index.php`, `app/core/Router.php`",
             "Punto de entrada, carga de configuración, enrutamiento y despacho."),
            ("P1 Gestión de Consultas Médicas", "Subsistema",
             "`ConsultaApiController`, `ConsultaService`, `ConsultaModel`",
             "Registro de consultas, historia clínica, recetas, documentos y órdenes."),
            ("P2 Gestión de Citas y Agenda", "Subsistema",
             "`CitaApiController`, `CitaService`, `DisponibilidadService`",
             "Disponibilidad, reserva web, programación, reprogramación, kiosco y fichas."),
            ("P3 Administración y Seguridad", "Subsistema",
             "`AuthController`, `AdminApiController`, `ReporteApiController`",
             "Autenticación, usuarios, catálogos, pagos, reportes y auditoría."),
            ("P4 Servicios Compartidos", "Transversal",
             "`MailService`, `KioskTokenService`, `AuditoriaModel`, `DB`",
             "Correo, tokens de kiosco, auditoría, acceso a datos y utilidades comunes."),
            ("Configuración", "«file»",
             "`.env`, `app/config/*.php`",
             "Parámetros de conexión, correo, sesión y seguridad."),
            ("Librerías", "«library»",
             "PDO, PHPMailer, generador QR, Gentelella/Bootstrap",
             "Persistencia, correo, códigos QR y componentes de interfaz."),
            ("Base de datos", "«database»",
             "PostgreSQL 16, migraciones 001–012",
             "Tablas, enums, índices, políticas RLS y triggers."),
            ("Reportes", "«package»",
             "`ReporteModel`, vistas imprimibles y exportadores CSV/PDF",
             "Indicadores y salidas PDF/XLSX para la dirección."),
        ],
        [3.7, 2.1, 5.6, 5.6],
        caption="Componentes de la arquitectura del sistema",
        font_size=7.9,
    )

    b.h3("7.2.1 Componentes y servicios implementados")
    b.table(
        ["Componente", "Responsabilidad", "Artefactos"],
        [
            ("Router y Request", "Resolver rutas, métodos y parámetros.", "`app/core/Router.php`, `Request.php`"),
            ("Middleware", "Validar sesión, rol y token de kiosco.", "`AuthMiddleware`, `RoleMiddleware`, `KioskMiddleware`"),
            ("AuthService", "Login, bloqueo, recuperación y activación.", "`AuthController`, `UsuarioModel`, `MailService`"),
            ("DisponibilidadService", "Calcular slots y cupos por canal.", "`HorarioModel`, `BloqueoModel`, `CitaModel`"),
            ("CitaService", "Reservar, reprogramar y cancelar.", "`CitaApiController`, `CitaModel`, `FichaService`"),
            ("Cambio de estado", "Aplicar transiciones y registrar historial.", "`CitaService`, `CitaModel`, tabla `citas_estado_historial`"),
            ("ReconsultaService", "Crear reconsultas por ausencia o seguimiento.", "`ReconsultaService`, `CitaModel`"),
            ("FichaService", "Emitir ficha, confirmar cita y generar QR.", "`FichaApiController`, `FichaModel`, `FichaService`"),
            ("ConsultaService", "Registrar consulta y pedidos.", "`ConsultaApiController`, `ConsultaModel`, `PedidoService`"),
            ("ReporteModel", "Agregar métricas y dashboards.", "`ReporteApiController`, `ReporteModel`, vistas de reportes"),
        ],
        [3.6, 7.4, 5.0],
        caption="Componentes principales de la implementación",
        font_size=8.0,
    )

    b.h3("7.2.2 Implementación de la base de datos")
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

    b.h3("7.2.3 Seguridad implementada")
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

    b.h3("7.2.4 Integraciones")
    b.p("El correo transaccional se envía con PHPMailer a través de Brevo. Si el envío falla, el sistema registra el evento y marca el correo como pendiente sin interrumpir la operación. Los pedidos se comparten por un enlace profundo de WhatsApp con mensaje precargado y código de acceso. Los códigos QR se generan para la ficha y para el perfil profesional del médico.")
    b.table(
        ["Integración", "Mecanismo", "Alcance"],
        [
            ("Correo", "SMTP Brevo + PHPMailer", "Recuperación, confirmación y avisos."),
            ("WhatsApp", "Deep link `wa.me`", "Envío de recetas, documentos y órdenes."),
            ("QR", "Generación en servidor", "Fichas y perfil del médico."),
            ("Pago", "Formulario visual Visa/Mastercard", "Solo UI; no procesa tarjetas."),
        ],
        [3.2, 7.0, 5.8],
        caption="Integraciones del sistema",
    )

    b.h3("7.2.5 Despliegue y operación")
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

    b.h2("7.3 Implementación de la arquitectura de los subsistemas")
    b.p("Los subsistemas se descomponen en tres niveles de componentes: los formularios y reportes de interfaz, las clases de negocio que aplican las reglas y las tablas que persisten el estado. La figura siguiente conserva la notación del diagrama de componentes y muestra, para cada subsistema, qué formulario invoca a qué clase y qué tabla alimenta.")
    b.figure("44_impl_arquitectura_subsistemas.png", 15.6,
             "Diagrama de componentes de los subsistemas P1, P2 y P3",
             max_height_cm=21.0)
    b.p("En P1, el formulario de registro abre la consulta; la consulta médica toma la cita confirmada, registra signos vitales, diagnóstico y tratamiento; y la historia clínica consolida consultas, recetas, documentos, órdenes y resultados. GestionFicha confirma la atención y ReporteConsulta produce la salida imprimible. Las tablas T_Ficha, T_Consulta, T_Paciente y T_Historia soportan el flujo; esta última agrupa las relaciones clínicas del paciente.")
    b.p("En P2, FReservaWeb y FKiosco comparten DisponibilidadService y CitaService; FProgramarCita y FReprogramarCita usan la misma validación transaccional. ReconsultaService crea citas prioritarias y FichaService emite la ficha con QR. En la bienvenida del kiosco, el chatbot Dr. Alex (UC25) orienta por síntomas con un árbol de decisión frontend y, si el paciente acepta, reutiliza `/api/kiosco/identificar` para continuar con el flujo existente de agendamiento sin lógica PHP adicional. Las tablas T_Cita, T_Horario, T_Bloqueo y T_Ficha concentran el estado de la agenda y de la atención.")
    b.p("En P3, FLogin pasa por AuthService; FUsuarios, FMedicos y FReportes operan sobre UsuarioModel, MedicoModel y ReporteModel. Las tablas T_Usuario, T_Medico, T_Especialidad y T_Auditoria sostienen la seguridad y la trazabilidad. La tabla siguiente resume la correspondencia entre subsistemas, componentes de interfaz, clases, tablas y casos de uso.")
    b.table(
        ["Subsistema", "Interfaz", "Negocio", "Datos", "Casos de uso"],
        [
            ("P1 Gestión de Consultas Médicas",
             "`FRegistroConsulta`, `FConsultaMedica`, `FHistoriaClinica`, `RHistoriaClinica`",
             "`GestionFicha`, `GestionConsulta`, `GestionHistoria`, `ReporteConsulta`, `ConsultaService`",
             "`fichas`, `consultas`, `pacientes`, `recetas`, `documentos`, `ordenes`, `resultados`",
             "UC15–UC18"),
            ("P2 Gestión de Citas y Agenda",
             "`FReservaWeb`, `FProgramarCita`, `FReprogramarCita`, `FKiosco`, `Chat Dr. Alex`",
             "`DisponibilidadService`, `CitaService`, `ReconsultaService`, `FichaService`, `ReservaWebService`, árbol de síntomas JS",
             "`citas`, `horarios`, `bloqueos`, `fichas`, `citas_estado_historial` (UC25 sin persistencia nueva)",
             "UC05–UC15, UC25"),
            ("P3 Administración, Seguridad y Reportes",
             "`FLogin`, `FUsuarios`, `FMedicos`, `FReportes`",
             "`AuthService`, `UsuarioModel`, `MedicoModel`, `ReporteModel`, `PagoModel`, `AuditoriaModel`",
             "`usuarios`, `medicos`, `especialidades`, `pagos`, `auditoria`",
             "UC04, UC19–UC24"),
        ],
        [3.2, 4.2, 4.3, 3.5, 1.8],
        caption="Subsistemas y componentes implementados",
        font_size=7.7,
    )
    b.note("En el diagrama, el prefijo T_ identifica el componente de datos y en la base de datos corresponde al nombre físico en plural. Las clases Gestion* agrupan las operaciones de ConsultaService, CitaService y FichaService; las dependencias discontinuas no implican acceso directo entre subsistemas, porque P1, P2 y P3 comparten P4 y la capa de datos.", "Trazabilidad de componentes")

    b.h3("7.3.1 Estado de avance")
    b.table(
        ["Módulo", "Estado", "Observación"],
        [
            ("Modelo de datos y RLS", "Diseñado", "DDL, enums, índices y políticas definidas."),
            ("Autenticación", "Diseñado", "Flujos de login, bloqueo y recuperación especificados."),
            ("Catálogos", "Diseñado", "Pacientes, médicos y especialidades."),
            ("Horarios y cupos", "Diseñado", "Motor de disponibilidad y cupos 50/50."),
            ("Citas y estados", "Diseñado", "Reserva transaccional, reprogramación y cancelación."),
            ("Kiosco y fichas", "Diseñado", "Flujo táctil, ficha con QR y chatbot Dr. Alex de orientación por síntomas."),
            ("Historia clínica", "Diseñado", "Consulta, recetas, documentos y órdenes."),
            ("Pagos y reportes", "Diseñado", "Registro y métricas operativas."),
            ("Pruebas", "Planificado", "Casos y criterios definidos en el capítulo 8."),
        ],
        [4.2, 3.0, 8.8],
        caption="Estado de avance por módulo",
        font_size=8.3,
    )


def chapter8():
    b.h1("8. FT. PRUEBAS")
    b.h2("8.1 Enfoque de pruebas de caja negra")
    b.p("Las pruebas se abordan con la técnica de caja negra: el evaluador desconoce la implementación interna y verifica el comportamiento observable del sistema a partir de los casos de uso y de las reglas de negocio. Se priorizan los casos de uso más relevantes —los clasificados como P1 Crítica en la sección 4.2.1— y se agrupan por paquete de análisis para asegurar que cada paquete tenga cobertura funcional. Para cada caso se documentan la interfaz de usuario desde la que se ejecuta la acción, los datos de entrada que se ingresan en la web, las condiciones que deben cumplirse y los datos de salida que se esperan. El procedimiento de uso se detalla, paso a paso, para la funcionalidad más relevante del proyecto en la sección 8.2.1.")
    b.p("Los datos de prueba son ficticios y se cargan con seeders sobre una base de datos limpia. Se utilizan los seis roles del sistema y un conjunto reducido de pacientes, médicos, especialidades y franjas que cubre los casos válidos, los valores límite y los escenarios de rechazo. Cada ejecución se registra con fecha, responsable, resultado obtenido y evidencia asociada.")
    b.table(
        ["Paquete", "Casos de uso", "Casos de prueba", "Interfaces principales", "Criterio de selección"],
        [
            ("PK-01 Acceso público y reserva web", "UC01–UC07",
             "P01, P02, P09–P13, P15, P16, P25",
             "Landing, login, reserva web y mis citas.",
             "Autenticación, disponibilidad, reserva, cancelación y concurrencia."),
            ("PK-02 Citas y agenda", "UC08–UC14",
             "P05, P14, P18, P19, P27–P29",
             "Panel de recepción, agenda, reprogramación e historial.",
             "Pacientes, programación, reprogramación, estados y reconsultas."),
            ("PK-03 Atención y administración", "UC15–UC25",
             "P06–P08, P17, P20–P24, P30, P31, P36–P40",
             "Kiosco, chatbot Dr. Alex, panel médico, pagos, reportes y catálogos.",
             "Ficha, orientación por síntomas, consulta, pedidos, pagos, reportes y administración."),
            ("PK-04 Servicios compartidos", "Transversal",
             "P03, P04, P26, P32–P35",
             "Login, middleware, correo, QR y auditoría.",
             "Seguridad, RLS, correo, QR y tokens de kiosco."),
        ],
        [3.4, 2.6, 3.6, 3.2, 3.2],
        caption="Alcance de las pruebas de caja negra por paquete",
        font_size=7.8,
        align_center_cols={1},
    )
    b.figure("pruebas_workflow.png", 15.0, "Flujo de trabajo de pruebas")

    b.h3("8.1.1 Técnicas de caja negra aplicadas")
    b.p("Las técnicas se combinan para derivar casos representativos sin revisar el código. La partición de equivalencia reduce el universo de entradas; los valores límite concentran los errores en los extremos; la tabla de decisión cubre combinaciones de condiciones; la transición de estados verifica el ciclo de vida de la cita; y las pruebas de concurrencia y seguridad comprueban los riesgos críticos del negocio.")
    b.table(
        ["Técnica", "Aplicación", "Ejemplos en el sistema"],
        [
            ("Partición de equivalencia", "Divide las entradas en clases válidas e inválidas y elige un representante de cada clase.",
             "Login válido e inválido; CI existente y nueva; fecha futura y pasada; monto mayor y menor que cero."),
            ("Valores límite", "Prueba los extremos de cada rango o umbral.",
             "Cancelación a dos días; slots de 08:00 y 11:30; cupo web 0 y 1; token de kiosco a los 10 minutos."),
            ("Tabla de decisión", "Combina condiciones para obtener la salida esperada.",
             "Elegibilidad por sexo y edad; cupo web/kiosco; estado de la cita; disponibilidad del slot."),
            ("Transición de estados", "Verifica que solo se ejecuten las transiciones permitidas.",
             "Programada → confirmada → atendida; cancelada no pasa a atendida; atendida no se reprograma."),
            ("Concurrencia", "Ejecuta solicitudes simultáneas sobre el mismo recurso.",
             "Dos pacientes reservan el mismo slot; solo una cita se registra."),
            ("Seguridad", "Comprueba autenticación, autorización, CSRF y aislamiento de filas.",
             "Acceso sin sesión; rol cruzado; RLS de médico; token CSRF inválido; token de kiosco vencido."),
        ],
        [3.2, 6.0, 6.8],
        caption="Técnicas de caja negra aplicadas al diseño de casos",
        font_size=7.8,
    )

    b.h2("8.2 Pruebas del paquete PK-01 — Acceso público y reserva web")
    b.p("El paquete PK-01 concentra el acceso público y la reserva web. Se prueban el login, el bloqueo por intentos, la disponibilidad, la reserva, la cancelación y la concurrencia. La tabla siguiente define, para cada caso, la interfaz de usuario, los datos de entrada, las condiciones y los datos de salida esperados.")
    b.table(
        ["Caso de prueba", "Interfaz de usuario", "Datos de entrada", "Condiciones", "Datos de salida"],
        [
            ("P01 · Login correcto (UC04)",
             "FLogin — `/login`: usuario o CI, contraseña y botón Ingresar.",
             "Usuario `ana.torres`; contraseña `Medico2026*`.",
             "Cuenta activa y no bloqueada; credenciales correctas; sesión previa cerrada.",
             "Redirección a `/medico/agenda`; sesión iniciada; nombre y rol visibles; último acceso actualizado."),
            ("P02 · Bloqueo por intentos (UC04)",
             "FLogin — mismo formulario de acceso.",
             "Usuario `ana.torres`; contraseña incorrecta `xxxxx`, repetida tres veces.",
             "Dos intentos fallidos previos; cuenta activa.",
             "Mensaje «Cuenta bloqueada»; indicador `bloqueado = true`; sin sesión; se requiere desbloqueo administrativo."),
            ("P09 · Cupo web agotado (UC05)",
             "FReservaWeb — paso 3, calendario de horarios disponibles.",
             "Especialidad `Medicina general`; médico `Dra. Ana Torres`; fecha `15/11/2026`; cupo web `0`; cupo kiosco `2`.",
             "Paciente autenticado; cupo web agotado; cupo de kiosco disponible.",
             "No se muestran slots web; mensaje «Sin cupo web, use el kiosco»; se ofrecen los slots de kiosco."),
            ("P10 · Reserva válida (UC05)",
             "FReservaWeb — stepper de cuatro pasos: especialidad, médico, fecha y hora, confirmación.",
             "Especialidad `Medicina general`; médico `Dra. Ana Torres`; fecha `15/11/2026`; slot `08:30`; motivo `Consulta general`.",
             "Paciente autenticado; médico activo; slot libre; cupo web mayor que cero; fecha futura; sin superposición.",
             "Cita `C-2026-000123` en estado `programada`, origen `web`; pantalla de confirmación y correo; slot bloqueado."),
            ("P11 · Cita superpuesta (UC05)",
             "FReservaWeb — confirmación y endpoint `POST /api/citas`.",
             "Mismo médico, fecha `15/11/2026` y slot `08:30` ya ocupado por otro paciente.",
             "Slot tomado; transacción con bloqueo; índice único parcial activo.",
             "HTTP 409; mensaje «El horario ya no está disponible»; no se crea una segunda cita; se sugieren otros slots."),
            ("P12 · Fuera de horario (UC05)",
             "FReservaWeb — calendario y validación del servidor.",
             "Médico `Dra. Ana Torres`; fecha `15/11/2026`; slot `13:00`.",
             "Franja del día `08:00–12:00`; sin bloqueo especial.",
             "HTTP 422; mensaje «Horario fuera de la franja»; no se crea la cita."),
            ("P13 · Fecha pasada (UC05)",
             "FReservaWeb — selector de fecha.",
             "Fecha `01/10/2026`; slot `08:30`.",
             "Fecha actual `02/11/2026`; la fecha solicitada es anterior al día actual.",
             "Fecha deshabilitada o HTTP 422; mensaje «No se puede reservar en una fecha pasada»; sin cita."),
            ("P15 · Cancelación anticipada (UC06)",
             "FMisCitas — lista de citas del paciente, acción Cancelar.",
             "Cita `C-2026-000123`; motivo `Viaje`; confirmación de la acción.",
             "Cita `programada` o `confirmada`; fecha `15/11/2026`; cancelación con dos días o más de anticipación.",
             "Estado `cancelada`; slot liberado; motivo registrado; correo de confirmación."),
            ("P16 · Cancelación el mismo día (UC06)",
             "FMisCitas — acción Cancelar sobre una cita del día.",
             "Cita del `02/11/2026`; motivo `Salud`.",
             "Cancelación con menos de dos días de anticipación (regla RX04).",
             "Rechazo; mensaje «No se puede cancelar el mismo día»; cita sin cambios; se ofrece reprogramar."),
            ("P25 · Concurrencia de reserva (UC05)",
             "Dos sesiones web simultáneas y endpoint `POST /api/citas`.",
             "Dos pacientes reservan `Dra. Ana Torres`, `15/11/2026`, `08:30`, al mismo tiempo.",
             "Un solo slot; `SELECT ... FOR UPDATE`; índice único parcial; validación transaccional.",
             "Una respuesta 201 con la cita y otra 409; una sola cita en la base de datos; sin superposición."),
        ],
        [2.7, 3.2, 3.4, 3.1, 3.6],
        caption="Pruebas de caja negra del paquete PK-01",
        font_size=7.2,
    )

    b.h3("8.2.1 Procedimiento de uso: reserva de cita web (P10)")
    b.p("El procedimiento describe cómo se usa la aplicación para la funcionalidad más relevante del proyecto: la reserva de una cita por el paciente. Se ejecuta con el paciente de prueba `María Quispe` (CI `7123456`), la médico `Dra. Ana Torres` y la especialidad `Medicina general`. El flujo se apoya en el prototipo de reserva web presentado en la sección 4.4.1 y en el stepper de cuatro pasos implementado en la aplicación.")
    b.table(
        ["Paso", "Pantalla o interfaz", "Acción del usuario", "Datos de entrada", "Salida esperada"],
        [
            ("1", "Landing pública `/`", "Ingresar a la landing y pulsar «Reservar cita».",
             "Sesión de paciente iniciada.", "Redirección a `/reserva`; stepper en el paso 1."),
            ("2", "Reserva, paso 1: especialidad", "Seleccionar la especialidad.",
             "`Medicina general`.", "Lista de médicos activos de la especialidad."),
            ("3", "Reserva, paso 2: médico", "Seleccionar la médico.",
             "`Dra. Ana Torres`.", "Calendario y horarios disponibles con cupos web y kiosco."),
            ("4", "Reserva, paso 3: fecha y hora", "Elegir la fecha y el slot.",
             "`15/11/2026`, `08:30`.", "Slot seleccionado; resumen de fecha, hora y médico."),
            ("5", "Reserva, paso 4: motivo y confirmación", "Escribir el motivo, revisar el resumen y confirmar.",
             "`Consulta general`; aceptación de condiciones.", "Revalidación en el servidor y creación transaccional de la cita."),
            ("6", "Confirmación de la reserva", "Verificar la pantalla de resultado.",
             "—", "Código `C-2026-000123`; estado `programada`; fecha, hora y médico; correo enviado."),
            ("7", "Mis citas `/mis-citas`", "Abrir «Mis citas» y localizar la cita.",
             "Cita `C-2026-000123`.", "Cita visible con estado `programada`; opción Cancelar habilitada (dos días o más)."),
            ("8", "Agenda de la médico", "Verificar que el slot quedó ocupado.",
             "`Dra. Ana Torres`, `15/11/2026`, `08:30`.", "Slot marcado como ocupado; no disponible para otra reserva."),
        ],
        [1.2, 3.0, 3.6, 3.8, 4.4],
        caption="Procedimiento de prueba de la reserva web",
        font_size=7.6,
        align_center_cols={0},
    )
    b.note("Si otro paciente confirma el mismo slot antes, el servidor responde 409, muestra el mensaje y refresca los horarios; no se crea una segunda cita (P11 y P25). La misma secuencia sirve de plantilla para las pruebas de los demás paquetes: cambia la interfaz, los datos de entrada y las condiciones, pero se conserva la verificación de la salida esperada.", "Flujo alternativo")

    b.h2("8.3 Pruebas del paquete PK-02 — Citas y agenda")
    b.p("El paquete PK-02 cubre la operación de recepción: pacientes, programación, reprogramación, control de asistencia, reconsultas y consulta de agenda e historial. Las pruebas verifican que la agenda conserve la integridad y que los estados solo cambien por transiciones permitidas.")
    b.table(
        ["Caso de prueba", "Interfaz de usuario", "Datos de entrada", "Condiciones", "Datos de salida"],
        [
            ("P05 · CI duplicada (UC08)",
             "FPacientes — alta de paciente.",
             "CI `7123456`; nombres `María`; apellidos `Quispe`; fecha de nacimiento `12/04/1988`; teléfono `70012345`; correo `maria.quispe@example.com`.",
             "Recepcionista autenticado; la CI ya existe; la CI es identificador único.",
             "Mensaje «La CI ya está registrada»; no se inserta el paciente; el formulario conserva los datos."),
            ("P14 · Reprogramación válida (UC10)",
             "FReprogramarCita — agenda de recepción, acción Reprogramar.",
             "Cita `C-2026-000123`; nueva fecha `17/11/2026`; slot `10:00`; motivo `Cambio de horario`.",
             "Cita `programada` o `confirmada`; nuevo slot libre; dentro de la franja de atención.",
             "Cita actualizada; slot anterior liberado; historial de estados; notificación al paciente."),
            ("P18 · Transición inválida (UC12)",
             "FAgendaRecepcion — detalle de la cita.",
             "Cita `C-2026-000123` en estado `cancelada`; acción «Marcar atendida».",
             "Máquina de estados: una cita cancelada no pasa a atendida.",
             "Rechazo «Transición no permitida»; estado sin cambios; evento registrado en auditoría."),
            ("P19 · Reconsulta por ausencia (UC13)",
             "FReconsulta — panel de recepción o médico.",
             "Cita original `C-2026-000123`; motivo `Inasistencia`; nueva fecha `18/11/2026`; slot `11:00`.",
             "Cita original marcada como `no asistió`; paciente elegible; slot libre.",
             "Nueva cita prioritaria `C-2026-000124`; vínculo con la cita original; estado `programada`."),
            ("P27 · Programar cita en recepción (UC09)",
             "FProgramarCita — agenda de recepción.",
             "Paciente `7123456`; especialidad `Medicina general`; médico `Dra. Ana Torres`; fecha `16/11/2026`; slot `09:30`; motivo `Control`.",
             "Recepcionista autenticado; paciente y médico activos; slot libre; cupo de recepción disponible.",
             "Cita `C-2026-000125`; estado `programada`; origen `recepción`; ficha disponible; notificación."),
            ("P28 · Cancelar cita en recepción (UC11)",
             "FAgendaRecepcion — acción Cancelar.",
             "Cita `C-2026-000125`; motivo `Paciente no puede asistir`.",
             "Cita `programada` o `confirmada`; recepcionista autorizado; plazo permitido.",
             "Estado `cancelada`; slot liberado; motivo registrado; historial actualizado."),
            ("P29 · Consultar agenda e historial (UC14)",
             "FAgenda y FHistorial — filtros por médico, fecha, estado y paciente.",
             "Médico `Dra. Ana Torres`; fecha `15/11/2026`; estado `confirmada`; paciente `7123456`.",
             "Sesión de recepción o médico; permisos por rol.",
             "Agenda filtrada con hora, código, paciente, especialidad y estado; historial completo del paciente."),
        ],
        [2.7, 3.2, 3.4, 3.1, 3.6],
        caption="Pruebas de caja negra del paquete PK-02",
        font_size=7.2,
    )
    b.p("La programación en recepción (P27) reutiliza la misma lógica transaccional de la reserva web (P10); la diferencia está en la interfaz, en el origen `recepción` y en los permisos del actor. Por eso P27 comparte con P10 las condiciones de disponibilidad y superposición, mientras que P05, P14, P18, P19, P28 y P29 validan las reglas propias de la agenda y del historial.")

    b.h2("8.4 Pruebas del paquete PK-03 — Atención y administración")
    b.p("El paquete PK-03 reúne el kiosco, la consulta médica, los pedidos, los pagos, los reportes y la administración de catálogos y usuarios. Las pruebas priorizan los flujos que habilitan la atención, dejan evidencia clínica o afectan la configuración de la agenda.")
    b.table(
        ["Caso de prueba", "Interfaz de usuario", "Datos de entrada", "Condiciones", "Datos de salida"],
        [
            ("P06 · Franja inválida (UC21)",
             "FHorarios — alta de franja.",
             "Día `Lunes`; inicio `12:00`; fin `08:00`; duración `30`; cupos web `4`; cupos kiosco `4`.",
             "Administrador autenticado; la hora fin debe ser posterior a la hora inicio.",
             "Rechazo «La hora fin debe ser mayor que la hora inicio»; no se guarda la franja."),
            ("P07 · Franjas solapadas (UC21)",
             "FHorarios — alta de franja.",
             "Día `Lunes`; inicio `09:00`; fin `11:00`.",
             "Franja activa `08:00–12:00` del mismo médico; solapamiento.",
             "Rechazo «La franja se superpone con otra activa»; no se guarda la franja."),
            ("P08 · Slots de 30 minutos (UC21)",
             "FHorarios y calendario de disponibilidad.",
             "Franja `Lunes 08:00–12:00`; duración `30`; fecha `16/11/2026`.",
             "Franja activa; sin bloqueos; cupos mayores que cero.",
             "Ocho slots de `08:00` a `11:30`; cada uno con cupo web y kiosco."),
            ("P17 · Ficha en kiosco (UC15)",
             "FKiosco — teclado de CI, selección de especialidad, médico y slot, y pantalla de ficha.",
             "CI `7123456`; especialidad `Medicina general`; médico `Dra. Ana Torres`; fecha `15/11/2026`; slot `09:00`.",
             "Token de kiosco válido; paciente existente o registrado; cupo kiosco mayor que cero; slot libre.",
             "Ficha `F-000456`; código de acceso `4821`; QR; cita `confirmada`; opción de impresión."),
            ("P20 · Consulta médica (UC16)",
             "FConsultaMedica — panel del médico.",
             "Motivo `Dolor de cabeza`; anamnesis `Cefalea de tres días`; PA `120/80`; FC `78`; T `36.5`; peso `62`; SpO2 `98`; diagnóstico `Cefalea tensional`; tratamiento `Paracetamol 500 mg`; indicaciones `Reposo e hidratación`.",
             "Cita `confirmada`; ficha emitida; médico propietario de la cita.",
             "Consulta guardada; cita `atendida`; historia clínica actualizada; recetas y órdenes habilitadas."),
            ("P21 · Pedido por WhatsApp (UC18)",
             "FPedido — receta generada y botón «Enviar por WhatsApp».",
             "Teléfono `70012345`; receta `R-000789`; mensaje precargado.",
             "Receta generada; teléfono válido; enlace profundo `wa.me` disponible.",
             "URL `wa.me/59170012345?text=...`; marca `enviado`; fecha y hora; registro en auditoría."),
            ("P22 · Pago registrado (UC19)",
             "FPago — formulario de pago.",
             "Monto `150.00`; método `Efectivo`; referencia `PAG-0001`; cita `C-2026-000123`.",
             "Monto mayor que cero; método válido; cita atendida o confirmada; sin pago duplicado.",
             "Pago almacenado; recibo `REC-0001`; saldo `0`; registro en auditoría."),
            ("P23 · Reporte por estado (UC23)",
             "FReportes — filtros por rango, médico y estado; exportación.",
             "Desde `01/10/2026`; hasta `31/10/2026`; estado `atendida`; médico `Todos`.",
             "Administrador autenticado; rango válido; datos existentes.",
             "Totales por estado; tabla de resultados; exportación PDF/XLSX; validación `desde <= hasta`."),
            ("P24 · Auditoría de catálogo (UC24)",
             "FEspecialidades — edición de especialidad.",
             "Especialidad `Cardiología`; cambio de `sexo_permitido` de `Todos` a `Femenino`.",
             "Administrador autenticado; especialidad existente; trigger `fn_auditar()` activo.",
             "Registro de auditoría con usuario, IP, fecha, acción `UPDATE` y valores antes/después."),
            ("P30 · Gestionar usuarios (UC22)",
             "FUsuarios — alta y edición de usuarios.",
             "Usuario `lucas.recepcion`; rol `Recepcionista`; correo `lucas@vidasalud.bo`; estado `Activo`.",
             "Administrador autenticado; usuario y correo únicos; rol válido.",
             "Usuario creado; rol asignado; contraseña temporal enviada; aparece en la lista."),
            ("P31 · Gestionar médicos y especialidades (UC20)",
             "FMedicos y FEspecialidades — alta de médico.",
             "Médico `Dr. Luis Rojas`; registro `MP-67890`; especialidad `Cardiología`; correo `luis@vidasalud.bo`.",
             "Administrador autenticado; registro profesional único; especialidad activa.",
             "Médico creado y asociado; disponible para la agenda; aparece en el directorio."),
            ("P36 · Recomendación por síntomas (UC25)",
             "Bienvenida del kiosco — chatbot Dr. Alex.",
             "Texto libre `me duele la cabeza`; respuestas de opción múltiple en 3 preguntas de confirmación.",
             "Chat abierto automáticamente; árbol de decisión cargado; palabras clave reconocibles.",
             "Se clasifica la descripción, se formulan al menos 3 preguntas de confirmación y se muestra una especialidad recomendada con opción de agendar."),
            ("P37 · Ambigüedad y fallback de categorías (UC25)",
             "Chatbot Dr. Alex — entrada de síntomas.",
             "Primer intento `xyz` (no reconocible); segundo intento `abc123?` (persistente).",
             "Ninguna palabra clave coincide en los intentos de texto libre.",
             "El chatbot solicita una nueva descripción una vez; ante la segunda ambigüedad muestra categorías generales seleccionables y el flujo no queda bloqueado."),
            ("P38 · Bloqueo de números fuera de cédula (UC25)",
             "Chatbot Dr. Alex — pasos de síntomas y confirmación.",
             "Entrada `tengo 38 de fiebre` o `2 días` en un paso distinto al de cédula.",
             "Usuario no está en el paso de identificación por cédula.",
             "El sistema bloquea la entrada numérica con un mensaje claro y mantiene el paso actual sin avanzar con datos inválidos."),
            ("P39 · Handoff a agendamiento con especialidad preseleccionada (UC25)",
             "Chatbot Dr. Alex — aceptación de recomendación y paso de cédula.",
             "Acepta agendar; cédula `7123456`.",
             "Especialidad recomendada disponible; `/api/kiosco/identificar` responde correctamente; flujo de reserva existente operativo.",
             "El chatbot identifica al paciente, reutiliza la API existente y continúa al flujo de agendamiento/ficha con la especialidad preseleccionada, sin duplicar pantallas."),
            ("P40 · Cancelación del agendamiento desde el chat (UC25)",
             "Chatbot Dr. Alex — pregunta «¿Quieres agendar una cita para esto?».",
             "Respuesta `No` / opción de rechazo.",
             "Recomendación ya mostrada; usuario en la bienvenida del kiosco.",
             "El chat no solicita cédula ni invoca identificación; el usuario permanece en la bienvenida del kiosco sin cambios en citas ni datos."),
        ],
        [2.7, 3.2, 3.4, 3.1, 3.6],
        caption="Pruebas de caja negra del paquete PK-03",
        font_size=7.2,
    )

    b.h2("8.5 Pruebas del paquete PK-04 — Servicios compartidos")
    b.p("El paquete PK-04 concentra los servicios transversales de seguridad, persistencia, correo, QR y auditoría. Las pruebas verifican que estos servicios protejan la información, que no interrumpan la operación cuando falla un servicio externo y que los tokens se invaliden correctamente.")
    b.table(
        ["Caso de prueba", "Interfaz de usuario", "Datos de entrada", "Condiciones", "Datos de salida"],
        [
            ("P03 · Acceso sin sesión (RNF03)",
             "URL interna `/admin/usuarios`.",
             "GET sin cookie de sesión.",
             "Sin sesión activa; middleware `AuthMiddleware` habilitado.",
             "Redirección 302 a `/login`; no se exponen datos ni rutas internas."),
            ("P04 · Desbloqueo administrativo (UC22)",
             "FUsuarios — acción Desbloquear.",
             "Usuario `ana.torres`; confirmación de la acción.",
             "Administrador autenticado; usuario bloqueado.",
             "`bloqueado = false`; `intentos_fallidos = 0`; registro en auditoría; login permitido."),
            ("P26 · RLS de médico (RNF03)",
             "Panel médico `/medico/agenda` y API de citas.",
             "Médico `Dra. Ana Torres` solicita las citas del `Dr. Luis Rojas`.",
             "Contexto RLS `app.medico_id`; rol `medico`.",
             "Solo se devuelven las citas propias; las filas ajenas quedan filtradas; sin fuga de datos."),
            ("P32 · Correo con fallback",
             "Recuperación de contraseña y confirmación de cita; log de correo.",
             "Correo `maria.quispe@example.com`; SMTP no disponible.",
             "Falla de Brevo/SMTP; la operación no debe abortar.",
             "Correo marcado `pendiente`; evento en el log; mensaje «correo en cola»; la operación continúa."),
            ("P33 · QR de ficha",
             "Ficha `/ficha/F-000456` y ticket imprimible.",
             "Ficha `F-000456`; cita `C-2026-000123`.",
             "Ficha emitida; librería de QR disponible.",
             "Imagen QR con código y URL; legible por escáner; coincide con la ficha emitida."),
            ("P34 · CSRF",
             "Cualquier formulario: `/login`, `/pacientes` o `/reserva`.",
             "POST con sesión válida pero sin `csrf_token` o con token inválido.",
             "Middleware CSRF activo.",
             "HTTP 403; sin cambio de estado; evento en el log de seguridad."),
            ("P35 · Token de kiosco vencido",
             "Kiosco `/kiosco`.",
             "Token con más de diez minutos; CI `7123456`.",
             "Token HMAC vencido; sesión de kiosco.",
             "Flujo reiniciado; mensaje «Sesión expirada»; sin ficha; se solicita un nuevo token."),
        ],
        [2.7, 3.2, 3.4, 3.1, 3.6],
        caption="Pruebas de caja negra del paquete PK-04",
        font_size=7.2,
    )

    b.h2("8.6 Matriz de cobertura")
    b.p("La matriz relaciona cada requisito con los casos de prueba que lo verifican. La cobertura se considera alta cuando el requisito se prueba en sus flujos válidos, límite y de rechazo; media cuando se cubren los flujos principales; y baja cuando solo se verifica de forma indirecta.")
    b.table(
        ["Requisito", "Casos de prueba", "Cobertura", "Observación"],
        [
            ("RF01", "P01–P04, P30", "Alta", "Login, bloqueo, desbloqueo y gestión de usuarios."),
            ("RF02", "P05", "Media", "Alta de paciente y unicidad de la CI."),
            ("RF03 / RF04", "P10, P17, P26, P31", "Media", "Médicos y especialidades en reserva, kiosco y administración."),
            ("RF05", "P06–P09, P17", "Alta", "Franjas, slots, cupos y ficha."),
            ("RF06", "P10, P13, P25, P27", "Alta", "Registro de citas y validación de fecha."),
            ("RF07", "P11, P12, P25", "Alta", "Superposición, fuera de horario y concurrencia."),
            ("RF08", "P14", "Media", "Reprogramación y liberación del slot anterior."),
            ("RF09", "P15, P16, P28", "Alta", "Cancelación del paciente y de recepción."),
            ("RF10", "P17–P19", "Alta", "Ficha, transiciones de estado y reconsulta."),
            ("RF11 / RF12", "P20, P21, P29", "Media", "Agenda, historial y pedidos."),
            ("RF13", "P22, P23", "Media", "Pagos y reportes."),
            ("RNF03", "P03, P04, P26, P34, P35", "Alta", "Sesión, roles, RLS, CSRF y token de kiosco."),
            ("RNF05", "P25", "Alta", "Integridad transaccional y ausencia de superposición."),
            ("RNF09", "P24, P32", "Media", "Auditoría de catálogos y trazabilidad del correo."),
            ("Integraciones", "P21, P32, P33", "Media", "WhatsApp, correo y código QR."),
            ("Mejora UC25 (kiosco)", "P36–P40", "Media", "Orientación por síntomas, fallback sin bloqueo, control de entrada y handoff al flujo existente."),
        ],
        [2.6, 6.8, 2.6, 4.0],
        caption="Cobertura de requisitos/mejoras por pruebas de caja negra",
        font_size=7.9,
        align_center_cols={0, 2},
    )

    b.h2("8.7 Ejecución y evidencia")
    b.p("Cada caso se ejecuta sobre una base de datos limpia, con seeders de catálogos y usuarios de los seis roles. Los resultados se registran en una tabla de ejecución con fecha, responsable, resultado obtenido y estado. Las pruebas de concurrencia se ejecutan con dos sesiones simultáneas sobre el mismo slot; las de seguridad verifican que un rol no acceda a rutas ni filas de otro; y las de integración simulan la caída del correo para comprobar el fallback.")
    b.table(
        ["Tipo de evidencia", "Contenido"],
        [
            ("Registro de ejecución", "ID, fecha, responsable, resultado obtenido, estado y observaciones."),
            ("Capturas de pantalla", "Landing, login, reserva, kiosco, chatbot Dr. Alex, ficha, consulta, pagos y reportes."),
            ("Salida de base de datos", "Citas, fichas, historial de estados, pagos y auditoría."),
            ("Respuestas HTTP", "Códigos 200, 201, 302, 401, 403, 409 y 422."),
            ("Log de correo", "Envío, fallback a cola y errores SMTP."),
            ("Reporte de defectos", "Severidad, estado, responsable y acción correctiva."),
        ],
        [4.2, 11.8],
        caption="Evidencias de prueba",
        font_size=8.0,
    )

    b.h2("8.8 Defectos y acciones correctivas")
    b.p("Los defectos potenciales se priorizan por severidad y se asocian a la prueba que los detecta. La acción correctiva indica el control técnico o la validación que evita su reaparición.")
    b.table(
        ["Defecto potencial", "Severidad", "Acción"],
        [
            ("Cita superpuesta por concurrencia", "Crítica", "Bloqueo pesimista, transacción e índice único parcial (P25)."),
            ("Cupo negativo o sobreventa", "Alta", "Validación en el servicio y restricción CHECK en la base de datos (P08, P09)."),
            ("Ficha sin confirmar la cita", "Alta", "Transacción única en FichaService (P17)."),
            ("Acceso a datos de otro médico", "Crítica", "RLS, contexto de sesión y pruebas de autorización (P26)."),
            ("Token de recuperación reutilizable", "Alta", "Invalidación al usarlo y expiración (P32)."),
            ("Reporte con rango inválido", "Media", "Validación `desde <= hasta` (P23)."),
            ("Token de kiosco vencido no reinicia el flujo", "Alta", "Validación HMAC y timeout de diez minutos (P35)."),
            ("Formulario sin CSRF aceptado", "Crítica", "Middleware CSRF y prueba negativa (P34)."),
            ("QR ilegible o inconsistente con la ficha", "Media", "Verificación del código y regeneración del QR (P33)."),
        ],
        [5.6, 2.4, 8.0],
        caption="Defectos potenciales y acciones correctivas",
        font_size=7.9,
        align_center_cols={1},
    )

    b.h2("8.9 Criterios de aceptación")
    b.bullets([
        "Todos los casos que cubren requisitos de prioridad P1 Crítica deben finalizar con el resultado esperado.",
        "No debe existir ninguna cita superpuesta en la prueba de concurrencia (P25).",
        "Los usuarios solo acceden a las rutas y filas permitidas por su rol (P03, P26).",
        "La ficha debe confirmar la cita y habilitar la atención (P17).",
        "La cancelación debe respetar el plazo y liberar el slot (P15, P16, P28).",
        "La auditoría debe registrar los cambios de catálogos con IP y valores antes/después (P24).",
        "La caída del correo no debe interrumpir la reserva ni la recuperación de contraseña (P32).",
        "El QR de la ficha debe ser legible y coincidir con el código emitido (P33).",
        "Las solicitudes sin CSRF o con token de kiosco vencido deben rechazarse (P34, P35).",
        "El chatbot Dr. Alex debe orientar sin bloquear el flujo: ante ambigüedad ofrece categorías, bloquea números fuera de cédula y, al agendar, reutiliza la identificación y el flujo existente sin persistir síntomas (P36–P40).",
    ])


def closing():
    b.h1("CONCLUSIÓN")
    b.p("El proyecto demuestra que un sistema web de gestión de citas puede resolver los problemas operativos de la Clínica Vida Salud mediante una arquitectura sencilla, una base de datos relacional robusta y un conjunto claro de reglas de negocio. La separación entre requisitos, análisis, diseño, implementación y pruebas permite comprender y mantener la solución.")
    b.p("La validación de disponibilidad en el servidor, el uso de transacciones y el índice único parcial constituyen la defensa principal contra citas superpuestas. La distribución de cupos 50/50, la emisión de fichas y la reconsulta prioritaria responden a las necesidades reales de la clínica. La seguridad se refuerza con roles, RLS, CSRF, bloqueo de cuentas y auditoría.")
    b.p("El documento evidencia la trazabilidad entre los requisitos RF01–RF13, la mejora UC25, los casos de uso UC01–UC25, las clases de análisis, los componentes de diseño y los casos de prueba. Esta trazabilidad facilita la evaluación académica y sirve como base para la implementación y la puesta en producción.")

    b.h1("RECOMENDACIÓN")
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


def repo_section():
    b.h1("CÓDIGO FUENTE Y REPOSITORIO")
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


def annexes():
    b.h1("ANEXOS")
    b.h2("Anexo A. Glosario")
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
            ("Chatbot Dr. Alex", "Asistente de orientación del kiosco que recomienda una especialidad mediante un árbol de decisión fijo; no usa IA generativa ni altera datos clínicos."),
        ],
        [3.4, 12.6],
        caption="Glosario de términos",
    )

    b.h2("Anexo B. Diccionario de datos resumido")
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

    b.h2("Anexo C. Matriz de consistencia")
    b.table(
        ["Requisito", "Caso de uso", "Clase/servicio", "Prueba"],
        [
            ("RF01", "UC03, UC04, UC22", "AuthService, AuthController", "P01–P04, P30"),
            ("RF02", "UC08", "PacienteModel, PacienteService", "P05"),
            ("RF03 / RF04", "UC02, UC20", "MedicoModel, EspecialidadModel", "P10, P17, P26, P31"),
            ("RF05", "UC05, UC09, UC15, UC21", "DisponibilidadService", "P06–P09, P17"),
            ("RF06 / RF07", "UC05, UC09, UC15", "CitaService, CitaModel", "P10–P13, P25, P27"),
            ("RF08", "UC10", "CitaService", "P14"),
            ("RF09", "UC06, UC11", "CitaService", "P15, P16, P28"),
            ("RF10", "UC12, UC13", "CitaService, ReconsultaService", "P17–P19"),
            ("RF11 / RF12", "UC07, UC14, UC16–UC18", "ConsultaService, PedidoModel", "P20, P21, P29"),
            ("RF13", "UC19, UC23", "PagoModel, ReporteModel", "P22, P23"),
            ("RNF03", "UC04, UC22", "AuthService, RoleMiddleware, RLS", "P03, P04, P26, P34, P35"),
            ("RNF05", "UC05, UC09", "CitaService, índice único parcial", "P25"),
            ("RNF09", "UC24", "fn_auditar(), AuditoriaModel", "P24, P32"),
            ("Integraciones", "UC18, UC19", "MailService, generador QR", "P21, P32, P33"),
            ("Mejora UC25 (kiosco)", "UC25", "ChatSintomas JS, DrAlexBuddy, reutiliza /api/kiosco/identificar", "P36–P40"),
        ],
        [2.2, 5.0, 6.4, 2.4],
        caption="Matriz de consistencia requisito–caso de uso–clase–prueba",
        font_size=7.9,
    )

    b.h2("Anexo D. Inventario de diagramas")
    b.p("La distribución mantiene el criterio del documento de referencia PROYECTOCOMPLETO1: los casos de uso se concentran en la captura de requisitos; los prototipos se separan por ciclo y nivel de fidelidad, con el panel administrativo del Ciclo 1 y la reserva web y el kiosco del Ciclo 2 como prototipos de baja fidelidad, y diecinueve capturas de vistas implementadas como prototipos de alta fidelidad (once vistas generales y ocho flujos operativos del Ciclo 2); el análisis reúne las clases, el dominio y las secuencias de los flujos del Ciclo 2; el diseño reúne la arquitectura lógica y física, las secuencias de diseño del Ciclo 1, el despliegue y el modelo entidad-relación; y la implementación incorpora los diagramas de componentes del sistema y de los subsistemas. Como diferencia, este PUDS representa los paquetes con una tabla en la sección 4.5 y añade una vista de navegación por rol, además de diagramas actor–caso de uso en el detalle (4.3) y una vista integral de paquetes (5.4).")
    b.table(
        ["Capítulo", "Tipos de diagrama", "Diagramas"],
        [
            ("2", "RUP: fases y disciplinas", "2"),
            ("3", "Actividad de negocio", "1"),
            ("4", "Casos de uso, detalle de actores y prototipos de interfaz", "35"),
            ("5", "Paquetes, clases, secuencia y estados", "10"),
            ("6", "Componentes, paquetes, arquitectura física, secuencia de diseño, despliegue, entidad-relación y navegación", "15"),
            ("7", "Componentes de la arquitectura del sistema y de los subsistemas", "2"),
            ("8", "Flujo de pruebas", "1"),
            ("Código fuente y repositorio", "QR del repositorio (no es diagrama UML)", "1"),
        ],
        [2.2, 10.2, 3.6],
        caption="Distribución de diagramas por capítulo",
        font_size=8.2,
        align_center_cols={0, 2},
    )
    b.table(
        ["Archivo", "Diagrama", "Tipo UML", "Capítulo"],
        [
            ("rup_fases.png", "Fases RUP", "RUP", "2"),
            ("rup_disciplinas.png", "Disciplinas RUP", "RUP", "2"),
            ("02_actividad_negocio.png", "Actividad de negocio", "Actividad", "3"),
            ("01_casos_uso_publico.png", "Casos de uso públicos", "Casos de uso", "4"),
            ("01b_casos_uso_citas.png", "Casos de uso de citas", "Casos de uso", "4"),
            ("01c_casos_uso_atencion.png", "Casos de uso de atención", "Casos de uso", "4"),
            ("31_prototipo_panel_ciclo1.png", "Prototipo del panel administrativo del Ciclo 1", "Prototipo", "4"),
            ("28_prototipo_reserva.png", "Prototipo de reserva web", "Prototipo", "4"),
            ("29_prototipo_kiosco.png", "Prototipo de kiosco", "Prototipo", "4"),
            ("32_vista_paciente_landing.png", "Vista de alta fidelidad de la landing pública", "Prototipo", "4"),
            ("33_vista_paciente_especialidades.png", "Vista de alta fidelidad del directorio de especialidades", "Prototipo", "4"),
            ("34_vista_paciente_medicos.png", "Vista de alta fidelidad del directorio de médicos", "Prototipo", "4"),
            ("35_vista_paciente_nosotros.png", "Vista de alta fidelidad de la sección Nosotros", "Prototipo", "4"),
            ("36_vista_paciente_canales.png", "Vista de alta fidelidad de canales de atención", "Prototipo", "4"),
            ("37_vista_paciente_blog.png", "Vista de alta fidelidad del blog de salud", "Prototipo", "4"),
            ("38_vista_paciente_kiosco.png", "Vista de alta fidelidad del kiosco de fichas", "Prototipo", "4"),
            ("39_vista_medico_panel.png", "Vista de alta fidelidad del panel del médico", "Prototipo", "4"),
            ("40_vista_recepcion_panel.png", "Vista de alta fidelidad del panel de recepción", "Prototipo", "4"),
            ("41_vista_recepcion_medica_panel.png", "Vista de alta fidelidad del panel de recepción médica", "Prototipo", "4"),
            ("42_vista_admin_usuarios.png", "Vista de alta fidelidad del panel de administración de usuarios", "Prototipo", "4"),
            ("63_uc04_actores.png", "Actores del caso de uso UC04 Iniciar sesión", "Casos de uso", "4"),
            ("64_uc05_actores.png", "Actores del caso de uso UC05 Reservar cita web", "Casos de uso", "4"),
            ("65_uc09_actores.png", "Actores del caso de uso UC09 Programar cita", "Casos de uso", "4"),
            ("66_uc10_actores.png", "Actores del caso de uso UC10 Reprogramar cita", "Casos de uso", "4"),
            ("67_uc15_actores.png", "Actores del caso de uso UC15 Emitir ficha en kiosco", "Casos de uso", "4"),
            ("68_uc16_actores.png", "Actores del caso de uso UC16 Registrar consulta", "Casos de uso", "4"),
            ("fecha y hora al agendar cita.png", "Ciclo 2: selección de fecha y hora al agendar cita (UC05)", "Prototipo", "4"),
            ("confirmar cita.png", "Ciclo 2: confirmación de cita antes de registrar (UC05)", "Prototipo", "4"),
            ("cita confirmada.png", "Ciclo 2: confirmación exitosa de cita reservada (UC05)", "Prototipo", "4"),
            ("ficha emitida vista desde recepcion.png", "Ciclo 2: ficha emitida vista desde recepción (UC15)", "Prototipo", "4"),
            ("reprogramar una cita.png", "Ciclo 2: reprogramación de cita (UC10)", "Prototipo", "4"),
            ("cita reprogramada.png", "Ciclo 2: confirmación de cita reprogramada (UC10)", "Prototipo", "4"),
            ("cambio de estado de cita a atendida.png", "Ciclo 2: cambio de estado de cita a atendida (UC12)", "Prototipo", "4"),
            ("registro de pago.png", "Ciclo 2: registro de pago y método de pago (UC19)", "Prototipo", "4"),
            ("inicio conversacion chat bot.jpeg", "Ciclo 2: inicio de conversación con el chatbot Dr. Alex en el kiosco (UC25)", "Prototipo", "4"),
            ("primer mensaje de consulta del bot.jpeg", "Ciclo 2: primer mensaje de consulta del chatbot Dr. Alex (UC25)", "Prototipo", "4"),
            ("respuesta del paciente.jpeg", "Ciclo 2: respuesta del paciente en la conversación del chatbot Dr. Alex (UC25)", "Prototipo", "4"),
            ("especialidad recomendada por el bot.jpeg", "Ciclo 2: especialidad recomendada por el chatbot Dr. Alex (UC25)", "Prototipo", "4"),
            ("62_paquetes_integral.png", "Vista integral de paquetes del análisis", "Paquetes / Casos de uso", "5"),
            ("05_analisis_clases.png", "Clases de análisis", "Clases", "5"),
            ("03_clases_dominio_core.png", "Dominio núcleo", "Clases", "5"),
            ("04_clases_dominio_clinico.png", "Dominio clínico", "Clases", "5"),
            ("08_sec_login.png", "Secuencia de login", "Secuencia", "5"),
            ("09_sec_reserva.png", "Secuencia de reserva", "Secuencia", "5"),
            ("10_sec_kiosco.png", "Secuencia de kiosco", "Secuencia", "5"),
            ("11_sec_cancelar.png", "Secuencia de cancelación", "Secuencia", "5"),
            ("12_sec_consulta.png", "Secuencia de consulta", "Secuencia", "5"),
            ("13_estados_cita.png", "Estados de la cita", "Estados", "5"),
            ("17_arquitectura_fisica.png", "Arquitectura física", "Despliegue", "6"),
            ("07_despliegue.png", "Despliegue", "Despliegue", "6"),
            ("30_capas_arquitectura_logica.png", "Paquetes por capas del diseño", "Paquetes", "6"),
            ("06_arquitectura_logica.png", "Arquitectura lógica", "Componentes", "6"),
            ("18_sec_login_diseno.png", "Secuencia de diseño de login", "Secuencia", "6"),
            ("19_sec_pacientes_diseno.png", "Secuencia de diseño de pacientes", "Secuencia", "6"),
            ("20_sec_horarios_diseno.png", "Secuencia de diseño de horarios", "Secuencia", "6"),
            ("23_sec_reserva_diseno.png", "Secuencia de diseño de reserva web", "Secuencia", "6"),
            ("24_sec_programar_diseno.png", "Secuencia de diseño de programación", "Secuencia", "6"),
            ("25_sec_reprogramar_diseno.png", "Secuencia de diseño de reprogramación", "Secuencia", "6"),
            ("26_sec_kiosco_diseno.png", "Secuencia de diseño de kiosco", "Secuencia", "6"),
            ("27_sec_consulta_diseno.png", "Secuencia de diseño de consulta", "Secuencia", "6"),
            ("16_mapa_navegacion.png", "Navegación", "Navegación", "6"),
            ("14_er_nucleo.png", "ER núcleo", "Entidad-relación", "6"),
            ("15_er_clinico.png", "ER clínico", "Entidad-relación", "6"),
            ("43_impl_arquitectura_sistema.png", "Componentes de la arquitectura del sistema", "Componentes", "7"),
            ("44_impl_arquitectura_subsistemas.png", "Componentes de los subsistemas P1, P2 y P3", "Componentes", "7"),
            ("pruebas_workflow.png", "Flujo de pruebas", "Actividad", "8"),
            ("qr_repositorio.png", "QR del repositorio", "No UML", "Código fuente y repositorio"),
        ],
        [6.2, 4.4, 2.8, 2.6],
        caption="Inventario de figuras del documento",
        font_size=7.9,
        align_center_cols={2, 3},
    )

    b.h2("Anexo E. Lista de verificación")
    b.table(
        ["Ítem", "Estado"],
        [
            ("Repositorio con código y documentación.", "Cumplido"),
            ("Diagramas UML en formato editable y PNG.", "Cumplido"),
            ("Especificación de requisitos RF01–RF13.", "Cumplido"),
            ("Catálogo de casos de uso UC01–UC25.", "Cumplido"),
            ("Modelo de datos y políticas RLS.", "Cumplido"),
            ("Diseño de arquitectura lógica, física y despliegue.", "Cumplido"),
            ("Plan y casos de prueba.", "Cumplido"),
            ("Documento en formato Word y PDF.", "Pendiente de compilación"),
            ("Defensa preparada.", "Pendiente"),
        ],
        [12.0, 4.0],
        caption="Lista de verificación de entrega",
        align_center_cols={1},
    )

