# -*- coding: utf-8 -*-
from puds_lib import b, cover, front_matter


def chapter1():
    b.h1("1. PERFIL DEL PROYECTO")
    b.h2("1.1 Introducción")
    b.p("La Clínica Vida Salud es un establecimiento de salud que brinda atención ambulatoria en especialidades como medicina general, pediatría, ginecología, cardiología, traumatología y otras. Su operación actual depende de registros manuales y de una comunicación fragmentada entre recepción, médicos y pacientes, lo que genera duplicidad de citas, pérdida de fichas, demoras en la atención y poca visibilidad de la agenda.")
    b.p("El presente proyecto propone un sistema web de gestión de citas médicas que centralice la información de pacientes, médicos, especialidades, horarios, cupos, citas, fichas, historia clínica, pedidos, pagos y reportes. La solución se construye con PHP 8.1+, PostgreSQL 16, HTML5, CSS3 y JavaScript, bajo una arquitectura MVC manual y un proceso de desarrollo iterativo e incremental basado en RUP.")
    b.note("El sistema no reemplaza el criterio clínico ni constituye un dispositivo médico. Su finalidad es administrativa y de apoyo a la atención, preservando la confidencialidad y la trazabilidad de la información.", "Delimitación")

    b.h2("1.2 Antecedentes y planteamiento del problema")
    b.p("La clínica atiende a pacientes que reservan por teléfono, de forma presencial o por recomendación de un médico. La recepción anota las citas en una agenda física y utiliza hojas de cálculo para consolidar la información. Este esquema presenta los siguientes problemas:")
    b.bullets([
        ("Duplicidad de citas: ", "dos personas pueden registrar al mismo paciente o al mismo médico en el mismo horario."),
        ("Pérdida de información: ", "las fichas y historiales en papel se extravían o se vuelven ilegibles."),
        ("Falta de control de cupos: ", "no existe una distribución equitativa entre la reserva web y la atención presencial en kiosco."),
        ("Cancelaciones tardías: ", "las citas no se liberan con la anticipación necesaria."),
        ("Poca trazabilidad: ", "no se registra quién modificó un catálogo ni cuándo."),
        ("Experiencia limitada: ", "el paciente no puede consultar disponibilidad ni sus propias citas."),
    ])
    b.p("El problema central se resume en la ausencia de un mecanismo único, confiable y disponible para gestionar la demanda de citas y la información clínica asociada. La solución debe reducir la carga operativa de recepción, evitar conflictos de agenda y ofrecer autoservicio controlado al paciente.")

    b.h2("1.3 Objetivos")
    b.h3("1.3.1 Objetivo general")
    b.p("Diseñar e implementar un sistema web de gestión de citas médicas para la Clínica Vida Salud que automatice la reserva, reprogramación, cancelación, control de asistencia, emisión de fichas, historia clínica, pedidos, pagos y reportes, garantizando integridad, seguridad y trazabilidad.")
    b.h3("1.3.2 Objetivos específicos")
    b.numbered([
        "Modelar los procesos de negocio y los requisitos funcionales y no funcionales del sistema.",
        "Implementar autenticación por roles con bloqueo por intentos fallidos y recuperación de contraseña.",
        "Gestionar pacientes, médicos, especialidades, horarios, cupos y bloqueos de agenda.",
        "Registrar citas con validación de disponibilidad en el servidor y prevención de superposiciones.",
        "Permitir la reprogramación y cancelación controlada de citas, conservando el historial.",
        "Implementar el kiosco de autoservicio para emisión de fichas con código y QR.",
        "Registrar consultas, recetas, documentos, órdenes de estudio y pagos.",
        "Enviar pedidos mediante enlace de WhatsApp con código de acceso.",
        "Generar reportes operativos y dashboards por rol.",
        "Aplicar pruebas funcionales, de seguridad y de concurrencia sobre los flujos críticos.",
    ])

    b.h2("1.4 Alcance del sistema")
    b.table(
        ["Incluido", "Excluido"],
        [
            ("Autenticación, roles y permisos.\nPacientes, médicos y especialidades.\nHorarios, cupos 50/50 y bloqueos.\nCitas, reprogramación y cancelación.\nEstados, asistencia y reconsultas.\nKiosco y fichas con QR.\nHistoria clínica, recetas, documentos y órdenes.\nPagos y reportes.\nAuditoría de catálogos.", "Pasarela de pago real.\nMatching biométrico real.\nWhatsApp Business API.\nIntegración HL7/FHIR.\nFacturación electrónica.\nTelemedicina.\nAplicación móvil nativa.\nImpresión física obligatoria de fichas."),
        ],
        [8.0, 8.0],
        caption="Alcance funcional del proyecto",
    )

    b.h2("1.5 Actores y roles")
    b.table(
        ["Rol", "Código", "Responsabilidad principal"],
        [
            ("Superadministrador", "super_admin", "Control total del sistema, usuarios, catálogos, auditoría y reportes."),
            ("Administrador", "admin", "Configuración de catálogos, usuarios, horarios, reportes y desbloqueo de cuentas."),
            ("Recepcionista", "recepcionista", "Gestión de pacientes y citas de toda la clínica, fichas y reconsultas."),
            ("Recepcionista de médico", "recepcionista_medico", "Operación limitada a los médicos que tiene asignados."),
            ("Médico", "medico", "Atención de su agenda, historia clínica, recetas, órdenes y pagos."),
            ("Paciente", "paciente", "Reserva, consulta y cancelación de sus propias citas y pedidos."),
            ("Kiosco", "dispositivo", "Emisión de fichas en modo público con token firmado."),
        ],
        [3.4, 3.7, 8.9],
        caption="Actores del sistema",
        font_size=8.5,
    )

    b.h2("1.6 Interesados y expectativas")
    b.table(
        ["Interesado", "Expectativa"],
        [
            ("Dirección de la clínica", "Información consolidada, reportes confiables y control de la operación."),
            ("Recepción", "Menos trabajo manual, agenda clara y prevención de errores."),
            ("Médicos", "Agenda ordenada, acceso rápido a historia clínica y pedidos."),
            ("Pacientes", "Reserva simple, confirmación oportuna y consulta de sus citas."),
            ("Equipo de TI", "Código mantenible, seguridad, respaldo y despliegue viable."),
            ("Docente y evaluadores", "Trazabilidad entre requisitos, análisis, diseño, implementación y pruebas."),
        ],
        [4.0, 12.0],
        caption="Interesados y expectativas",
    )

    b.h2("1.7 Metodología y ciclo de vida")
    b.p("Se adopta un ciclo de vida iterativo e incremental basado en RUP, con énfasis en las disciplinas de Captura de Requisitos, Análisis, Diseño, Implementación y Pruebas. El trabajo se organiza en dos ciclos: el primero consolida la base de datos, seguridad, catálogos, horarios y landing; el segundo incorpora citas, estados, kiosco, historia clínica, pedidos, pagos, reportes y auditoría.")
    b.p("El Ciclo 1 (hasta el 24/10/2026) establece la plataforma base sobre la que se construyen los demás módulos. Su implementación sigue un orden de dependencias: primero el modelo de datos y las políticas de seguridad a nivel de fila; luego la autenticación y el control de acceso por roles; a continuación los catálogos de pacientes, médicos y especialidades; después la configuración de horarios y cupos 50/50 web/kiosco; y finalmente la landing pública base. Este orden garantiza que cada módulo cuente con las tablas, permisos y catálogos que necesita antes de ser construido, y se complementa con las reglas transversales de seguridad, validación cliente/servidor, CSRF y despliegue en cPanel con PostgreSQL en la nube.")
    b.figure("rup_fases.png", 15.5, "Fases e hitos del proceso unificado aplicados al proyecto")
    b.figure("rup_disciplinas.png", 15.5, "Disciplinas RUP y su intensidad relativa por fase")
    b.table(
        ["Ciclo", "Periodo", "Entregables principales"],
        [
            ("Ciclo 1", "Hasta 24/10/2026", "Modelo de datos y RLS; autenticación; pacientes, médicos y especialidades; horarios y cupos 50/50; landing base. Orden: 99 → 01 → 02/03/04 → 05 → 15."),
            ("Ciclo 2", "Hasta 07/11/2026", "Citas, estados, reconsultas, kiosco, fichas, agenda, historia clínica, pedidos, WhatsApp, pagos, reportes y auditoría."),
            ("Cierre", "09/11/2026", "Documento PUDS-UML, pruebas integradas, repositorio y defensa."),
        ],
        [2.2, 3.0, 10.8],
        caption="Planificación general por ciclos",
    )
    b.h3("1.7.1 Detalle de los entregables del Ciclo 1")
    b.p("La tabla siguiente desglosa el alcance comprometido para el Ciclo 1, alineado con las especificaciones por módulo del repositorio (carpeta docs/specs). Cada entrega incluye su modelo de datos, endpoints, reglas de negocio y criterios de aceptación.")
    b.table(
        ["Spec", "Módulo", "Resultado esperado"],
        [
            ("99", "Modelo de datos y RLS", "Esquema PostgreSQL 16 con enums, códigos visibles, borrado lógico y políticas de seguridad a nivel de fila por rol."),
            ("01", "Autenticación y control de acceso", "Login por CI o correo, sesión segura, menú dinámico por rol, bloqueo tras tres intentos fallidos y recuperación de contraseña por token."),
            ("02", "Pacientes", "CRUD y búsqueda por CI, nombre o apellido; perfil con datos personales, contacto, información médica y contacto de emergencia; CI única y desactivación sin pérdida de historial."),
            ("03", "Médicos", "CRUD, asignación de especialidades (N:M), código QR profesional, activación/desactivación y asignación de recepcionistas."),
            ("04", "Especialidades", "CRUD con restricciones de sexo y edad, especialidades destacadas y visibilidad controlada en la landing."),
            ("05", "Horarios y cupos", "Franjas semanales por médico, cupos web/kiosco 50/50, bloqueos de agenda y cálculo de disponibilidad por canal."),
            ("15", "Landing pública base", "Página informativa con buscador de especialidades, directorio de médicos activos, llamados a la acción y registro/login de paciente."),
        ],
        [1.4, 4.0, 10.6],
        caption="Entregables detallados del Ciclo 1",
        font_size=8.2,
    )
    b.p("El cierre del Ciclo 1 deja operativa la base del sistema: usuarios y roles, catálogos maestros, disponibilidad y sitio público, de modo que el Ciclo 2 pueda concentrarse en la operación de citas, la atención clínica y los procesos de soporte.")

    b.h3("1.7.2 Detalle de los entregables del Ciclo 2")
    b.p("El Ciclo 2 (hasta el 07/11/2026) completa la operación clínica y administrativa. A partir de la base entregada, incorpora el registro y la gestión de citas, los estados y reconsultas, el kiosco y las fichas, la agenda y el historial, la historia clínica con recetas, documentos y órdenes, el envío de pedidos por WhatsApp, los pagos, los reportes y la auditoría. El orden conserva la dependencia entre agenda, cita, atención y soporte, de modo que cada módulo se apoye en datos y permisos ya disponibles.")
    b.table(
        ["Spec", "Módulo", "Resultado esperado"],
        [
            ("06", "Citas", "Registro, prevención de duplicados y superposiciones, reprogramación y cancelación con validación de disponibilidad."),
            ("07", "Estados y asistencia", "Transiciones de estado, control de asistencia e inasistencia, y reconsultas por ausencia o seguimiento."),
            ("09", "Kiosco y fichas", "Emisión de ficha con token firmado, código de acceso y QR; confirmación de la cita."),
            ("08", "Agenda e historial", "Agenda médica por rol e historial cronológico del paciente."),
            ("10", "Historia clínica", "Consultas, recetas, documentos y órdenes de estudio."),
            ("11", "WhatsApp y pedidos", "Envío de pedidos mediante enlace con código de acceso."),
            ("12", "Pagos", "Registro de pagos por monto y método, asociado a la atención."),
            ("13", "Reportes", "Dashboards y reportes de demanda, atención, cancelaciones y productividad."),
            ("14", "Auditoría", "Bitácora de cambios de catálogos con IP y datos antes/después."),
        ],
        [1.4, 4.0, 10.6],
        caption="Entregables detallados del Ciclo 2",
        font_size=8.2,
    )

    b.h3("1.7.3 Distribución de casos de uso por ciclo")
    b.p("La asignación de casos de uso a cada ciclo sigue la prioridad y las dependencias funcionales. El Ciclo 1 agrupa los casos de uso de la plataforma base y el Ciclo 2 los de la operación clínica y de soporte. La tabla siguiente resume la distribución y remite a la priorización detallada del capítulo 4.")
    b.table(
        ["Ciclo", "Casos de uso", "N.º", "Distribución por prioridad", "Resultado esperado"],
        [
            ("Ciclo 1", "UC01–UC04, UC08, UC20–UC22", "8", "5 P1, 1 P3, 2 P4", "Plataforma base, catálogos y disponibilidad operativos."),
            ("Ciclo 2", "UC05–UC07, UC09–UC19, UC23–UC24", "16", "6 P1, 3 P2, 7 P3", "Operación de citas, atención clínica, pagos, reportes y auditoría."),
        ],
        [1.8, 5.6, 1.0, 3.4, 4.2],
        caption="Distribución de casos de uso por ciclo",
        font_size=8.0,
        align_center_cols={0, 2, 3},
    )

    b.h2("1.8 Organización del documento")
    b.p("El documento se estructura en ocho capítulos. El capítulo 1 presenta el perfil y el problema. El capítulo 2 expone el marco teórico. El capítulo 3 describe el modelo de negocio. El capítulo 4 captura los requisitos y casos de uso. El capítulo 5 desarrolla el análisis. El capítulo 6 presenta el diseño. El capítulo 7 documenta la implementación. El capítulo 8 describe la estrategia y los casos de prueba. Finalmente se incluyen conclusiones, recomendaciones, bibliografía y anexos.")


