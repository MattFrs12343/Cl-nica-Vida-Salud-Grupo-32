-- 004 — Historia clínica, recetas, documentos, órdenes, resultados y pagos demo

INSERT INTO consultas (
    cita_id, paciente_id, medico_id, motivo, anamnesis, signos_vitales,
    examen_fisico, diagnostico, tratamiento, indicaciones, fecha
)
SELECT
    c.id,
    c.paciente_id,
    c.medico_id,
    'Control de presión arterial',
    'Paciente refiere cefalea ocasional y buen apego al tratamiento.',
    '{"pa": "130/85", "fc": 78, "temp": 36.6, "peso": 82.5, "spo2": 98, "talla": 175}'::jsonb,
    'Consciente, orientado, afebril. Ruidos cardíacos rítmicos sin soplos.',
    'Hipertensión arterial leve controlada.',
    'Continuar losartán 50 mg cada 12 horas y dieta hiposódica.',
    'Control en 30 días. Registrar presión arterial en casa.',
    (c.fecha + c.hora_inicio)::timestamptz
FROM citas c
WHERE c.codigo = 'CM-DEMO-001'
ON CONFLICT (cita_id) DO NOTHING;

INSERT INTO consultas (
    cita_id, paciente_id, medico_id, motivo, anamnesis, signos_vitales,
    examen_fisico, diagnostico, tratamiento, indicaciones, fecha
)
SELECT
    c.id,
    c.paciente_id,
    c.medico_id,
    'Control pediátrico',
    'Madre refiere que el niño presenta tos seca nocturna desde hace tres días.',
    '{"pa": "100/65", "fc": 92, "temp": 36.8, "peso": 28.4, "spo2": 97, "talla": 132}'::jsonb,
    'Faringe levemente hiperémica, auscultación pulmonar sin crepitantes.',
    'Cuadro viral de vías respiratorias altas.',
    'Hidratación abundante, lavados nasales y control de fiebre.',
    'Retornar si presenta dificultad respiratoria o fiebre persistente.',
    (c.fecha + c.hora_inicio)::timestamptz
FROM citas c
WHERE c.codigo = 'CM-DEMO-002'
ON CONFLICT (cita_id) DO NOTHING;

INSERT INTO recetas (
    codigo, codigo_acceso, consulta_id, paciente_id, medico_id, enviado_whatsapp, fecha
)
SELECT
    'RSV-DEMO-001', '1234', q.id, q.paciente_id, q.medico_id, FALSE, q.fecha + INTERVAL '5 minutes'
FROM consultas q
JOIN citas c ON c.id = q.cita_id
WHERE c.codigo = 'CM-DEMO-001'
ON CONFLICT DO NOTHING;

INSERT INTO receta_detalle (
    receta_id, medicamento, dosis, via, frecuencia, duracion, instrucciones
)
SELECT r.id, detalle.medicamento, detalle.dosis, detalle.via, detalle.frecuencia,
       detalle.duracion, detalle.instrucciones
FROM recetas r
CROSS JOIN (VALUES
    ('Losartán', '50 mg', 'oral', 'cada 12 horas', '30 días', 'Tomar con alimentos.'),
    ('Paracetamol', '500 mg', 'oral', 'cada 8 horas si hay dolor', '5 días', 'No superar 3 g al día.')
) AS detalle(medicamento, dosis, via, frecuencia, duracion, instrucciones)
WHERE r.codigo = 'RSV-DEMO-001'
  AND NOT EXISTS (
      SELECT 1 FROM receta_detalle d
      WHERE d.receta_id = r.id AND d.medicamento = detalle.medicamento
  );

INSERT INTO recetas (
    codigo, codigo_acceso, consulta_id, paciente_id, medico_id, enviado_whatsapp, fecha
)
SELECT
    'RSV-DEMO-002', '5678', q.id, q.paciente_id, q.medico_id, TRUE, q.fecha + INTERVAL '5 minutes'
FROM consultas q
JOIN citas c ON c.id = q.cita_id
WHERE c.codigo = 'CM-DEMO-002'
ON CONFLICT DO NOTHING;

