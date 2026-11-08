-- `bytea` vuelve de PDO en formato hexadecimal de texto (\x...), no en bytes
-- crudos, así que session_decode() recibía basura. Se guarda en texto plano
-- (base64, desde el lado de PHP) para evitar ese problema de codificación.

ALTER TABLE sesiones ALTER COLUMN datos DROP DEFAULT;
ALTER TABLE sesiones ALTER COLUMN datos TYPE TEXT USING ''::text;
ALTER TABLE sesiones ALTER COLUMN datos SET DEFAULT '';