def chapter2():
    b.h1("2. MARCO TEÓRICO")
    b.h2("2.1 Ingeniería de requisitos")
    b.p("La ingeniería de requisitos es la disciplina que establece, documenta y mantiene los requisitos de un sistema. Comprende la elicitación, el análisis, la especificación, la validación y la gestión de cambios. Un requisito funcional describe un comportamiento o servicio; un requisito no funcional describe una restricción de calidad, seguridad, rendimiento o plataforma. La trazabilidad permite verificar que cada requisito se transforma en casos de uso, clases, componentes y pruebas.")
    b.p("Para este proyecto se aplican técnicas de entrevista con el personal de recepción y médicos, revisión de documentos operativos, observación del flujo de atención y prototipación de las pantallas críticas. Los requisitos se numeran como RF01–RF13 y las reglas de negocio como RN01–RN14 y RX01–RX13.")

    b.h2("2.2 Proceso Unificado Rational (RUP)")
    b.p("RUP es un proceso de ingeniería de software iterativo e incremental, dirigido por casos de uso, centrado en la arquitectura y guiado por riesgos. Se organiza en cuatro fases —Inicio, Elaboración, Construcción y Transición— y en disciplinas que agrupan actividades técnicas y de gestión.")
    b.table(
        ["Fase", "Propósito", "Artefactos principales"],
        [
            ("Inicio", "Definir visión, alcance y viabilidad.", "Perfil, visión, actores, casos de uso principales, riesgos."),
            ("Elaboración", "Estabilizar arquitectura y requisitos críticos.", "Modelo de casos de uso, análisis, arquitectura, modelo de datos."),
            ("Construcción", "Implementar y probar el producto.", "Código, componentes, pruebas de integración, manuales."),
            ("Transición", "Desplegar y validar con usuarios.", "Versión candidata, pruebas de aceptación, capacitación."),
        ],
        [2.5, 5.0, 8.5],
        caption="Fases de RUP y artefactos",
    )
    b.p("Las disciplinas se representan en el documento mediante los diagramas de casos de uso, análisis, diseño, implementación y pruebas. La arquitectura se documenta con vistas lógica y de despliegue, mientras que la implementación se organiza en capas y paquetes.")

    b.h2("2.3 UML 2.5")
    b.p("UML es un lenguaje de modelado que proporciona notaciones para visualizar, especificar, construir y documentar artefactos de software. En este proyecto se emplean diagramas de casos de uso, actividad, clases, secuencia, estados, componentes, despliegue y entidad-relación; la organización en paquetes se representa mediante la tabla de la sección 4.4. Los diagramas se elaboran con PlantUML y se integran al documento como figuras numeradas.")
    b.table(
        ["Diagrama", "Tipo", "Uso en el proyecto", "Capítulo"],
        [
            ("Casos de uso", "Comportamiento", "Delimitar actores y servicios; tres vistas por paquete.", "4"),
            ("Actividad", "Comportamiento", "Modelar el flujo de reserva y atención.", "3"),
            ("Clases", "Estructura", "Representar el dominio y las clases de análisis.", "5"),
            ("Secuencia", "Comportamiento", "Detallar la interacción entre actores y componentes.", "5"),
            ("Estados", "Comportamiento", "Modelar el ciclo de vida de la cita.", "5"),
            ("Componentes", "Estructura", "Describir la arquitectura lógica y los servicios.", "6"),
            ("Despliegue", "Estructura", "Ubicar nodos, contenedores y base de datos.", "6"),
            ("Paquetes", "Estructura", "Agrupar casos de uso por módulo funcional.", "4 (tabla)"),
            ("Entidad-relación", "Estructura", "Modelar el esquema relacional del núcleo y del dominio clínico.", "6"),
        ],
        [3.2, 2.8, 8.2, 1.8],
        caption="Diagramas UML utilizados",
        font_size=8.2,
        align_center_cols={1, 3},
    )
    b.p("La tabla siguiente contrasta la cobertura de tipos UML de este PUDS con la del documento de referencia PROYECTOCOMPLETO1. El objetivo es verificar que se conserva la notación adoptada y que la distribución por capítulos es equivalente, sin trasladar a este documento la estructura de módulos de la referencia.")
    b.table(
        ["Tipo UML", "Este PUDS", "PROYECTOCOMPLETO1", "Observación"],
        [
            ("Casos de uso", "3 diagramas (cap. 4)", "5 diagramas (figs. 7–11)", "Se conserva la notación; la agrupación por paquete se documenta en la tabla 4.4."),
            ("Paquetes", "Tabla 4.4", "1 diagrama (fig. 12)", "Se mantiene la agrupación lógica; no se añade una figura independiente."),
            ("Clases", "3 diagramas (cap. 5)", "3 diagramas (figs. 29–31)", "Cobertura equivalente: análisis y dominio."),
            ("Entidad-relación", "2 diagramas (cap. 6)", "4 diagramas (figs. 32–35)", "Se prioriza el núcleo y el modelo clínico."),
            ("Secuencia", "5 diagramas (cap. 5)", "3 diagramas (figs. 36–38)", "Mayor detalle en login, reserva, kiosco, cancelación y consulta."),
            ("Actividad", "1 diagrama (cap. 3)", "3 diagramas (figs. 4–6)", "Se documenta el proceso principal de reserva y atención."),
            ("Estados", "1 diagrama (cap. 5)", "3 diagramas (figs. 39–41)", "Se modela el ciclo de vida de la cita."),
            ("Componentes y despliegue", "2 diagramas (cap. 6)", "2 diagramas (figs. 27–28)", "Cobertura equivalente."),
        ],
        [3.2, 3.2, 3.8, 5.8],
        caption="Comparación de tipos de diagrama UML con PROYECTOCOMPLETO1",
        font_size=7.9,
    )

    b.h2("2.4 Arquitectura web MVC")
    b.p("El patrón Modelo-Vista-Controlador separa la presentación, la lógica de negocio y el acceso a datos. El router frontal recibe la petición, el middleware valida sesión y rol, el controlador coordina la operación, el servicio aplica reglas y transacciones, el modelo ejecuta SQL parametrizado y la vista renderiza HTML o responde JSON. Esta separación facilita el mantenimiento, las pruebas y la evolución del sistema.")
    b.code("""Petición HTTP
  └─ Router frontal (public/index.php)
       └─ Middleware (auth, roles, kiosco)
            └─ Controller
                 └─ Service (reglas y transacciones)
                      └─ Model (PDO)
                           └─ PostgreSQL (RLS)""")

    b.h2("2.5 PostgreSQL y seguridad a nivel de fila")
    b.p("PostgreSQL 16 aporta transacciones ACID, tipos enumerados, JSONB, índices parciales y políticas de seguridad a nivel de fila (RLS). RLS permite que una misma aplicación consulte datos distintos según el rol y el usuario de sesión, sin duplicar la lógica de filtrado en cada consulta. El sistema utiliza un rol de aplicación sin privilegios de superusuario y establece el contexto mediante `SET LOCAL`.")
    b.p("La integridad de las citas se refuerza con un índice único parcial sobre `(medico_id, fecha, hora_inicio)` para las citas no canceladas, además de una validación por rango en el servicio para duraciones variables. Las operaciones críticas se ejecutan dentro de transacciones con bloqueo pesimista.")

    b.h2("2.6 Seguridad de aplicaciones web")
    b.p("El diseño incorpora controles alineados con OWASP: validación de entrada en cliente y servidor, consultas parametrizadas, escape de salida, tokens CSRF, cookies HttpOnly/Secure/SameSite, hash de contraseñas con `password_hash()`, bloqueo de cuentas, tokens de recuperación de un solo uso, control de acceso por rol y registro de auditoría. El kiosco utiliza un token HMAC de corta duración y no accede a datos clínicos ni financieros.")
    b.table(
        ["Riesgo", "Control adoptado"],
        [
            ("Inyección SQL", "PDO con sentencias preparadas y validación de tipos."),
            ("XSS", "Escape de salida con `htmlspecialchars()` y políticas de contenido."),
            ("CSRF", "Token por sesión en formularios y peticiones que mutan estado."),
            ("Fuerza bruta", "Bloqueo tras tres intentos fallidos y desbloqueo administrativo."),
            ("Escalada de privilegios", "Middleware de roles y políticas RLS."),
            ("Exposición de datos", "Kiosco restringido y página pública con código de acceso."),
            ("Pérdida de trazabilidad", "Auditoría con IP y datos antes/después."),
        ],
        [4.2, 11.8],
        caption="Riesgos de seguridad y controles",
    )

    b.h2("2.7 Pruebas de software")
    b.p("Las pruebas verifican que el sistema cumple los requisitos y que los defectos se detectan antes de la entrega. Se aplican pruebas unitarias sobre servicios y validaciones, pruebas de integración sobre endpoints y base de datos, pruebas funcionales sobre los flujos de negocio, pruebas de seguridad y pruebas de concurrencia sobre la reserva de citas. Cada caso de prueba se vincula con un requisito y con un resultado esperado.")
    b.p("El flujo específico de pruebas se presenta en el capítulo 8, junto con los niveles, casos y criterios de aceptación.")


