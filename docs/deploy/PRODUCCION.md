# Deploy de producción (M-37)

Arquitectura objetivo: **cPanel** para PHP/Apache + **Neon o Railway** para
PostgreSQL + **Brevo** para SMTP + HTTPS obligatorio.

> Esta guía deja el despliegue listo para ejecutar. La publicación real requiere
> credenciales del hosting, de la base de datos, de Brevo y del dominio.

## 1. Requisitos del hosting

- PHP **8.1 o superior** (recomendado 8.2).
- Extensiones: `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`, `curl`.
- Apache con `mod_rewrite` y `mod_headers` habilitados.
- SSH o terminal de cPanel para ejecutar migraciones y el preflight.
- Document root del dominio apuntando a la carpeta `public/` del proyecto.
- Certificado HTTPS activo (AutoSSL de cPanel o equivalente).

Si el hosting no ofrece `pdo_pgsql`, no es apto para este proyecto.

## 2. Variables de entorno

1. Copia `.env.production.example` como `.env` en la raíz del proyecto,
   **fuera de `public/`**.
2. Completa los valores reales.
3. Permisos recomendados: `chmod 600 .env`.
4. Nunca subas `.env` al repositorio.

Variables críticas:

| Variable | Valor de producción |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://tu-dominio.com` (sin barra final) |
| `SESSION_SECURE` | `true` |
| `DB_SSLMODE` | `require` (o `verify-full` si tienes CA) |
| `DB_APP_ROLE` | `clinica_app` |
| `MAIL_HOST` | `smtp-relay.brevo.com` |
| `MAIL_PORT` | `587` |
| `MAIL_ENCRYPTION` | `tls` |
| `MAIL_SMTP_AUTH` | `true` |
| `KIOSK_DEVICE_KEY` | valor aleatorio propio |
| `KIOSK_HMAC_SECRET` | secreto aleatorio de 32+ caracteres |

`APP_URL` debe coincidir exactamente con el origen HTTPS real (incluido
`www` o subdominio). La reserva web usa ese origen para validar CSRF.

## 3. Base de datos

1. Crea la base en Neon o Railway y copia la cadena de conexión.
2. Usa el usuario propietario para migrar; la aplicación trabajará con
   `DB_APP_ROLE=clinica_app`.
3. Ejecuta desde la raíz del proyecto:

```bash
php bin/db-migrate.php
```

4. No ejecutes `db-seed.php` en producción: los seeders crean cuentas demo.
5. Crea el primer administrador:

```bash
php bin/crear-admin.php --super
# o, para un administrador normal:
php bin/crear-admin.php
```

El script pide usuario, email y contraseña por consola y no imprime la clave.

## 4. Subida de archivos

1. Sube el proyecto completo fuera de `public_html` (por ejemplo
   `/home/USUARIO/clinica`).
2. Instala dependencias de producción:

```bash
composer install --no-dev --optimize-autoloader
```

3. Configura el document root del dominio a `/home/USUARIO/clinica/public`.
4. Verifica que `public/uploads` y `storage/logs` existan y sean escribibles.
5. Mantén `public/uploads/.htaccess` para impedir ejecución de PHP en subidas.

## 5. HTTPS y cookies

1. Activa AutoSSL y fuerza la redirección de HTTP a HTTPS desde cPanel.
2. Confirma `SESSION_SECURE=true`.
3. Comprueba que `APP_URL` use `https://`.
4. No desactives `HttpOnly`, `SameSite=Lax` ni las cabeceras de seguridad.

## 6. Correo con Brevo

1. Crea la cuenta y verifica el remitente o dominio en Brevo.
2. Usa el usuario SMTP y la clave SMTP (no la clave de la cuenta).
3. Prueba la recuperación de contraseña desde `/recuperar`.
4. Si el envío falla, el correo queda en `cola_correos`; revisa
   `storage/logs/app.log` y la tabla para reintentar manualmente.

## 7. Backups

Configura un cron diario en cPanel. Ejemplo:

```bash
mkdir -p /home/USUARIO/backups
pg_dump "$DATABASE_URL" --format=custom \
  --file=/home/USUARIO/backups/clinica-$(date +\%F).dump
tar -czf /home/USUARIO/backups/uploads-$(date +\%F).tar.gz \
  /home/USUARIO/clinica/public/uploads
find /home/USUARIO/backups -name 'clinica-*.dump' -mtime +14 -delete
find /home/USUARIO/backups -name 'uploads-*.tar.gz' -mtime +14 -delete
```

- Guarda al menos 14 días y una copia fuera del hosting.
- Prueba una restauración en staging:

```bash
pg_restore --clean --if-exists -d "$DATABASE_URL" clinica-AAAA-MM-DD.dump
```

## 8. Verificación posterior al deploy

Ejecuta en el servidor:

```bash
php bin/preflight-produccion.php
```

Debe terminar con **0 fallos**. Después comprueba:

- [ ] `GET https://tu-dominio.com/api/health` responde `200`.
- [ ] El login de administrador funciona y redirige a `/admin`.
- [ ] Recepción, médico y paciente acceden a sus paneles.
- [ ] La landing lista especialidades y médicos.
- [ ] La reserva web crea una cita (mismo origen y CSRF correctos).
- [ ] El kiosco obtiene token y muestra disponibilidad.
- [ ] Se envía un correo de recuperación con Brevo.
- [ ] Se descarga un CSV de reportes.
- [ ] La auditoría registra el acceso y los cambios.
- [ ] `storage/logs/app.log` no tiene errores repetidos.
- [ ] Los usuarios demo fueron eliminados o cambiados de contraseña.

## 9. Seguridad operativa

- No publiques `APP_DEBUG=true`.
- No uses las claves `dev-*` del repositorio.
- No expongas `.env`, `storage/`, `vendor/` ni `database/` por HTTP.
- Restringe `/kiosco` al dispositivo del kiosco si el hosting lo permite.
- Revisa semanalmente `storage/logs/app.log` y la tabla `auditoria`.
- Mantén actualizados PHP, Composer y las dependencias.

## 10. Rollback

1. Conserva el release anterior y un dump previo.
2. Si falla el deploy, restaura el código anterior.
3. Restaura la base solo si la migración es incompatible; las migraciones
   actuales son aditivas (`IF NOT EXISTS`).
4. Vuelve a ejecutar el preflight y las pruebas de humo.
