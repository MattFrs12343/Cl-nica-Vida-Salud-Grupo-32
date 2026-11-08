<?php

declare(strict_types=1);

namespace App\Core;

use SessionHandlerInterface;
use Throwable;

/**
 * Guarda las sesiones de PHP en la base de datos en vez de en disco.
 *
 * El filesystem de un contenedor en Render (plan free) no es persistente:
 * cada vez que el servicio se duerme por inactividad y despierta (o se
 * redeploya), se pierde todo lo que el handler de sesiones por defecto de
 * PHP guarda en disco local. Eso invalidaba silenciosamente la sesión de
 * cualquiera que hubiera cargado una página antes del reinicio, y con ella
 * su token CSRF — se veía como "Token CSRF inválido o expirado" al enviar
 * un formulario aunque el usuario no hubiera hecho nada mal.
 */
final class DbSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $statement = DB::connection()->prepare('SELECT datos FROM sesiones WHERE id = :id');
            $statement->execute(['id' => $id]);
            $datos = $statement->fetchColumn();

            return $datos === false || $datos === null || $datos === '' ? '' : (string) base64_decode((string) $datos, true);
        } catch (Throwable) {
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $statement = DB::connection()->prepare(
                'INSERT INTO sesiones (id, datos, actualizado_en) VALUES (:id, :datos, now())
                 ON CONFLICT (id) DO UPDATE SET datos = EXCLUDED.datos, actualizado_en = now()'
            );
            $statement->bindValue('id', $id);
            $statement->bindValue('datos', base64_encode($data));

            return $statement->execute();
        } catch (Throwable) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $statement = DB::connection()->prepare('DELETE FROM sesiones WHERE id = :id');
            $statement->execute(['id' => $id]);
        } catch (Throwable) {
            // No bloquea el logout si la tabla aún no existe.
        }

        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $statement = DB::connection()->prepare(
                "DELETE FROM sesiones WHERE actualizado_en < now() - make_interval(secs => :max)"
            );
            $statement->execute(['max' => $max_lifetime]);

            return $statement->rowCount();
        } catch (Throwable) {
            return false;
        }
    }
}
