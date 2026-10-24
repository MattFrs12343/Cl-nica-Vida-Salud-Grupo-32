<?php
/** @var array<string, mixed> $user */
$esAdmin = in_array((string) ($user['rol'] ?? ''), ['admin', 'super_admin'], true);
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Directorio</span>
        <h1>Pacientes</h1>
        <p>Busca por nombre o cédula, revisa su historial y mantén sus datos al día.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary btn-sm" type="button" data-patient-new>
            Nuevo paciente
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-user-plus"></use></svg></span>
        </button>
    </div>
</header>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" name="q" placeholder="Buscar por nombre, apellido o CI…"
               autocomplete="off" aria-label="Buscar pacientes" data-patient-search>
        <button class="toolbar-clear" type="button" data-patient-clear aria-label="Limpiar búsqueda" hidden>
            <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
    </div>
    <div class="toolbar-filters">
        <label class="select-wrap">
            <span class="sr-only">Filtrar por estado</span>
            <select data-patient-estado>
                <option value="">Todos los estados</option>
                <option value="activo">Activos</option>
                <option value="inactivo">Inactivos</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <button class="icon-btn" type="button" data-patient-refresh aria-label="Actualizar listado">
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
        </button>
    </div>
    <p class="toolbar-count" data-patient-count>—</p>
</section>

<section class="card" aria-labelledby="lista-pacientes-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Directorio clínico</span>
            <h2 id="lista-pacientes-title">Pacientes registrados</h2>
        </div>
    </div>
    <div class="table-wrap" data-patients-table>
        <div class="table-loading">
            <?php for ($i = 0; $i < 7; $i++): ?>
                <div class="skeleton skeleton-row"></div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="pagination" data-patient-pagination hidden></div>
</section>

