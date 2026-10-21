-- Usuarios demo para desarrollo.
-- Contraseña para todas las cuentas: Demo1234!
-- Hash bcrypt generado con password_hash('Demo1234!', PASSWORD_DEFAULT).

INSERT INTO usuarios (username, email, password_hash, rol)
VALUES
    ('superadmin',        'superadmin@vidasalud.test',        '$2y$10$jFEFU3RW2LwW9lGzI7m4lOd81A4cXNxF1i1iQTD16/yhqdPLtKZJm', 'super_admin'),
    ('admin',             'admin@vidasalud.test',             '$2y$10$jFEFU3RW2LwW9lGzI7m4lOd81A4cXNxF1i1iQTD16/yhqdPLtKZJm', 'admin'),
    ('recepcion',         'recepcion@vidasalud.test',         '$2y$10$jFEFU3RW2LwW9lGzI7m4lOd81A4cXNxF1i1iQTD16/yhqdPLtKZJm', 'recepcionista'),
    ('recepcion.medico',  'recepcion.medico@vidasalud.test',  '$2y$10$jFEFU3RW2LwW9lGzI7m4lOd81A4cXNxF1i1iQTD16/yhqdPLtKZJm', 'recepcionista_medico'),
    ('medico',            'medico@vidasalud.test',            '$2y$10$jFEFU3RW2LwW9lGzI7m4lOd81A4cXNxF1i1iQTD16/yhqdPLtKZJm', 'medico'),
    ('12345678',          'paciente@vidasalud.test',          '$2y$10$jFEFU3RW2LwW9lGzI7m4lOd81A4cXNxF1i1iQTD16/yhqdPLtKZJm', 'paciente')
ON CONFLICT DO NOTHING;