def chapter3():
    b.h1("3. MODELO DE NEGOCIO")
    b.h2("3.1 Descripción del negocio")
    b.p("La clínica ofrece consultas ambulatorias en múltiples especialidades. El paciente solicita una cita, recepción verifica la disponibilidad, asigna un médico y registra la reserva. El día de la atención, el paciente se identifica, se emite una ficha y el médico realiza la consulta. Si corresponde, se generan recetas, documentos u órdenes de estudio, y se registra el pago. La clínica necesita además reportes de demanda, atención y productividad.")
    b.p("El modelo de negocio se transforma con un canal digital de reserva y un kiosco presencial. Ambos canales comparten la misma agenda y respetan una distribución de cupos 50/50 por franja. La recepción conserva la capacidad de reprogramar, cancelar y crear reconsultas prioritarias cuando la operación lo requiera.")

    b.h2("3.2 Procesos actuales y propuestos")
    b.table(
        ["Proceso", "Situación actual (AS-IS)", "Situación propuesta (TO-BE)"],
        [
            ("Reserva", "Teléfono o presencial, agenda física.", "Web, kiosco o recepción con disponibilidad validada en servidor."),
            ("Confirmación", "Llamada o anotación manual.", "Ficha con código y QR; correo de confirmación."),
            ("Cancelación", "Aviso telefónico sin control de plazo.", "Cancelación hasta el día anterior, con motivo y liberación del cupo."),
            ("Atención", "Ficha de papel y búsqueda manual.", "Ficha digital, historia clínica y estados de cita."),
            ("Pedidos", "Entrega física de recetas u órdenes.", "Enlace de WhatsApp con código de acceso."),
            ("Reportes", "Hojas de cálculo dispersas.", "Dashboards y reportes consolidados por rol."),
        ],
        [3.0, 6.3, 6.7],
        caption="Comparación de procesos AS-IS y TO-BE",
        font_size=8.3,
    )

    b.h2("3.3 Proceso de negocio")
    b.p("El proceso principal inicia con la necesidad del paciente y termina con la atención y el registro clínico. Las actividades de validación de elegibilidad, disponibilidad, cupo y superposición son puntos de control obligatorios. La emisión de la ficha confirma la cita y habilita la atención.")
    b.figure("02_actividad_negocio.png", 15.8, "Diagrama de actividad del proceso de reserva y atención")

    b.h2("3.4 Reglas de negocio")
    b.table(
        ["Código", "Regla"],
        [
            ("RN01", "El paciente posee un identificador único por CI."),
            ("RN02", "El médico posee código y registro profesional únicos."),
            ("RN03", "Toda cita se asocia a paciente, médico y especialidad válidos."),
            ("RN04", "Solo médicos activos reciben citas nuevas."),
            ("RN05", "La cita debe pertenecer al horario de atención del médico."),
            ("RN06", "No se permiten citas en fechas u horas pasadas."),
            ("RN07", "Un médico no puede tener citas superpuestas."),
            ("RN08", "La duración de la cita se configura en bloques de 30 minutos."),
            ("RN09", "Las citas canceladas liberan el cupo."),
            ("RN10", "Las citas atendidas conservan su registro histórico."),
            ("RN11", "Pacientes y médicos con historial se desactivan, no se eliminan."),
            ("RN12", "Solo los roles autorizados ejecutan cada operación."),
            ("RN13", "La disponibilidad se revalida en el servidor."),
            ("RN14", "La persistencia se realiza en PostgreSQL."),
        ],
        [2.0, 14.0],
        caption="Reglas de negocio del enunciado",
    )
    b.table(
        ["Código", "Regla de ampliación"],
        [
            ("RX01", "Cupos 50% web y 50% kiosco por franja de horario."),
            ("RX02", "Restricción de sexo por especialidad; ginecología solo femenino."),
            ("RX03", "Restricción de edad: pediatría 0–12 y geriatría 60+."),
            ("RX04", "Cancelación solo hasta el día anterior a la cita."),
            ("RX05", "Reconsulta por ausencia del médico con prioridad."),
            ("RX06", "Reconsulta de seguimiento sugerida a los siete días."),
            ("RX07", "La reconsulta puede ocupar cupo aunque el canal esté agotado."),
            ("RX08", "La ficha es el ticket de la cita; sin ficha no hay atención."),
            ("RX09", "Bloqueo de login tras tres intentos fallidos."),
            ("RX10", "Recuperación de contraseña con token temporal de 30 minutos."),
            ("RX11", "Pedidos enviados por WhatsApp con enlace y código de acceso."),
            ("RX12", "Paciente de kiosco existe sin cuenta y la activa con CI y teléfono."),
            ("RX13", "Auditoría con IP y datos antes/después en catálogos."),
        ],
        [2.0, 14.0],
        caption="Reglas de ampliación acordadas",
    )

    b.h2("3.5 Propuesta de valor")
    b.bullets([
        ("Eficiencia operativa: ", "menos llamadas y registros manuales, agenda única y validaciones automáticas."),
        ("Confiabilidad: ", "prevención de superposiciones y control de cupos en tiempo real."),
        ("Autoservicio: ", "reserva web y kiosco para pacientes, con fichas digitales."),
        ("Trazabilidad: ", "historial de estados, auditoría de catálogos y códigos únicos."),
        ("Continuidad clínica: ", "historia clínica, recetas, órdenes y resultados asociados al paciente."),
        ("Toma de decisiones: ", "reportes de demanda, atención, cancelaciones y productividad."),
    ])

    b.h2("3.6 Canales y puntos de contacto")
    b.table(
        ["Canal", "Usuario", "Función"],
        [
            ("Landing pública", "Paciente / visitante", "Información, especialidades, médicos y reserva web."),
            ("Portal del paciente", "Paciente", "Mis citas, historial, perfil y cancelación."),
            ("Kiosco", "Paciente presencial", "Identificación, reserva y emisión de ficha."),
            ("Panel administrativo", "Admin / recepción", "Catálogos, agenda, citas, reportes y auditoría."),
            ("Panel médico", "Médico", "Agenda, consulta, historia, pedidos y pagos."),
            ("WhatsApp", "Paciente", "Acceso a recetas, documentos y órdenes mediante enlace."),
        ],
        [3.4, 4.2, 8.4],
        caption="Canales del sistema",
    )


