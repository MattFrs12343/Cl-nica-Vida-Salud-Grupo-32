<?php
/** @var array<int, array<string, mixed>> $especialidades */
/** @var array<string, mixed> $usuario */

$indiceMedicina = null;

foreach ($especialidades as $indice => $especialidad) {
    if (mb_strtolower(trim((string) ($especialidad['nombre'] ?? ''))) === 'medicina general') {
        $indiceMedicina = $indice;
        break;
    }
}

if ($indiceMedicina !== null && $indiceMedicina !== 0) {
    $medicinaGeneral = $especialidades[$indiceMedicina];
    unset($especialidades[$indiceMedicina]);
    array_unshift($especialidades, $medicinaGeneral);
    $especialidades = array_values($especialidades);
}

$iconos = [
    'stethoscope' => 'i-stethoscope',
    'child' => 'i-child',
    'elderly' => 'i-elderly',
    'heart' => 'i-heart',
    'female' => 'i-female',
];
$nombrePaciente = trim((string) ($usuario['nombre'] ?? 'paciente'));
?>
<section class="reserva-hero">
    <div class="container">
        <a class="back-link" href="<?= e(url('/')) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"></use></svg>
            Volver al inicio
        </a>

        <div class="reserva-hero-grid">
            <div>
                <h1>Agenda tu<br><em>próxima cita.</em></h1>
                <p>
                    Hola, <?= e($nombrePaciente) ?>. Elige especialidad, médico y horario.
                    Te tomará menos de dos minutos.
                </p>
            </div>

            <div class="reserva-hero-note">
                <svg class="icon" aria-hidden="true"><use href="#i-shield"></use></svg>
                <div>
                    <strong>Reserva segura</strong>
                    <span>Confirmación inmediata por correo</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section reserva-section">
    <div class="container reserva-layout" data-reserva-app>
        <div class="reserva-main">
            <div class="reserva-card" data-reserva-card>
                <ol class="reserva-progress" aria-label="Pasos de la reserva">
                    <li class="is-active" data-step-indicator="1" aria-current="step">
                        <span>01</span>
                        <div><strong>Especialidad</strong><small>y médico</small></div>
                    </li>
                    <li data-step-indicator="2">
                        <span>02</span>
                        <div><strong>Fecha</strong><small>y hora</small></div>
                    </li>
                    <li data-step-indicator="3">
                        <span>03</span>
                        <div><strong>Confirmación</strong><small>y motivo</small></div>
                    </li>
                </ol>

                <div class="form-alert" data-reserva-alert role="alert" hidden></div>

                <form class="reserva-form" data-reserva-form novalidate>
                    <section class="reserva-step is-active" data-step="1" aria-labelledby="paso-especialidad">
                        <div class="step-head">
                            <span>Paso 1</span>
                            <h2 id="paso-especialidad" tabindex="-1">¿A quién quieres ver?</h2>
                            <p>Elige una especialidad y luego a tu médico.</p>
                        </div>

                        <fieldset class="choice-fieldset">
                            <legend>Especialidad</legend>
                            <div class="choice-grid">
                                <?php foreach ($especialidades as $especialidad): ?>
                                    <?php
                                    $icono = $iconos[(string) ($especialidad['icono'] ?? '')] ?? 'i-spark';
                                    ?>
                                    <label class="choice-card">
                                        <input
                                            type="radio"
                                            name="especialidad_id"
                                            value="<?= e($especialidad['id']) ?>"
                                            data-especialidad-choice
                                            data-nombre="<?= e($especialidad['nombre']) ?>"
                                        >
                                        <span class="choice-icon" aria-hidden="true">
                                            <svg class="icon"><use href="#<?= e($icono) ?>"></use></svg>
                                        </span>
                                        <span class="choice-label"><?= e($especialidad['nombre']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <div class="doctor-picker">
                            <div class="picker-head">
                                <span>Médico</span>
                                <span class="picker-status" data-doctor-picker-status aria-live="polite">Elige una especialidad</span>
                            </div>
                            <div class="doctor-choice-list" data-reserva-medicos>
                                <div class="picker-empty">Elige una especialidad para ver los médicos disponibles.</div>
                            </div>
                        </div>

                        <div class="step-actions">
                            <span class="step-hint">Paso 1 de 3</span>
                            <button class="btn btn-primary" type="button" data-next-step="2" disabled>
                                <span>Continuar</span>
                                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
                            </button>
                        </div>
                    </section>

                    <section class="reserva-step" data-step="2" aria-labelledby="paso-horario" hidden>
                        <div class="step-head">
                            <span>Paso 2</span>
                            <h2 id="paso-horario" tabindex="-1">Elige fecha y hora.</h2>
                            <p>Solo mostramos horarios con cupo web disponible.</p>
                        </div>

                        <div class="date-row">
                            <div class="form-field">
                                <label for="reserva-fecha">Fecha</label>
                                <input id="reserva-fecha" type="date" name="fecha" data-reserva-fecha required>
                            </div>
                            <div class="date-hint">
                                <svg class="icon" aria-hidden="true"><use href="#i-calendar"></use></svg>
                                <span>Puedes reservar desde hoy hasta 60 días adelante.</span>
                            </div>
                        </div>

                        <div class="slots-panel">
                            <div class="picker-head">
                                <span>Horarios disponibles</span>
                                <span class="picker-status" data-slots-status aria-live="polite">Sin horarios</span>
                            </div>
                            <div class="slots-grid" data-reserva-slots>
                                <div class="slots-empty">Elige una fecha para ver horarios.</div>
                            </div>
                        </div>

                        <div class="step-actions">
                            <button class="btn btn-ghost" type="button" data-prev-step>
                                <span>Volver</span>
                            </button>
                            <button class="btn btn-primary" type="button" data-next-step="3" disabled>
                                <span>Continuar</span>
                                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
                            </button>
                        </div>
                    </section>

                    <section class="reserva-step" data-step="3" aria-labelledby="paso-confirmar" hidden>
                        <div class="step-head">
                            <span>Paso 3</span>
                            <h2 id="paso-confirmar" tabindex="-1">Confirma tu cita.</h2>
                            <p>Revisa los datos y cuéntanos brevemente el motivo de la consulta.</p>
                        </div>

                        <div class="confirm-summary">
                            <div>
                                <span>Especialidad</span>
                                <strong data-confirm-especialidad>Por elegir</strong>
                            </div>
                            <div>
                                <span>Médico</span>
                                <strong data-confirm-medico>Por elegir</strong>
                            </div>
                            <div>
                                <span>Fecha</span>
                                <strong data-confirm-fecha>Por elegir</strong>
                            </div>
                            <div>
                                <span>Hora</span>
                                <strong data-confirm-hora>Por elegir</strong>
                            </div>
                        </div>

                        <div class="form-field">
                            <label for="reserva-motivo">Motivo <span>(opcional)</span></label>
                            <textarea
                                id="reserva-motivo"
                                name="motivo"
                                maxlength="200"
                                rows="3"
                                placeholder="Ej. Control general, dolor de cabeza, seguimiento…"
                                data-reserva-motivo
                            ></textarea>
                            <span class="field-help"><span data-motivo-count>0</span>/200</span>
                        </div>

                        <div class="step-actions">
                            <button class="btn btn-ghost" type="button" data-prev-step>
                                <span>Volver</span>
                            </button>
                            <button class="btn btn-primary btn-submit" type="submit" data-reserva-submit>
                                <span>Confirmar reserva</span>
                                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
                            </button>
                        </div>
                    </section>
                </form>
            </div>

            <div class="reserva-success" data-reserva-success hidden>
                <div class="success-icon" aria-hidden="true">
                    <svg class="icon"><use href="#i-check"></use></svg>
                </div>
                <span class="success-kicker">Reserva confirmada</span>
                <h2 tabindex="-1">Todo listo, <?= e($nombrePaciente) ?>.</h2>
                <p>
                    Tu cita quedó programada. Revisa tu correo para la confirmación y
                    presenta tu ficha digital al llegar.
                </p>

                <div class="success-ticket">
                    <div><span>Cita</span><strong data-success-cita>—</strong></div>
                    <div><span>Ficha</span><strong data-success-ficha>—</strong></div>
                    <div><span>Fecha</span><strong data-success-fecha>—</strong></div>
                    <div><span>Hora</span><strong data-success-hora>—</strong></div>
                    <div><span>Médico</span><strong data-success-medico>—</strong></div>
                </div>

                <div class="success-actions">
                    <a class="btn btn-primary" href="<?= e(url('/portal')) ?>">
                        <span>Ir a mi portal</span>
                        <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-up-right"></use></svg></span>
                    </a>
                    <a class="btn btn-ghost" href="<?= e(url('/')) ?>">Volver al inicio</a>
                </div>
            </div>
        </div>

        <aside class="reserva-aside">
            <div class="summary-card">
                <div class="summary-head">
                    <span>Tu cita</span>
                    <svg class="icon" aria-hidden="true"><use href="#i-calendar"></use></svg>
                </div>

                <dl class="summary-list">
                    <div><dt>Especialidad</dt><dd class="is-empty" data-summary-especialidad>Por elegir</dd></div>
                    <div><dt>Médico</dt><dd class="is-empty" data-summary-medico>Por elegir</dd></div>
                    <div><dt>Fecha</dt><dd class="is-empty" data-summary-fecha>Por elegir</dd></div>
                    <div><dt>Hora</dt><dd class="is-empty" data-summary-hora>Por elegir</dd></div>
                </dl>

                <div class="summary-note">
                    <svg class="icon" aria-hidden="true"><use href="#i-shield"></use></svg>
                    <span>Tus datos se usan únicamente para gestionar esta cita.</span>
                </div>
            </div>

            <div class="aside-help">
                <h2>¿Necesitas ayuda?</h2>
                <p>Si tienes dudas antes de confirmar, escríbenos en horario de atención.</p>
                <div class="aside-help-links">
                    <a href="https://wa.me/59170000000" target="_blank" rel="noopener">WhatsApp</a>
                    <a href="tel:+59170000000">+591 700 00000</a>
                </div>
            </div>
        </aside>
    </div>
</section>