<div class="modal" id="modal-paciente" data-modal aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel modal-panel-lg" role="dialog" aria-modal="true" aria-labelledby="modal-paciente-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker" data-patient-modal-kicker>Nuevo registro</span>
                <h2 id="modal-paciente-title" data-patient-modal-title>Registrar paciente</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>

        <form data-patient-form novalidate>
            <div class="modal-body">
                <div class="form-section">
                    <div class="form-section-head">
                        <span class="form-step">01</span>
                        <div>
                            <h3>Datos personales</h3>
                            <p>La cédula y el nombre son la base del historial.</p>
                        </div>
                    </div>
                    <div class="field-grid field-grid-3">
                        <div class="field">
                            <label for="pac-ci">Cédula de identidad *</label>
                            <input id="pac-ci" name="ci" type="text" inputmode="numeric" placeholder="Ej. 10000001" required>
                        </div>
                        <div class="field">
                            <label for="pac-nombres">Nombres *</label>
                            <input id="pac-nombres" name="nombres" type="text" placeholder="Nombres" autocomplete="given-name" required>
                        </div>
                        <div class="field">
                            <label for="pac-apellidos">Apellidos *</label>
                            <input id="pac-apellidos" name="apellidos" type="text" placeholder="Apellidos" autocomplete="family-name" required>
                        </div>
                        <div class="field">
                            <label for="pac-fecha">Fecha de nacimiento *</label>
                            <input id="pac-fecha" name="fecha_nacimiento" type="date" max="<?= e(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="field">
                            <label for="pac-sexo">Sexo *</label>
                            <div class="select-wrap">
                                <select id="pac-sexo" name="sexo" required>
                                    <option value="">Selecciona</option>
                                    <option value="femenino">Femenino</option>
                                    <option value="masculino">Masculino</option>
                                </select>
                                <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                            </div>
                        </div>
                        <div class="field">
                            <label for="pac-sangre">Tipo de sangre</label>
                            <div class="select-wrap">
                                <select id="pac-sangre" name="tipo_sangre">
                                    <option value="">Sin dato</option>
                                    <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $tipo): ?>
                                        <option value="<?= e($tipo) ?>"><?= e($tipo) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <span class="form-step">02</span>
                        <div>
                            <h3>Contacto</h3>
                            <p>Datos para confirmar citas y enviar recordatorios.</p>
                        </div>
                    </div>
                    <div class="field-grid field-grid-2">
                        <div class="field">
                            <label for="pac-telefono">Teléfono</label>
                            <div class="input-wrap">
                                <svg class="icon input-icon" aria-hidden="true"><use href="#i-phone"></use></svg>
                                <input id="pac-telefono" name="telefono" type="tel" inputmode="tel" placeholder="Ej. 70000000" autocomplete="tel">
                            </div>
                        </div>
                        <div class="field">
                            <label for="pac-email">Correo electrónico</label>
                            <div class="input-wrap">
                                <svg class="icon input-icon" aria-hidden="true"><use href="#i-mail"></use></svg>
                                <input id="pac-email" name="email" type="email" placeholder="nombre@correo.com" autocomplete="email">
                            </div>
                        </div>
                        <div class="field field-span-2">
                            <label for="pac-direccion">Dirección</label>
                            <input id="pac-direccion" name="direccion" type="text" placeholder="Zona, calle y número" autocomplete="street-address">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <span class="form-step">03</span>
                        <div>
                            <h3>Información clínica</h3>
                            <p>Opcional, pero muy útil para una atención más segura.</p>
                        </div>
                    </div>
                    <div class="field-grid field-grid-2">
                        <div class="field">
                            <label for="pac-alergias">Alergias</label>
                            <textarea id="pac-alergias" name="alergias" rows="2" placeholder="Medicamentos, alimentos u otras"></textarea>
                        </div>
                        <div class="field">
                            <label for="pac-cronicas">Enfermedades crónicas</label>
                            <textarea id="pac-cronicas" name="enfermedades_cronicas" rows="2" placeholder="Diagnósticos relevantes"></textarea>
                        </div>
                        <div class="field">
                            <label for="pac-medicamentos">Medicamentos actuales</label>
                            <textarea id="pac-medicamentos" name="medicamentos" rows="2" placeholder="Nombre y dosis, si los conoces"></textarea>
                        </div>
                        <div class="field">
                            <label for="pac-antecedentes">Antecedentes</label>
                            <textarea id="pac-antecedentes" name="antecedentes" rows="2" placeholder="Cirugías, hospitalizaciones, etc."></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <span class="form-step">04</span>
                        <div>
                            <h3>Contacto de emergencia</h3>
                            <p>A quién avisar si ocurre algo durante la atención.</p>
                        </div>
                    </div>
                    <div class="field-grid field-grid-3">
                        <div class="field">
                            <label for="pac-emergencia-nombre">Nombre</label>
                            <input id="pac-emergencia-nombre" name="emergencia_nombre" type="text" placeholder="Nombre completo">
                        </div>
                        <div class="field">
                            <label for="pac-emergencia-parentesco">Parentesco</label>
                            <input id="pac-emergencia-parentesco" name="emergencia_parentesco" type="text" placeholder="Ej. Madre">
                        </div>
                        <div class="field">
                            <label for="pac-emergencia-telefono">Teléfono</label>
                            <input id="pac-emergencia-telefono" name="emergencia_telefono" type="tel" inputmode="tel" placeholder="Ej. 70000000">
                        </div>
                    </div>
                </div>
            </div>

            <footer class="modal-foot">
                <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cancelar</button>
                <button class="btn btn-primary btn-sm" type="submit">
                    <span class="btn-label">Guardar paciente</span>
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
                </button>
            </footer>
        </form>
    </div>
</div>

<div class="modal" id="modal-historial" data-modal aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel modal-panel-xl" role="dialog" aria-modal="true" aria-labelledby="modal-historial-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Ficha del paciente</span>
                <h2 id="modal-historial-title" data-history-title>Historial</h2>
            </div>
            <div class="modal-head-actions">
                <a class="btn btn-ghost btn-sm" href="#" data-history-schedule hidden>
                    <svg class="icon" aria-hidden="true"><use href="#i-calendar-plus"></use></svg>
                    Agendar
                </a>
                <button class="btn btn-ghost btn-sm" type="button" data-history-edit hidden>
                    <svg class="icon" aria-hidden="true"><use href="#i-edit"></use></svg>
                    Editar
                </button>
                <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                    <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
                </button>
            </div>
        </header>
        <div class="modal-body" data-history-content>
            <div class="table-loading">
                <div class="skeleton skeleton-block"></div>
                <div class="skeleton skeleton-row"></div>
                <div class="skeleton skeleton-row"></div>
            </div>
        </div>
    </div>
</div>
