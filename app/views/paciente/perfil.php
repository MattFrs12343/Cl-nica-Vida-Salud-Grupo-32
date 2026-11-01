<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Mi cuenta</span>
        <h1>Mi perfil</h1>
        <p>Actualiza tus datos de contacto, información clínica y contacto de emergencia.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/portal')) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg>
            Volver al inicio
        </a>
    </div>
</header>

<div class="dashboard-grid">
    <section class="card card-span-2" aria-labelledby="perfil-form-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">Ficha personal</span>
                <h2 id="perfil-form-title">Mis datos</h2>
            </div>
        </div>

        <div data-perfil-identity>
            <div class="table-loading">
                <div class="skeleton skeleton-row"></div>
                <div class="skeleton skeleton-row"></div>
            </div>
        </div>

        <form data-perfil-form novalidate>
            <div class="form-section">
                <div class="form-section-head">
                    <span class="form-step">01</span>
                    <div>
                        <h3>Contacto</h3>
                        <p>Así nos comunicamos contigo para recordatorios y resultados.</p>
                    </div>
                </div>
                <div class="field-grid field-grid-2">
                    <div class="field">
                        <label for="perfil-telefono">Teléfono</label>
                        <div class="input-wrap">
                            <svg class="icon input-icon" aria-hidden="true"><use href="#i-phone"></use></svg>
                            <input id="perfil-telefono" name="telefono" type="tel" inputmode="tel" placeholder="Ej. 70000000" autocomplete="tel">
                        </div>
                    </div>
                    <div class="field">
                        <label for="perfil-email">Correo electrónico</label>
                        <div class="input-wrap">
                            <svg class="icon input-icon" aria-hidden="true"><use href="#i-mail"></use></svg>
                            <input id="perfil-email" name="email" type="email" placeholder="tucorreo@ejemplo.com" autocomplete="email">
                        </div>
                    </div>
                    <div class="field field-span-2">
                        <label for="perfil-direccion">Dirección</label>
                        <input id="perfil-direccion" name="direccion" type="text" placeholder="Zona, calle y número" autocomplete="street-address">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-head">
                    <span class="form-step">02</span>
                    <div>
                        <h3>Información clínica</h3>
                        <p>Ayuda a tu médico a conocer mejor tu estado de salud.</p>
                    </div>
                </div>
                <div class="field-grid field-grid-2">
                    <div class="field">
                        <label for="perfil-sangre">Tipo de sangre</label>
                        <div class="select-wrap">
                            <select id="perfil-sangre" name="tipo_sangre">
                                <option value="">Selecciona…</option>
                                <?php foreach (['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'] as $tipo): ?>
                                    <option value="<?= e($tipo) ?>"><?= e($tipo) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <label for="perfil-alergias">Alergias</label>
                        <textarea id="perfil-alergias" name="alergias" rows="2" placeholder="Medicamentos, alimentos u otras"></textarea>
                    </div>
                    <div class="field">
                        <label for="perfil-cronicas">Enfermedades crónicas</label>
                        <textarea id="perfil-cronicas" name="enfermedades_cronicas" rows="2" placeholder="Diagnósticos relevantes"></textarea>
                    </div>
                    <div class="field">
                        <label for="perfil-medicamentos">Medicamentos actuales</label>
                        <textarea id="perfil-medicamentos" name="medicamentos" rows="2" placeholder="Nombre y dosis, si los conoces"></textarea>
                    </div>
                    <div class="field field-span-2">
                        <label for="perfil-antecedentes">Antecedentes</label>
                        <textarea id="perfil-antecedentes" name="antecedentes" rows="2" placeholder="Cirugías, hospitalizaciones, etc."></textarea>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-head">
                    <span class="form-step">03</span>
                    <div>
                        <h3>Contacto de emergencia</h3>
                        <p>Una persona a quien podamos llamar si fuera necesario.</p>
                    </div>
                </div>
                <div class="field-grid field-grid-3">
                    <div class="field">
                        <label for="perfil-emergencia-nombre">Nombre</label>
                        <input id="perfil-emergencia-nombre" name="emergencia_nombre" type="text" placeholder="Nombre completo">
                    </div>
                    <div class="field">
                        <label for="perfil-emergencia-parentesco">Parentesco</label>
                        <input id="perfil-emergencia-parentesco" name="emergencia_parentesco" type="text" placeholder="Ej. Madre">
                    </div>
                    <div class="field">
                        <label for="perfil-emergencia-telefono">Teléfono</label>
                        <input id="perfil-emergencia-telefono" name="emergencia_telefono" type="tel" inputmode="tel" placeholder="Ej. 70000000">
                    </div>
                </div>
            </div>

            <div class="row-actions" style="gap:8px;margin-top:18px;">
                <button class="btn btn-primary btn-sm" type="submit" data-perfil-submit>
                    <svg class="icon" aria-hidden="true"><use href="#i-check"></use></svg>
                    Guardar cambios
                </button>
            </div>
        </form>
    </section>

    <aside class="dashboard-side">
        <section class="card" aria-labelledby="perfil-summary-title">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Resumen</span>
                    <h2 id="perfil-summary-title">Mi ficha</h2>
                </div>
            </div>
            <div data-perfil-summary>
                <div class="table-loading">
                    <div class="skeleton skeleton-row"></div>
                    <div class="skeleton skeleton-row"></div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Seguridad</span>
                    <h2>Contraseña</h2>
                </div>
            </div>
            <div class="callout callout-warning">
                <svg class="icon" aria-hidden="true"><use href="#i-alert"></use></svg>
                <p>El cambio de contraseña desde el portal aún no está disponible. Solicítalo en recepción para proteger tu cuenta.</p>
            </div>
        </section>

        <section class="card card-accent">
            <span class="card-kicker">Ayuda</span>
            <h2>¿Datos incorrectos?</h2>
            <p>La cédula, nombres, apellidos, fecha de nacimiento y sexo solo pueden modificarse en recepción.</p>
            <a class="btn btn-light btn-sm" href="<?= e(url('/reservar')) ?>">Reservar una cita</a>
        </section>
    </aside>
</div>
