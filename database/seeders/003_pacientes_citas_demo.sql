-- 003 — Pacientes, citas y fichas demo
-- Idempotente por códigos/CI y por el índice único de slot.

INSERT INTO pacientes (
    codigo, usuario_id, ci, nombres, apellidos, fecha_nacimiento, sexo,
    telefono, email, direccion, tipo_sangre, alergias, enfermedades_cronicas,
    medicamentos, antecedentes, emergencia_nombre, emergencia_parentesco,
    emergencia_telefono
) VALUES
    (
        'PAC-DEMO-001',
        (SELECT id FROM usuarios WHERE username = '12345678'),
        '10000001',
        'Juan',
        'Pérez',
        '1985-05-12',
        'masculino',
        '70020001',
        'juan.perez@example.test',
        'Av. América #123',
        'O+',
        'Penicilina',
        'Hipertensión arterial',
        'Losartán 50 mg',
        'Apéndice extirpada en 2010',
        'María Pérez',
        'Esposa',
        '70030001'
    ),
    (
        'PAC-DEMO-002',
        NULL,
        '10000002',
        'María',
        'Gómez',
        '1992-08-23',
        'femenino',
        '70020002',
        'maria.gomez@example.test',
        'Calle Sucre #456',
        'A+',
        NULL,
        NULL,
        NULL,
        NULL,
        'Pedro Gómez',
        'Hermano',
        '70030002'
    ),
    (
        'PAC-DEMO-003',
        NULL,
        '10000003',
        'Luis',
        'Niño',
        '2015-03-04',
        'masculino',
        '70020003',
        'luis.nino@example.test',
        'Barrio Lindo #789',
        'B+',
        'Ninguna conocida',
        NULL,
        NULL,
        'Asma leve en la infancia',
        'Ana Niño',
        'Madre',
        '70030003'
    ),
    (
        'PAC-DEMO-004',
        NULL,
        '10000004',
        'Rosa',
        'Villarroel',
        '1950-11-30',
        'femenino',
        '70020004',
        'rosa.villarroel@example.test',
        'Av. Busch #321',
        'AB+',
        NULL,
        'Diabetes tipo 2',
        'Metformina 850 mg',
        'Artrosis de rodilla',
        'Carmen Villarroel',
        'Hija',
        '70030004'
    )
ON CONFLICT DO NOTHING;

INSERT INTO citas (
    codigo, paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
    estado, origen, es_prioritaria, es_reconsulta, motivo
)
SELECT
    'CM-DEMO-001', p.id, m.id, e.id, CURRENT_DATE - 7, TIME '09:00', TIME '09:30',
    'atendida', 'recepcion', FALSE, FALSE, 'Control de presión arterial'
FROM pacientes p, medicos m, especialidades e
WHERE p.codigo = 'PAC-DEMO-001'
  AND m.codigo = 'MED-DEMO-001'
  AND e.nombre = 'Medicina General'
ON CONFLICT DO NOTHING;

INSERT INTO citas (
    codigo, paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
    estado, origen, es_prioritaria, es_reconsulta, motivo
)
SELECT
    'CM-DEMO-002', p.id, m.id, e.id, CURRENT_DATE - 5, TIME '10:00', TIME '10:30',
    'atendida', 'kiosco', FALSE, FALSE, 'Control pediátrico'
FROM pacientes p, medicos m, especialidades e
WHERE p.codigo = 'PAC-DEMO-003'
  AND m.codigo = 'MED-DEMO-002'
  AND e.nombre = 'Pediatría'
ON CONFLICT DO NOTHING;

INSERT INTO citas (
    codigo, paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
    estado, origen, es_prioritaria, es_reconsulta, motivo
)
SELECT
    'CM-DEMO-003', p.id, m.id, e.id, CURRENT_DATE + 1, TIME '08:00', TIME '08:30',
    'confirmada', 'web', FALSE, FALSE, 'Consulta de seguimiento'
FROM pacientes p, medicos m, especialidades e
WHERE p.codigo = 'PAC-DEMO-001'
  AND m.codigo = 'MED-DEMO-001'
  AND e.nombre = 'Medicina General'
ON CONFLICT DO NOTHING;

INSERT INTO citas (
    codigo, paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
    estado, origen, es_prioritaria, es_reconsulta, motivo
)
SELECT
    'CM-DEMO-004', p.id, m.id, e.id, CURRENT_DATE + 2, TIME '09:00', TIME '09:30',
    'programada', 'recepcion', FALSE, FALSE, 'Evaluación geriátrica'
FROM pacientes p, medicos m, especialidades e
WHERE p.codigo = 'PAC-DEMO-002'
  AND m.codigo = 'MED-DEMO-003'
  AND e.nombre = 'Geriatría'
ON CONFLICT DO NOTHING;

INSERT INTO citas (
    codigo, paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
    estado, origen, es_prioritaria, es_reconsulta, motivo
)
SELECT
    'CM-DEMO-005', p.id, m.id, e.id, CURRENT_DATE + 3, TIME '09:00', TIME '09:30',
    'programada', 'kiosco', FALSE, FALSE, 'Control de movilidad'
FROM pacientes p, medicos m, especialidades e
WHERE p.codigo = 'PAC-DEMO-004'
  AND m.codigo = 'MED-DEMO-003'
  AND e.nombre = 'Geriatría'
ON CONFLICT DO NOTHING;

INSERT INTO fichas (codigo, cita_id, canal, codigo_acceso, verificacion_biometrica)
SELECT 'FIC-DEMO-001', c.id, 'web', '1234', FALSE
FROM citas c
WHERE c.codigo = 'CM-DEMO-003'
ON CONFLICT DO NOTHING;

INSERT INTO fichas (codigo, cita_id, canal, codigo_acceso, verificacion_biometrica)
SELECT 'FIC-DEMO-002', c.id, 'kiosco', '5678', TRUE
FROM citas c
WHERE c.codigo = 'CM-DEMO-004'
ON CONFLICT DO NOTHING;

INSERT INTO fichas (codigo, cita_id, canal, codigo_acceso, verificacion_biometrica)
SELECT 'FIC-DEMO-003', c.id, 'kiosco', '0000', FALSE
FROM citas c
WHERE c.codigo = 'CM-DEMO-001'
ON CONFLICT DO NOTHING;
