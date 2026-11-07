-- 014 — Quita la verificación biométrica simulada del kiosco
-- La pantalla de "verificación facial" del kiosco era una animación sin
-- cámara real detrás; se eliminó del flujo y ya no se registra en la ficha.

ALTER TABLE fichas DROP COLUMN IF EXISTS verificacion_biometrica;
