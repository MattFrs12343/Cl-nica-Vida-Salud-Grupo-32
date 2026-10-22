<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use PDO;
use Throwable;

/**
 * Envío de correo con fallback a cola/log.
 *
 * La integración SMTP real (PHPMailer + Brevo + Mailpit) se completa en M-13.
 */
final class MailService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    public function queue(string $destinatario, string $asunto, string $cuerpo): void
    {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO cola_correos (destinatario, asunto, cuerpo)
                 VALUES (:destinatario, :asunto, :cuerpo)'
            );
            $statement->execute([
                'destinatario' => $destinatario,
                'asunto' => $asunto,
                'cuerpo' => $cuerpo,
            ]);
        } catch (Throwable $exception) {
            logger($exception, ['correo' => $destinatario, 'asunto' => $asunto]);
        }
    }

    public function send(string $destinatario, string $asunto, string $cuerpo): bool
    {
        $config = (array) config('mail', []);
        $driver = strtolower((string) ($config['driver'] ?? 'smtp'));

        if (!in_array($driver, ['smtp', 'mail'], true) || !class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            $this->queue($destinatario, $asunto, $cuerpo);

            return false;
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            if ($driver === 'smtp') {
                $host = trim((string) ($config['host'] ?? ''));

                if ($host === '') {
                    $this->queue($destinatario, $asunto, $cuerpo);

                    return false;
                }

                $mail->isSMTP();
                $mail->Host = $host;
                $mail->Port = (int) ($config['port'] ?? 1025);
                $mail->SMTPAuth = (bool) ($config['smtp_auth'] ?? false);
                $mail->Timeout = (int) ($config['timeout'] ?? 10);
                $mail->SMTPAutoTLS = false;

                if ($mail->SMTPAuth) {
                    $mail->Username = (string) ($config['username'] ?? '');
                    $mail->Password = (string) ($config['password'] ?? '');
                }

                $encryption = strtolower((string) ($config['encryption'] ?? ''));

                if ($encryption === 'tls') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($encryption === 'ssl') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                }
            } else {
                $mail->isMail();
            }

            $mail->CharSet = 'UTF-8';
            $mail->isHTML(false);
            $mail->setFrom(
                (string) ($config['from'] ?? 'no-reply@vidasalud.test'),
                (string) ($config['from_name'] ?? 'Clínica Vida Salud')
            );
            $mail->addAddress($destinatario);
            $mail->Subject = $asunto;
            $mail->Body = $cuerpo;
            $mail->AltBody = $cuerpo;
            $mail->send();

            return true;
        } catch (Throwable $exception) {
            logger($exception, ['correo' => $destinatario, 'asunto' => $asunto]);
            $this->queue($destinatario, $asunto, $cuerpo);

            return false;
        }
    }

    public function sendReset(string $destinatario, string $enlace): void
    {
        $asunto = 'Recuperación de contraseña — Clínica Vida Salud';
        $cuerpo = "Recibimos una solicitud para restablecer tu contraseña.\n\n"
            . "Ingresa al siguiente enlace (válido por 30 minutos):\n{$enlace}\n\n"
            . "Si no solicitaste este cambio, ignora este mensaje.";

        $this->send($destinatario, $asunto, $cuerpo);
    }

    public function sendConfirmacionCita(string $destinatario, array $datos): void
    {
        $asunto = 'Confirmación de cita — Clínica Vida Salud';
        $cuerpo = "Tu cita quedó registrada.\n\n"
            . 'Fecha: ' . ($datos['fecha'] ?? '') . "\n"
            . 'Hora: ' . ($datos['hora'] ?? '') . "\n"
            . 'Médico: ' . ($datos['medico'] ?? '') . "\n"
            . 'Especialidad: ' . ($datos['especialidad'] ?? '') . "\n"
            . 'Código: ' . ($datos['codigo'] ?? '') . "\n";

        $this->send($destinatario, $asunto, $cuerpo);
    }

    public function sendCancelacionCita(string $destinatario, array $datos): void
    {
        $asunto = 'Cancelación de cita — Clínica Vida Salud';
        $cuerpo = "Tu cita fue cancelada.\n\n"
            . 'Fecha: ' . ($datos['fecha'] ?? '') . "\n"
            . 'Hora: ' . ($datos['hora'] ?? '') . "\n"
            . 'Motivo: ' . ($datos['motivo'] ?? 'No indicado') . "\n";

        $this->send($destinatario, $asunto, $cuerpo);
    }
}
