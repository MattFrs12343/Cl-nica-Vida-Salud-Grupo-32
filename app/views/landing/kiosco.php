<section class="page-hero">
    <div class="container">
        <span class="page-kicker">Atención presencial</span>
        <h1>Tu ficha,<br><em>sin filas.</em></h1>
        <p>
            Si vienes a la clínica, saca tu ficha en el kiosco táctil. Eliges especialidad,
            médico y horario, y obtienes tu código de atención en pocos pasos.
        </p>
    </div>
</section>

<section class="section kiosco-section">
    <div class="container kiosco-grid">
        <div class="kiosco-steps">
            <h2>Cómo funciona.</h2>
            <ol class="steps-list">
                <li>
                    <span>01</span>
                    <div><strong>Ingresa tu cédula de identidad</strong> en la pantalla del kiosco.</div>
                </li>
                <li>
                    <span>02</span>
                    <div><strong>Confirma tus datos.</strong> Si es tu primera visita, completa la información básica.</div>
                </li>
                <li>
                    <span>03</span>
                    <div><strong>Elige especialidad, médico y horario</strong> con cupo disponible.</div>
                </li>
                <li>
                    <span>04</span>
                    <div><strong>Recibe tu ficha</strong> con código y QR. Preséntala en recepción.</div>
                </li>
            </ol>

            <div class="notice">
                <strong>Importante:</strong> sin ficha no se atiende. Si ya reservaste online,
                igual debes sacar tu ficha al llegar o presentar tu código de reserva.
            </div>
        </div>

        <aside class="kiosco-aside">
            <div class="kiosco-ticket-card">
                <h2>Tu ficha,<br>en un código.</h2>
                <p>La ficha digital te permite confirmar tu atención y guardar el comprobante en tu teléfono.</p>

                <div class="kiosco-demo-ticket" aria-hidden="true">
                    <span>Ejemplo de ficha</span>
                    <strong>FIC-042</strong>
                </div>

                <a class="btn btn-light btn-block" href="<?= e(url('/reservar')) ?>">
                    <span>Reservar online</span>
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-up-right"></use></svg></span>
                </a>
                <a class="btn btn-ghost-light btn-block" href="<?= e(url('/activar')) ?>">Activar mi cuenta</a>
            </div>

            <div class="kiosco-help">
                <h2>¿Prefieres desde casa?</h2>
                <p>Agenda con tu cuenta de paciente y llega con tu cita confirmada.</p>
                <a href="<?= e(url('/reservar')) ?>">Reservar online →</a>
            </div>
        </aside>
    </div>
</section>
