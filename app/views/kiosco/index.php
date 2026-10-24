<?php
/**
 * Kiosco táctil de fichas (Spec 09 / M-26).
 *
 * Todo el estado vive en el cliente. El servidor solo entrega el token de
 * sesión y los endpoints /api/kiosco/*.
 */
?>
<div class="kiosk-shell">
    <header class="kiosk-top">
        <button class="kiosk-brand" type="button" data-action="reset" aria-label="Volver al inicio del kiosco">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
            </span>
            <span class="brand-text">
                <strong>Clínica Vida Salud</strong>
                <small>Kiosco de fichas</small>
            </span>
        </button>

        <ol class="kiosk-steps" data-steps aria-label="Progreso de la ficha" hidden>
            <li data-step="1"><span>1</span><small>Especialidad</small></li>
            <li data-step="2"><span>2</span><small>Médico</small></li>
            <li data-step="3"><span>3</span><small>Horario</small></li>
            <li data-step="4"><span>4</span><small>Ficha</small></li>
        </ol>

        <div class="kiosk-top-right">
            <span class="kiosk-clock" data-clock aria-live="off"></span>
            <button class="kiosk-icon-btn" type="button" data-action="reset" aria-label="Reiniciar el kiosco" title="Reiniciar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 12a9 9 0 1 0 3-6.7L3 8"/>
                    <path d="M3 3v5h5"/>
                </svg>
            </button>
        </div>
    </header>

    <main class="kiosk-main">
        <!-- Bienvenida ------------------------------------------------------->
        <section class="screen screen-welcome is-active" data-screen="welcome" aria-labelledby="welcome-title">
            <div class="welcome-copy">
                <span class="kicker">Atención presencial</span>
                <h1 id="welcome-title">Tu ficha,<br><em>sin filas.</em></h1>
                <p class="welcome-lead">
                    Identifícate con tu cédula, elige especialidad y horario,
                    y recibe tu ficha con código QR.
                </p>
                <div class="welcome-actions">
                    <button class="btn btn-primary btn-xl" type="button" data-action="start">
                        <span>Sacar mi ficha</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6"/>
                        </svg>
                    </button>
                    <a class="btn btn-soft btn-xl" href="<?= e(url('/kiosco-como-funciona')) ?>" target="_blank" rel="noopener">
                        ¿Cómo funciona?
                    </a>
                </div>
                <p class="welcome-note">
                    <span class="dot" aria-hidden="true"></span>
                    Sin cuenta. Solo tu cédula de identidad.
                </p>
            </div>

            <aside class="welcome-side" aria-label="Resumen del proceso">
                <div class="welcome-card">
                    <div class="welcome-card-head">
                        <span class="pulse-dot" aria-hidden="true"></span>
                        Kiosco habilitado
                    </div>
                    <ol class="welcome-steps">
                        <li>
                            <span>01</span>
                            <div><strong>Identifícate</strong><small>Ingresa tu cédula</small></div>
                        </li>
                        <li>
                            <span>02</span>
                            <div><strong>Elige tu atención</strong><small>Especialidad, médico y horario</small></div>
                        </li>
                        <li>
                            <span>03</span>
                            <div><strong>Recibe tu ficha</strong><small>Código, clave y QR</small></div>
                        </li>
                    </ol>

                    <div class="demo-box">
                        <span class="demo-label">Modo demostración</span>
                        <p>Prueba el flujo completo con un paciente de ejemplo.</p>
                        <div class="demo-actions">
                            <button class="btn btn-ghost btn-sm" type="button" data-action="demo">
                                Probar con CI 10000001
                            </button>
                            <button class="btn btn-ghost btn-sm" type="button" data-action="start">
                                Paciente nuevo
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <!-- Identificación --------------------------------------------------->
        <section class="screen" data-screen="identify" aria-labelledby="identify-title" hidden>
            <div class="screen-head">
                <button class="btn-back" type="button" data-action="go-welcome" aria-label="Volver">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M19 12H5M11 18l-6-6 6-6"/>
                    </svg>
                </button>
                <div>
                    <span class="screen-kicker">Identificación</span>
                    <h2 id="identify-title">Ingresa tu cédula</h2>
                    <p>La usamos para encontrar tu registro. Si es tu primera visita, te pediremos tus datos básicos.</p>
                </div>
            </div>

            <div class="identify-grid">
                <div class="identify-panel">
                    <label class="ci-label" for="ci-input">Cédula de identidad</label>
                    <div class="ci-field">
                        <input
                            id="ci-input"
                            class="ci-input"
                            data-ci-input
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            spellcheck="false"
                            maxlength="14"
                            placeholder="00000000"
                            aria-describedby="ci-hint ci-error"
                        >
                    </div>
                    <p class="field-hint" id="ci-hint">Solo números. Si tu CI lleva complemento, escríbelo con guion (ej. 12345678-1A).</p>
                    <p class="field-error" id="ci-error" data-ci-error role="alert" hidden></p>

                    <div class="identify-actions">
                        <button class="btn btn-primary btn-xl" type="button" data-action="identify">
                            Continuar
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6"/>
                            </svg>
                        </button>
                        <button class="btn btn-soft" type="button" data-action="backspace" aria-label="Borrar un dígito">Borrar</button>
                    </div>
                </div>

                <div class="keypad" data-keypad aria-label="Teclado numérico">
                    <button type="button" data-key="1">1</button>
                    <button type="button" data-key="2">2</button>
                    <button type="button" data-key="3">3</button>
                    <button type="button" data-key="4">4</button>
                    <button type="button" data-key="5">5</button>
                    <button type="button" data-key="6">6</button>
                    <button type="button" data-key="7">7</button>
                    <button type="button" data-key="8">8</button>
                    <button type="button" data-key="9">9</button>
                    <button type="button" class="keypad-soft" data-key="clear" aria-label="Limpiar todo">C</button>
                    <button type="button" data-key="0">0</button>
                    <button type="button" class="keypad-soft" data-key="back" aria-label="Borrar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 5H9L3 12l6 7h12a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1Z"/>
                            <path d="m17 9-6 6M11 9l6 6"/>
                        </svg>
                    </button>
                </div>
            </div>
        </section>

        <!-- Verificación simulada ------------------------------------------->
        <section class="screen screen-verify" data-screen="verify" aria-labelledby="verify-title" hidden>
            <div class="verify-panel">
                <div class="scan-shell" aria-hidden="true">
                    <div class="scan-ring"></div>
                    <div class="scan-ring scan-ring-2"></div>
                    <div class="scan-face">
                        <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M32 8c-9.4 0-16 7.4-16 17v7c0 9.6 6.6 17 16 17s16-7.4 16-17v-7C48 15.4 41.4 8 32 8Z"/>
                            <path d="M22 30h.01M42 30h.01"/>
                            <path d="M26 41c3.4 2.4 8.6 2.4 12 0"/>
                        </svg>
                    </div>
                    <span class="scan-line"></span>
                </div>
                <span class="screen-kicker">Verificación facial</span>
                <h2 id="verify-title" data-verify-title>Verificando identidad…</h2>
                <p data-verify-text>Comparando tu rostro con el registro de la clínica.</p>
                <div class="progress-track" aria-hidden="true"><span class="progress-bar" data-verify-progress></span></div>
                <p class="verify-note">Verificación simulada para esta demostración. No se captura ni almacena imagen.</p>
            </div>
        </section>

        <!-- Alta rápida ------------------------------------------------------>
        <section class="screen" data-screen="register" aria-labelledby="register-title" hidden>
            <div class="screen-head">
                <button class="btn-back" type="button" data-action="go-identify" aria-label="Volver">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M19 12H5M11 18l-6-6 6-6"/>
                    </svg>
                </button>
                <div>
                    <span class="screen-kicker">Primera visita</span>
                    <h2 id="register-title">Completa tus datos</h2>
                    <p>No creas una cuenta: solo guardamos lo necesario para tu atención.</p>
                </div>
                <span class="ci-chip" data-register-ci>CI —</span>
            </div>

            <form class="register-form" data-register-form novalidate>
                <div class="form-grid">
                    <label class="field">
                        <span>Nombres</span>
                        <input type="text" name="nombres" autocomplete="given-name" maxlength="100" required placeholder="María José">
                    </label>
                    <label class="field">
                        <span>Apellidos</span>
                        <input type="text" name="apellidos" autocomplete="family-name" maxlength="100" required placeholder="Gómez Quispe">
                    </label>
                    <label class="field">
                        <span>Fecha de nacimiento</span>
                        <input type="date" name="fecha_nacimiento" required>
                    </label>
                    <label class="field">
                        <span>Teléfono</span>
                        <input type="tel" name="telefono" inputmode="tel" maxlength="15" required placeholder="71234567">
                    </label>
                    <fieldset class="field field-sex">
                        <legend>Sexo</legend>
                        <div class="segmented">
                            <label><input type="radio" name="sexo" value="femenino" required><span>Femenino</span></label>
                            <label><input type="radio" name="sexo" value="masculino"><span>Masculino</span></label>
                        </div>
                    </fieldset>
                </div>

                <p class="field-error form-error" data-register-error role="alert" hidden></p>

                <div class="form-actions">
                    <button class="btn btn-primary btn-xl" type="submit">
                        Continuar
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6"/>
                        </svg>
                    </button>
                    <span class="form-note">Al continuar aceptas el registro para tu atención presencial.</span>
                </div>
            </form>
        </section>

        <!-- Especialidad ----------------------------------------------------->
        <section class="screen" data-screen="specialty" aria-labelledby="specialty-title" hidden>
            <div class="screen-head">
                <button class="btn-back" type="button" data-action="go-welcome" aria-label="Volver al inicio">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M19 12H5M11 18l-6-6 6-6"/>
                    </svg>
                </button>
                <div>
                    <span class="screen-kicker">Paso 1 de 4</span>
                    <h2 id="specialty-title">¿Qué especialidad necesitas?</h2>
                    <p data-patient-greeting>Elige la especialidad de tu consulta.</p>
                </div>
            </div>

            <div class="state-box" data-specialties-loading>
                <span class="spinner" aria-hidden="true"></span>
                <p>Cargando especialidades…</p>
            </div>
            <div class="state-box state-error" data-specialties-error hidden>
                <p data-specialties-error-text>No pudimos cargar las especialidades.</p>
                <button class="btn btn-soft btn-sm" type="button" data-action="retry-specialties">Reintentar</button>
            </div>
            <div class="card-grid specialty-grid" data-specialties></div>
        </section>

        <!-- Médico ----------------------------------------------------------->
        <section class="screen" data-screen="doctor" aria-labelledby="doctor-title" hidden>
            <div class="screen-head">
                <button class="btn-back" type="button" data-action="go-specialty" aria-label="Volver a especialidades">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M19 12H5M11 18l-6-6 6-6"/>
                    </svg>
                </button>
                <div>
                    <span class="screen-kicker">Paso 2 de 4</span>
                    <h2 id="doctor-title">Elige tu médico</h2>
                    <p data-doctor-subtitle>Profesionales disponibles para la especialidad seleccionada.</p>
                </div>
                <span class="pill" data-specialty-badge></span>
            </div>

            <div class="state-box" data-doctors-loading>
                <span class="spinner" aria-hidden="true"></span>
                <p>Cargando médicos…</p>
            </div>
            <div class="state-box state-error" data-doctors-error hidden>
                <p>No pudimos cargar los médicos.</p>
                <button class="btn btn-soft btn-sm" type="button" data-action="retry-doctors">Reintentar</button>
            </div>
            <div class="card-grid doctor-grid" data-doctors></div>
        </section>

        <!-- Horario ---------------------------------------------------------->
        <section class="screen" data-screen="schedule" aria-labelledby="schedule-title" hidden>
            <div class="screen-head">
                <button class="btn-back" type="button" data-action="go-doctor" aria-label="Volver a médicos">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M19 12H5M11 18l-6-6 6-6"/>
                    </svg>
                </button>
                <div>
                    <span class="screen-kicker">Paso 3 de 4</span>
                    <h2 id="schedule-title">Elige fecha y hora</h2>
                    <p data-schedule-subtitle>Selecciona un horario disponible.</p>
                </div>
                <span class="pill pill-soft" data-schedule-doctor></span>
            </div>

            <div class="date-strip" data-dates role="group" aria-label="Fechas disponibles"></div>

            <div class="schedule-body">
                <div class="state-box" data-slots-loading>
                    <span class="spinner" aria-hidden="true"></span>
                    <p>Buscando horarios…</p>
                </div>
                <div class="state-box state-empty" data-slots-empty hidden>
                    <p>No quedan horarios de kiosco para este día.</p>
                    <small>Prueba con otra fecha del calendario.</small>
                </div>
                <div class="slot-grid" data-slots role="group" aria-label="Horarios disponibles"></div>
            </div>

            <div class="schedule-actions">
                <span class="selection-note" data-selection-note>Selecciona un horario para continuar.</span>
                <button class="btn btn-primary btn-xl" type="button" data-action="to-confirm" disabled>
                    Continuar
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14M13 6l6 6-6 6"/>
                    </svg>
                </button>
            </div>
        </section>

        <!-- Confirmación ------------------------------------------------------>
        <section class="screen" data-screen="confirm" aria-labelledby="confirm-title" hidden>
            <div class="screen-head">
                <button class="btn-back" type="button" data-action="go-schedule" aria-label="Volver al horario">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M19 12H5M11 18l-6-6 6-6"/>
                    </svg>
                </button>
                <div>
                    <span class="screen-kicker">Paso 3 de 4 · Confirmación</span>
                    <h2 id="confirm-title">Confirma tu cita</h2>
                    <p>Revisa los datos antes de generar tu ficha.</p>
                </div>
            </div>

            <div class="confirm-layout">
                <div class="confirm-card">
                    <div class="confirm-row confirm-main">
                        <div class="avatar-lg" data-confirm-avatar aria-hidden="true">JP</div>
                        <div>
                            <span class="confirm-label">Paciente</span>
                            <strong data-confirm-patient>—</strong>
                            <small data-confirm-ci>CI —</small>
                        </div>
                    </div>
                    <dl class="confirm-list">
                        <div><dt>Especialidad</dt><dd data-confirm-specialty>—</dd></div>
                        <div><dt>Médico</dt><dd data-confirm-doctor>—</dd></div>
                        <div><dt>Fecha</dt><dd data-confirm-date>—</dd></div>
                        <div><dt>Hora</dt><dd data-confirm-time>—</dd></div>
                        <div><dt>Duración</dt><dd data-confirm-duration>—</dd></div>
                    </dl>
                </div>

                <aside class="confirm-side">
                    <span class="kicker">Todo listo</span>
                    <h3>Tu ficha se genera al confirmar.</h3>
                    <ul class="check-list">
                        <li>La cita queda confirmada en el sistema.</li>
                        <li>Recibirás un código FIC y una clave de 4 dígitos.</li>
                        <li>Podrás imprimir la ficha o mostrarla en pantalla.</li>
                    </ul>
                    <p class="field-error" data-confirm-error role="alert" hidden></p>
                    <button class="btn btn-primary btn-xl" type="button" data-action="confirm">
                        <span data-confirm-label>Confirmar y generar ficha</span>
                    </button>
                    <button class="btn btn-soft" type="button" data-action="go-schedule">Cambiar horario</button>
                </aside>
            </div>
        </section>

        <!-- Ficha ------------------------------------------------------------->
        <section class="screen screen-ficha" data-screen="ficha" aria-labelledby="ficha-title" hidden>
            <div class="ficha-layout" data-ficha-content>
                <article class="ficha-ticket" data-print-area aria-label="Ficha de atención">
                    <header class="ticket-head">
                        <div class="ticket-brand">
                            <span class="brand-mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 5v14M5 12h14"/>
                                </svg>
                            </span>
                            <div>
                                <strong data-ficha-clinica>Clínica Vida Salud</strong>
                                <small>Ficha de atención</small>
                            </div>
                        </div>
                        <span class="ticket-tag" data-ficha-estado>Confirmada</span>
                    </header>

                    <div class="ticket-code">
                        <span>Código de ficha</span>
                        <strong data-ficha-codigo>FIC-0000</strong>
                    </div>

                    <div class="ticket-access">
                        <span>Clave de acceso</span>
                        <strong data-ficha-acceso>0000</strong>
                    </div>

                    <dl class="ticket-data">
                        <div><dt>Paciente</dt><dd data-ficha-paciente>—</dd></div>
                        <div><dt>CI</dt><dd data-ficha-ci>—</dd></div>
                        <div><dt>Especialidad</dt><dd data-ficha-especialidad>—</dd></div>
                        <div><dt>Médico</dt><dd data-ficha-medico>—</dd></div>
                        <div><dt>Fecha</dt><dd data-ficha-fecha>—</dd></div>
                        <div><dt>Hora</dt><dd data-ficha-hora>—</dd></div>
                    </dl>

                    <div class="ticket-bottom">
                        <div class="ticket-qr" data-ficha-qr aria-label="Código QR de la ficha"></div>
                        <div class="ticket-note">
                            <strong data-ficha-cita>CM-0000</strong>
                            <p data-ficha-instrucciones>Presenta esta ficha en recepción. Sin ficha no se atiende.</p>
                        </div>
                    </div>
                </article>

                <aside class="ficha-side">
                    <span class="kicker">¡Listo!</span>
                    <h2 id="ficha-title">Tu ficha está emitida.</h2>
                    <p>Muestra el código en recepción. También puedes imprimirla o llevarla en tu teléfono.</p>

                    <div class="ficha-meta">
                        <span>Cita</span>
                        <strong data-ficha-cita-side>CM-0000</strong>
                    </div>
                    <div class="ficha-meta">
                        <span>Verificación</span>
                        <strong data-ficha-verificacion>Simulada</strong>
                    </div>

                    <button class="btn btn-primary btn-xl" type="button" data-action="print">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                            <rect x="6" y="14" width="12" height="8" rx="1"/>
                        </svg>
                        Imprimir ficha
                    </button>
                    <button class="btn btn-soft btn-xl" type="button" data-action="reset">Nueva ficha</button>

                    <div class="reset-timer">
                        <span>La pantalla se reinicia en <strong data-reset-count>30</strong> s</span>
                        <div class="progress-track" aria-hidden="true"><span class="progress-bar" data-reset-progress></span></div>
                    </div>
                </aside>
            </div>

            <div class="ficha-fallback" data-ficha-fallback hidden>
                <span class="state-icon" aria-hidden="true">!</span>
                <h2>La cita quedó reservada</h2>
                <p>
                    No pudimos generar la ficha en este momento. Tu cita
                    <strong data-fallback-cita>CM-0000</strong> está confirmada;
                    acércate a recepción o reintenta.
                </p>
                <p class="field-error" data-ficha-error role="alert" hidden></p>
                <div class="welcome-actions">
                    <button class="btn btn-primary btn-xl" type="button" data-action="retry-ficha">Reintentar ficha</button>
                    <button class="btn btn-soft btn-xl" type="button" data-action="reset">Nueva ficha</button>
                </div>
            </div>
        </section>
    </main>

    <footer class="kiosk-foot">
        <span>¿Necesitas ayuda? Acércate a recepción.</span>
        <a href="<?= e(url('/kiosco-como-funciona')) ?>" target="_blank" rel="noopener">Cómo funciona el kiosco</a>
    </footer>
</div>

<div class="kiosk-toast" data-toast role="status" aria-live="polite" hidden></div>

<div class="kiosk-overlay" data-overlay hidden>
    <div class="overlay-card">
        <span class="state-icon" aria-hidden="true">!</span>
        <h2>Sesión del kiosco interrumpida</h2>
        <p data-overlay-text>No pudimos conectar con la clínica. Revisa la conexión e inténtalo de nuevo.</p>
        <button class="btn btn-primary btn-xl" type="button" data-action="reconnect">Reintentar</button>
    </div>
</div>