def chapter4():
    b.h1("4. CAPTURA DE REQUISITOS")
    b.h2("4.1 Técnicas de elicitación")
    b.bullets([
        ("Entrevistas: ", "con la dirección, recepción y médicos para comprender el flujo real de atención."),
        ("Observación: ", "del proceso de reserva, confirmación, emisión de ficha y cierre de consulta."),
        ("Análisis documental: ", "revisión de la especificación del examen y de los documentos operativos."),
        ("Prototipación: ", "pantallas de reserva, kiosco y agenda para validar la interacción."),
        ("Talleres de validación: ", "revisión de reglas de negocio y criterios de aceptación con los interesados."),
    ])

    b.h2("4.2 Requisitos funcionales")
    b.table(
        ["ID", "Requisito", "Prioridad"],
        [
            ("RF01", "Autenticación, control de acceso por roles, bloqueo y recuperación de contraseña.", "Alta"),
            ("RF02", "Gestión de pacientes con CI única, perfil e historial.", "Alta"),
            ("RF03", "Gestión de médicos, especialidades asignadas y QR profesional.", "Alta"),
            ("RF04", "Gestión de especialidades con restricciones de sexo y edad.", "Alta"),
            ("RF05", "Horarios, cupos web/kiosco, bloqueos y cálculo de disponibilidad.", "Alta"),
            ("RF06", "Registro de citas con validación de disponibilidad.", "Alta"),
            ("RF07", "Prevención de citas duplicadas, superpuestas y fuera de horario.", "Alta"),
            ("RF08", "Reprogramación de citas programadas o confirmadas.", "Alta"),
            ("RF09", "Cancelación con motivo y plazo mínimo.", "Alta"),
            ("RF10", "Estados, asistencia, inasistencia y reconsultas.", "Alta"),
            ("RF11", "Agenda médica e historial del paciente.", "Media"),
            ("RF12", "Historia clínica, recetas, documentos y órdenes.", "Media"),
            ("RF13", "Reportes y dashboards operativos.", "Media"),
        ],
        [2.0, 12.8, 2.2],
        caption="Requisitos funcionales del sistema",
        align_center_cols={0, 2},
    )

    b.h2("4.3 Requisitos no funcionales")
    b.table(
        ["ID", "Categoría", "Requisito"],
        [
            ("RNF01", "Usabilidad", "Interfaz responsive, clara y consistente; kiosco touch-friendly."),
            ("RNF02", "Rendimiento", "Respuesta menor a 2 s en consultas habituales y disponibilidad en menos de 1 s."),
            ("RNF03", "Seguridad", "Hash de contraseñas, CSRF, RLS, cookies seguras y control por rol."),
            ("RNF04", "Disponibilidad", "Operación continua en horario de atención; despliegue con HTTPS."),
            ("RNF05", "Integridad", "Transacciones y restricciones para evitar citas superpuestas."),
            ("RNF06", "Mantenibilidad", "Arquitectura MVC, separación por capas y convenciones de código."),
            ("RNF07", "Portabilidad", "PHP 8.1+, PostgreSQL 16 y navegadores modernos."),
            ("RNF08", "Escalabilidad", "Cupos y agenda preparados para crecimiento de médicos y sedes."),
            ("RNF09", "Auditabilidad", "Registro de cambios de catálogos con IP y datos antes/después."),
            ("RNF10", "Compatibilidad", "Landing y kiosco en iPad, tablet y navegador de escritorio."),
        ],
        [2.0, 3.0, 11.0],
        caption="Requisitos no funcionales",
        align_center_cols={0},
    )

    b.h2("4.4 Paquetes de casos de uso")
    b.p("Los casos de uso se agrupan en tres paquetes: acceso público y reserva web (UC01–UC07), operación de citas y agenda (UC08–UC14) y atención médica y administración (UC15–UC24). La numeración es única y se mantiene consistente entre diagramas, especificaciones y matriz de trazabilidad.")
    b.table(
        ["Paquete", "Casos de uso", "Actores principales"],
        [
            ("Acceso público", "UC01–UC07", "Paciente, público"),
            ("Citas y agenda", "UC08–UC14", "Recepcionista, recepcionista de médico, médico"),
            ("Atención y administración", "UC15–UC24", "Médico, recepción, administrador, kiosco"),
        ],
        [3.2, 4.8, 8.0],
        caption="Paquetes de casos de uso",
    )

    b.figure("01_casos_uso_publico.png", 15.5, "Casos de uso del acceso público y reserva web")
    b.figure("01b_casos_uso_citas.png", 15.5, "Casos de uso de operación de citas y agenda")
    b.figure("01c_casos_uso_atencion.png", 15.8, "Casos de uso de atención médica y administración", max_height_cm=23.0)

    b.h2("4.5 Catálogo de casos de uso")
    b.table(
        ["ID", "Caso de uso", "Actor principal", "RF"],
        [
            ("UC01", "Navegar por la landing", "Público", "—"),
            ("UC02", "Buscar especialidad y médico", "Público", "RF03, RF04"),
            ("UC03", "Registrarse o activar cuenta", "Paciente", "RF01"),
            ("UC04", "Iniciar sesión", "Todos", "RF01"),
            ("UC05", "Reservar cita web", "Paciente", "RF05–RF07"),
            ("UC06", "Cancelar cita", "Paciente", "RF09"),
            ("UC07", "Consultar mis citas e historial", "Paciente", "RF11–RF12"),
            ("UC08", "Gestionar pacientes", "Recepcionista", "RF02"),
            ("UC09", "Programar cita", "Recepcionista", "RF06–RF07"),
            ("UC10", "Reprogramar cita", "Recepcionista", "RF08"),
            ("UC11", "Cancelar cita en recepción", "Recepcionista", "RF09"),
            ("UC12", "Controlar asistencia", "Recepción / médico", "RF10"),
            ("UC13", "Gestionar reconsultas", "Recepción / médico", "RF10"),
            ("UC14", "Consultar agenda e historial", "Recepción / médico", "RF11"),
            ("UC15", "Emitir ficha en kiosco", "Kiosco / recepción", "RF05–RF07"),
            ("UC16", "Registrar consulta", "Médico", "RF12"),
            ("UC17", "Generar receta, documento u orden", "Médico", "RF12"),
            ("UC18", "Enviar pedido por WhatsApp", "Médico / recepción", "RF12"),
            ("UC19", "Registrar pago", "Médico / recepción", "RF13"),
            ("UC20", "Gestionar médicos y especialidades", "Administrador", "RF03–RF04"),
            ("UC21", "Configurar horarios y cupos", "Administrador", "RF05"),
            ("UC22", "Gestionar usuarios", "Administrador", "RF01"),
            ("UC23", "Generar reportes", "Administrador", "RF13"),
            ("UC24", "Auditar catálogos", "Administrador", "RNF09"),
        ],
        [1.8, 6.6, 5.4, 3.2],
        caption="Catálogo completo de casos de uso",
        font_size=8.0,
        align_center_cols={0},
    )

    b.h3("4.5.1 Priorización de casos de uso por importancia y riesgo")
    b.p("La priorización combina dos ejes: la importancia, que mide el grado en que el caso de uso sostiene la operación o la seguridad del paciente, y el riesgo, que estima el daño clínico, económico o legal que produciría una falla o un uso indebido. La combinación da lugar a cuatro niveles: P1 Crítica, P2 Alta, P3 Media y P4 Baja. La tabla se ordena por prioridad y conserva el código para mantener la trazabilidad con el catálogo y con los paquetes PK-01 (acceso público), PK-02 (citas y agenda) y PK-03 (atención y administración).")
    b.table(
        ["Prioridad", "Código", "Paquete", "Caso de uso", "Importancia", "Riesgo", "Fundamento"],
        [
            ("P1 Crítica", "UC04", "PK-01", "Iniciar sesión", "Alta", "Crítico", "Puerta de entrada a datos clínicos y administrativos."),
            ("P1 Crítica", "UC05", "PK-01", "Reservar cita web", "Alta", "Crítico", "Reserva transaccional; evita duplicados y sobrecupos."),
            ("P1 Crítica", "UC08", "PK-02", "Gestionar pacientes", "Alta", "Alto", "Maestro de pacientes; base de citas e historia clínica."),
            ("P1 Crítica", "UC09", "PK-02", "Programar cita", "Alta", "Crítico", "Núcleo de la operación; evita superposiciones."),
            ("P1 Crítica", "UC15", "PK-03", "Emitir ficha en kiosco", "Alta", "Crítico", "La ficha habilita la atención y confirma la cita."),
            ("P1 Crítica", "UC16", "PK-03", "Registrar consulta", "Alta", "Crítico", "Documento clínico y evidencia de la atención."),
            ("P1 Crítica", "UC17", "PK-03", "Generar receta, documento u orden", "Alta", "Alto", "Documento clínico con implicación legal."),
            ("P1 Crítica", "UC19", "PK-03", "Registrar pago", "Alta", "Crítico", "Impacto económico directo y cierre de cuenta."),
            ("P1 Crítica", "UC20", "PK-03", "Gestionar médicos y especialidades", "Alta", "Alto", "Catálogos que habilitan la agenda y la elegibilidad."),
            ("P1 Crítica", "UC21", "PK-03", "Configurar horarios y cupos", "Alta", "Alto", "Define la disponibilidad y el reparto 50/50."),
            ("P1 Crítica", "UC22", "PK-03", "Gestionar usuarios", "Alta", "Crítico", "Controla el acceso y los privilegios por rol."),
            ("P2 Alta", "UC12", "PK-02", "Controlar asistencia", "Alta", "Medio", "Afecta estados, reconsultas e indicadores."),
            ("P2 Alta", "UC13", "PK-02", "Gestionar reconsultas", "Alta", "Medio", "Prioriza la continuidad de la atención."),
            ("P2 Alta", "UC14", "PK-02", "Consultar agenda e historial", "Alta", "Medio", "Soporte a la decisión clínica y operativa."),
            ("P3 Media", "UC03", "PK-01", "Registrarse o activar cuenta", "Media", "Medio", "Habilita el acceso del paciente sin datos clínicos."),
            ("P3 Media", "UC06", "PK-01", "Cancelar cita", "Media", "Medio", "Libera cupo; requiere control de plazo."),
            ("P3 Media", "UC07", "PK-01", "Consultar mis citas e historial", "Media", "Medio", "Consulta de solo lectura para el paciente."),
            ("P3 Media", "UC10", "PK-02", "Reprogramar cita", "Media", "Medio", "Revalida disponibilidad sin perder historial."),
            ("P3 Media", "UC11", "PK-02", "Cancelar cita en recepción", "Media", "Medio", "Libera cupo con registro de motivo."),
            ("P3 Media", "UC18", "PK-03", "Enviar pedido por WhatsApp", "Media", "Medio", "Notificación externa; no altera datos clínicos."),
            ("P3 Media", "UC23", "PK-03", "Generar reportes", "Media", "Medio", "Indicadores para la dirección; no opera la agenda."),
            ("P3 Media", "UC24", "PK-03", "Auditar catálogos", "Media", "Medio", "Trazabilidad exigible; no bloquea la operación."),
            ("P4 Baja", "UC01", "PK-01", "Navegar por la landing", "Baja", "Bajo", "Informativo; no altera datos."),
            ("P4 Baja", "UC02", "PK-01", "Buscar especialidad y médico", "Baja", "Bajo", "Orientativo; solo lectura pública."),
        ],
        [1.9, 1.3, 1.5, 4.0, 1.8, 1.5, 4.0],
        caption="Priorización de los casos de uso por importancia y riesgo",
        font_size=7.6,
        align_center_cols={0, 1, 2, 4, 5},
        row_fills=["F4B6B6"] * 11 + ["F8CBAD"] * 3 + ["FFE699"] * 8 + ["C6E0B4"] * 2,
    )
    b.p("El sombreado de cada fila codifica la prioridad: rojo para P1 Crítica, naranja para P2 Alta, amarillo para P3 Media y verde para P4 Baja. El resultado es 11 casos de uso P1 Crítica, 3 P2 Alta, 8 P3 Media y 2 P4 Baja. Los casos P1 se construyen y prueban primero dentro de cada ciclo y concentran la mayor cobertura de pruebas; los casos P3 y P4 se incorporan según la dependencia funcional y el avance de la operación. La prioridad no altera el catálogo: los 24 casos de uso se conservan sin omisiones ni duplicados y solo se reordenan para planificar la entrega.")

    b.h2("4.6 Especificación de casos de uso críticos")
    b.h3("4.6.1 UC04 — Iniciar sesión")
    b.kv_table([
        ("Actor principal", "Usuario interno o paciente."),
        ("Precondición", "El usuario existe y la cuenta está activa."),
        ("Flujo principal", "1. Ingresa CI/usuario y contraseña. 2. El sistema valida credenciales. 3. Restablece intentos fallidos. 4. Crea la sesión y redirige según el rol."),
        ("Flujo alternativo", "Credenciales inválidas: incrementa el contador; al tercer intento bloquea la cuenta. Cuenta bloqueada o inactiva: muestra mensaje y no permite el acceso."),
        ("Postcondición", "Sesión iniciada con el rol correspondiente y último acceso actualizado."),
    ], caption="Especificación de UC04")

    b.h3("4.6.2 UC05 — Reservar cita web")
    b.kv_table([
        ("Actor principal", "Paciente."),
        ("Precondición", "Paciente autenticado, especialidad y médico activos."),
        ("Flujo principal", "1. Selecciona especialidad. 2. Selecciona médico. 3. Elige fecha y slot. 4. Ingresa motivo. 5. El servidor revalida elegibilidad, horario, cupo y superposición. 6. Registra la cita y genera el código."),
        ("Flujo alternativo", "Sin cupo, fuera de horario, fecha pasada o superposición: rechaza con mensaje y sugiere otro slot."),
        ("Postcondición", "Cita creada en estado programada, con origen web y código único."),
    ], caption="Especificación de UC05")

    b.h3("4.6.3 UC09 — Programar cita en recepción")
    b.kv_table([
        ("Actor principal", "Recepcionista."),
        ("Precondición", "Paciente registrado y médico activo con horario vigente."),
        ("Flujo principal", "1. Busca al paciente. 2. Selecciona especialidad, médico, fecha y hora. 3. Confirma. 4. El sistema ejecuta la transacción de reserva. 5. Genera código y ficha."),
        ("Flujo alternativo", "El paciente no existe: se registra previamente. Slot ocupado: se ofrece otro horario. Especialidad no elegible: se bloquea la operación."),
        ("Postcondición", "Cita registrada y disponible para emisión de ficha."),
    ], caption="Especificación de UC09")

    b.h3("4.6.4 UC10 — Reprogramar cita")
    b.kv_table([
        ("Actor principal", "Recepcionista."),
        ("Precondición", "Cita en estado programada o confirmada."),
        ("Flujo principal", "1. Busca la cita. 2. Selecciona nueva fecha y hora. 3. El sistema revalida disponibilidad. 4. Actualiza la cita y notifica al paciente."),
        ("Flujo alternativo", "Estado no permitido o slot ocupado: rechaza la operación sin modificar la cita."),
        ("Postcondición", "Cita reprogramada, historial conservado y cupo anterior liberado."),
    ], caption="Especificación de UC10")

    b.h3("4.6.5 UC15 — Emitir ficha en kiosco")
    b.kv_table([
        ("Actor principal", "Kiosco; paciente como usuario."),
        ("Precondición", "Token de kiosco válido y cita reservada o por reservar."),
        ("Flujo principal", "1. Paciente ingresa su CI. 2. El sistema identifica o registra al paciente. 3. Selecciona especialidad, médico y slot. 4. Confirma la reserva. 5. Se emite la ficha con código, acceso y QR. 6. La cita pasa a confirmada."),
        ("Flujo alternativo", "CI inválida, sin cupo kiosco o especialidad no elegible: muestra mensaje y permite reintentar."),
        ("Postcondición", "Ficha emitida y cita confirmada; el paciente puede ser atendido."),
    ], caption="Especificación de UC15")

    b.h3("4.6.6 UC16 — Registrar consulta")
    b.kv_table([
        ("Actor principal", "Médico."),
        ("Precondición", "Cita confirmada y ficha emitida."),
        ("Flujo principal", "1. Abre la cita del día. 2. Registra motivo, anamnesis, signos vitales, examen, diagnóstico, tratamiento e indicaciones. 3. Guarda la consulta. 4. Marca la cita como atendida."),
        ("Flujo alternativo", "Cita no confirmada o de otro médico: el sistema impide el registro."),
        ("Postcondición", "Consulta almacenada y cita atendida; se habilitan recetas y órdenes."),
    ], caption="Especificación de UC16")

    b.h2("4.7 Matriz de trazabilidad requisito–caso de uso")
    b.table(
        ["RF", "Casos de uso"],
        [
            ("RF01", "UC03, UC04, UC22"),
            ("RF02", "UC08"),
            ("RF03", "UC02, UC20"),
            ("RF04", "UC02, UC20"),
            ("RF05", "UC05, UC09, UC15, UC21"),
            ("RF06", "UC05, UC09, UC15"),
            ("RF07", "UC05, UC09, UC15"),
            ("RF08", "UC10"),
            ("RF09", "UC06, UC11"),
            ("RF10", "UC12, UC13"),
            ("RF11", "UC07, UC14"),
            ("RF12", "UC07, UC16, UC17, UC18"),
            ("RF13", "UC19, UC23"),
        ],
        [2.5, 13.5],
        caption="Trazabilidad entre requisitos funcionales y casos de uso",
        align_center_cols={0},
    )

    b.h2("4.8 Supuestos y restricciones")
    b.bullets([
        ("Supuesto: ", "la clínica dispone de conectividad estable y de un iPad o tablet para el kiosco."),
        ("Supuesto: ", "los médicos y recepcionistas cuentan con credenciales y capacitación básica."),
        ("Restricción: ", "PHP 8.1+ sin framework, PostgreSQL 16 y despliegue en hosting cPanel."),
        ("Restricción: ", "la base de datos productiva se aloja en Neon o Railway y requiere `pdo_pgsql`."),
        ("Restricción: ", "el envío por WhatsApp es un enlace profundo, no una integración con la API Business."),
        ("Restricción: ", "la verificación facial es simulada; la identidad se valida por CI."),
    ])
