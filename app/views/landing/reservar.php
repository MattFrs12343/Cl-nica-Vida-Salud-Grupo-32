<?php
/** @var array<int, array<string, mixed>> $especialidades */
/** @var array<string, mixed> $usuario */
?>
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Reserva web</span>
        <h1>Agenda tu cita</h1>
        <p>
            Hola, <?= e($usuario['nombre'] ?? 'paciente') ?>. Elige especialidad, médico, fecha y horario.
            La reserva consume un cupo web y recibirás la confirmación por correo.
        </p>
    </div>
</section>

<section class="section">
    <div class="container reserva-layout">
        <div class="reserva-card" data-reserva-app>
            <ol class="reserva-progress" aria-label="Pasos de la reserva">
                <li class="is-active"><span>1</span> Especialidad</li>
                <li><span>2</span> Médico</li>
                <li><span>3</span> Fecha y hora</li>
                <li><span>4</span> Confirmar</li>
            </ol>

            <form class="reserva-form" data-reserva-form novalidate>
                <div class="form-field">
                    <label for="reserva-especialidad">Especialidad</label>
                    <select id="reserva-especialidad" name="especialidad_id" data-reserva-especialidad required>
                        <option value="">Selecciona una especialidad</option>
                        <?php foreach ($especialidades as $especialidad): ?>
                            <option value="<?= e($especialidad['id']) ?>"><?= e($especialidad['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="reserva-medico">Médico</label>
                    <select id="reserva-medico" name="medico_id" data-reserva-medico required disabled>
                        <option value="">Primero elige una especialidad</option>
                    </select>
                </div>

                <div class="form-field">
                    <label for="reserva-fecha">Fecha</label>
                    <input id="reserva-fecha" type="date" name="fecha" data-reserva-fecha required disabled>
                </div>

                <div class="form-field">
                    <label for="reserva-hora">Horario</label>
                    <select id="reserva-hora" name="hora" data-reserva-hora required disabled>
                        <option value="">Primero elige un médico y una fecha</option>
                    </select>
                </div>

                <div class="form-field">
                    <label for="reserva-motivo">Motivo <span class="muted">(opcional)</span></label>
                    <input id="reserva-motivo" type="text" name="motivo" maxlength="200" placeholder="Ej. Control general">
                </div>

                <div class="form-alert" data-reserva-alert role="alert" hidden></div>

                <button class="button button-primary button-lg" type="submit" data-reserva-submit>
                    Confirmar reserva
                </button>
            </form>
        </div>

        <aside class="reserva-aside">
            <h2>Antes de reservar</h2>
            <ul class="check-list">
                <li>La fecha debe ser hoy o posterior.</li>
                <li>Solo se muestran horarios con cupo web disponible.</li>
                <li>La cita queda <strong>programada</strong> hasta que saques tu ficha.</li>
                <li>Puedes cancelar hasta el día anterior desde tu portal.</li>
            </ul>
            <p class="muted">
                Si necesitas atención hoy sin cita, acércate al kiosco presencial.
            </p>
            <a class="text-link" href="<?= e(url('/kiosco')) ?>">Cómo sacar ficha →</a>
        </aside>
    </div>
</section>