INSERT INTO receta_detalle (
    receta_id, medicamento, dosis, via, frecuencia, duracion, instrucciones
)
SELECT r.id, 'Ibuprofeno', '200 mg', 'oral', 'cada 8 horas si hay fiebre', '3 días',
       'Administrar después de las comidas.'
FROM recetas r
WHERE r.codigo = 'RSV-DEMO-002'
  AND NOT EXISTS (
      SELECT 1 FROM receta_detalle d
      WHERE d.receta_id = r.id AND d.medicamento = 'Ibuprofeno'
  );

INSERT INTO documentos (
    codigo, codigo_acceso, consulta_id, paciente_id, medico_id, tipo, contenido, enviado_whatsapp, fecha
)
SELECT
    'DOC-DEMO-001', '4321', q.id, q.paciente_id, q.medico_id, 'certificado',
    'Se certifica que el paciente requiere reposo relativo por 24 horas.',
    FALSE, q.fecha + INTERVAL '10 minutes'
FROM consultas q
JOIN citas c ON c.id = q.cita_id
WHERE c.codigo = 'CM-DEMO-001'
ON CONFLICT DO NOTHING;

INSERT INTO ordenes (
    codigo, codigo_acceso, consulta_id, paciente_id, medico_id, tipo, enviado_whatsapp, fecha
)
SELECT
    'ORD-DEMO-001', '8765', q.id, q.paciente_id, q.medico_id, 'laboratorio', FALSE,
    q.fecha + INTERVAL '10 minutes'
FROM consultas q
JOIN citas c ON c.id = q.cita_id
WHERE c.codigo = 'CM-DEMO-001'
ON CONFLICT DO NOTHING;

INSERT INTO orden_detalle (orden_id, estudio, observaciones)
SELECT o.id, detalle.estudio, detalle.observaciones
FROM ordenes o
CROSS JOIN (VALUES
    ('Hemograma completo', 'Ayuno no requerido.'),
    ('Glucosa en ayunas', 'Ayuno de 8 horas.')
) AS detalle(estudio, observaciones)
WHERE o.codigo = 'ORD-DEMO-001'
  AND NOT EXISTS (
      SELECT 1 FROM orden_detalle d
      WHERE d.orden_id = o.id AND d.estudio = detalle.estudio
  );

INSERT INTO resultados (orden_id, texto, revisado, fecha)
SELECT
    o.id,
    'Hemograma dentro de rangos normales. Glucosa en ayunas: 95 mg/dL.',
    TRUE,
    o.fecha + INTERVAL '2 days'
FROM ordenes o
WHERE o.codigo = 'ORD-DEMO-001'
  AND NOT EXISTS (
      SELECT 1 FROM resultados r
      WHERE r.orden_id = o.id AND r.texto LIKE 'Hemograma dentro%'
  );

INSERT INTO pagos (
    paciente_id, medico_id, registrado_por, monto, metodo, referencia_paciente, fecha
)
SELECT
    p.id, m.id, u.id, 150.00, 'efectivo', 'DEMO-PAGO-001',
    (c.fecha + c.hora_fin)::timestamptz
FROM pacientes p, medicos m, usuarios u, citas c
WHERE p.codigo = 'PAC-DEMO-001'
  AND m.codigo = 'MED-DEMO-001'
  AND u.username = 'recepcion'
  AND c.codigo = 'CM-DEMO-001'
  AND NOT EXISTS (
      SELECT 1 FROM pagos pg WHERE pg.referencia_paciente = 'DEMO-PAGO-001'
  );

INSERT INTO pagos (
    paciente_id, medico_id, registrado_por, monto, metodo, referencia_paciente, fecha
)
SELECT
    p.id, m.id, u.id, 200.00, 'visa', 'DEMO-PAGO-002', now()
FROM pacientes p, medicos m, usuarios u
WHERE p.codigo = 'PAC-DEMO-002'
  AND m.codigo = 'MED-DEMO-003'
  AND u.username = 'recepcion'
  AND NOT EXISTS (
      SELECT 1 FROM pagos pg WHERE pg.referencia_paciente = 'DEMO-PAGO-002'
  );
