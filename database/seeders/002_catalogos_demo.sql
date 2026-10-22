-- 002 — Catálogos demo: especialidades, médicos, asignaciones y horarios
-- Idempotente: se puede ejecutar varias veces sin duplicar datos.

INSERT INTO especialidades (
    nombre, descripcion, sexo_permitido, edad_min, edad_max, destacada, icono
) VALUES
    ('Medicina General', 'Atención integral para adultos y familias.', 'ambos', NULL, NULL, TRUE, 'stethoscope'),
    ('Pediatría', 'Atención de niños y adolescentes.', 'ambos', 0, 17, TRUE, 'child'),
    ('Geriatría', 'Atención integral de personas mayores.', 'ambos', 60, NULL, TRUE, 'elderly'),
    ('Cardiología', 'Evaluación y seguimiento cardiovascular.', 'ambos', NULL, NULL, FALSE, 'heart'),
    ('Ginecología', 'Atención de salud femenina.', 'femenino', 12, NULL, FALSE, 'female')
ON CONFLICT DO NOTHING;

INSERT INTO medicos (
    codigo, usuario_id, registro_profesional, nombres, apellidos, telefono, email, duracion_cita_min
) VALUES
    (
        'MED-DEMO-001',
        (SELECT id FROM usuarios WHERE username = 'medico'),
        'MP-1001',
        'Carlos',
        'Méndez',
        '70010001',
        'carlos.mendez@vidasalud.test',
        30
    ),
    (
        'MED-DEMO-002',
        NULL,
        'MP-1002',
        'Lucía',
        'Rojas',
        '70010002',
        'lucia.rojas@vidasalud.test',
        30
    ),
    (
        'MED-DEMO-003',
        NULL,
        'MP-1003',
        'Jorge',
        'Salazar',
        '70010003',
        'jorge.salazar@vidasalud.test',
        30
    ),
    (
        'MED-DEMO-004',
        NULL,
        'MP-1004',
        'Andrés',
        'Salinas',
        '70010004',
        'andres.salinas@vidasalud.test',
        30
    )
ON CONFLICT DO NOTHING;

INSERT INTO medico_especialidad (medico_id, especialidad_id)
SELECT m.id, e.id
FROM (VALUES
    ('MED-DEMO-001', 'Medicina General'),
    ('MED-DEMO-001', 'Cardiología'),
    ('MED-DEMO-002', 'Pediatría'),
    ('MED-DEMO-003', 'Geriatría'),
    ('MED-DEMO-003', 'Medicina General'),
    ('MED-DEMO-004', 'Ginecología')
) AS asignacion(medico_codigo, especialidad_nombre)
JOIN medicos m ON m.codigo = asignacion.medico_codigo
JOIN especialidades e ON e.nombre = asignacion.especialidad_nombre
ON CONFLICT DO NOTHING;

INSERT INTO recepcionista_medico (usuario_id, medico_id)
SELECT u.id, m.id
FROM usuarios u
JOIN medicos m ON m.codigo IN ('MED-DEMO-001', 'MED-DEMO-002')
WHERE u.username = 'recepcion.medico'
ON CONFLICT DO NOTHING;

-- Lunes a viernes, mañana y tarde, con cupos 50/50.
INSERT INTO horarios (
    medico_id, dia_semana, hora_inicio, hora_fin, cupos_web, cupos_kiosco
)
SELECT m.id, dia.dia, TIME '08:00', TIME '12:00', 50, 50
FROM medicos m
CROSS JOIN generate_series(1, 5) AS dia(dia)
WHERE m.codigo IN ('MED-DEMO-001', 'MED-DEMO-002', 'MED-DEMO-003', 'MED-DEMO-004')
  AND NOT EXISTS (
      SELECT 1 FROM horarios h
      WHERE h.medico_id = m.id
        AND h.dia_semana = dia.dia
        AND h.hora_inicio = TIME '08:00'
        AND h.hora_fin = TIME '12:00'
  );

INSERT INTO horarios (
    medico_id, dia_semana, hora_inicio, hora_fin, cupos_web, cupos_kiosco
)
SELECT m.id, dia.dia, TIME '14:00', TIME '18:00', 50, 50
FROM medicos m
CROSS JOIN generate_series(1, 5) AS dia(dia)
WHERE m.codigo IN ('MED-DEMO-001', 'MED-DEMO-002', 'MED-DEMO-003', 'MED-DEMO-004')
  AND NOT EXISTS (
      SELECT 1 FROM horarios h
      WHERE h.medico_id = m.id
        AND h.dia_semana = dia.dia
        AND h.hora_inicio = TIME '14:00'
        AND h.hora_fin = TIME '18:00'
  );

INSERT INTO bloqueos (medico_id, fecha, motivo)
SELECT m.id, CURRENT_DATE + 7, 'Capacitación interna'
FROM medicos m
WHERE m.codigo = 'MED-DEMO-001'
  AND NOT EXISTS (
      SELECT 1 FROM bloqueos b
      WHERE b.medico_id = m.id
        AND b.fecha = CURRENT_DATE + 7
        AND b.motivo = 'Capacitación interna'
  );
