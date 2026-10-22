<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Atención presencial</span>
        <h1>Kiosco de fichas</h1>
        <p>
            Si vienes a la clínica, puedes sacar tu ficha en el kiosco táctil. En pocos pasos
            eliges especialidad, médico y horario, y obtienes tu código de atención.
        </p>
    </div>
</section>

<section class="section">
    <div class="container kiosco-grid">
        <div class="kiosco-steps">
            <h2>¿Cómo funciona?</h2>
            <ol class="steps-list steps-list-lg">
                <li><span>1</span> Ingresa tu cédula de identidad en la pantalla.</li>
                <li><span>2</span> Si es tu primera visita, completa tus datos básicos.</li>
                <li><span>3</span> Elige especialidad, médico y horario disponible.</li>
                <li><span>4</span> Recibe tu ficha con código y QR. ¡Listo!</li>
            </ol>

            <div class="notice">
                <strong>Importante:</strong> sin ficha no se atiende. Si ya reservaste online,
                igual debes sacar tu ficha al llegar o presentar tu código de reserva.
            </div>
        </div>

        <aside class="contact-card">
            <h2>¿Prefieres reservar desde casa?</h2>
            <p>Agenda online con tu cuenta de paciente y llega con tu cita confirmada.</p>
            <a class="button button-primary" href="<?= e(url('/reservar')) ?>">Agendar cita online</a>
            <a class="button button-outline" href="<?= e(url('/activar')) ?>">Activar mi cuenta</a>

            <hr>

            <h3>Horario de atención</h3>
            <p class="muted">Lunes a viernes 08:00–18:00<br>Sábados 08:00–12:00</p>
        </aside>
    </div>
</section>
