<?php
/** @var array<int, array<string, mixed>> $especialidades */
/** @var array<int, array<string, mixed>> $medicos */

$medicos = $medicos ?? [];
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

$iniciales = static function (array $medico): string {
    $nombres = trim((string) ($medico['nombres'] ?? ''));
    $apellidos = trim((string) ($medico['apellidos'] ?? ''));
    $primera = $nombres !== '' ? mb_substr($nombres, 0, 1) : '';
    $segunda = $apellidos !== '' ? mb_substr($apellidos, 0, 1) : '';

    return mb_strtoupper($primera . $segunda);
};

$enlaceReserva = static function (array $medico): string {
    $query = ['medico' => (int) ($medico['id'] ?? 0)];
    $primera = $medico['especialidades'][0]['id'] ?? null;

    if ($primera !== null) {
        $query['especialidad'] = (int) $primera;
    }

    return url('/reservar?' . http_build_query($query));
};

$doctoresPorEspecialidad = [];

foreach ($medicos as $medico) {
    foreach (($medico['especialidades'] ?? []) as $especialidad) {
        $especialidadId = (int) ($especialidad['id'] ?? 0);

        if ($especialidadId > 0) {
            $doctoresPorEspecialidad[$especialidadId][] = $medico;
        }
    }
}

$especialidadesConMedicos = array_values(array_filter(
    $especialidades,
    static fn (array $especialidad): bool => count(
        $doctoresPorEspecialidad[(int) ($especialidad['id'] ?? 0)] ?? []
    ) > 0
));
?>
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <p class="hero-kicker"><span class="status-dot" aria-hidden="true"></span> Clínica Vida Salud · Santa Cruz</p>
            <h1>Tu salud,<br><em>a tu ritmo.</em></h1>
            <p class="hero-lead">Reserva online, elige especialidad y médico, y llega con tu cita lista.</p>

            <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(url('/reservar')) ?>">
                    <span>Reservar cita</span>
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-up-right"></use></svg></span>
                </a>
                <a class="btn btn-ghost btn-lg" href="<?= e(url('/kiosco')) ?>">
                    <span>Sacar ficha</span>
                </a>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-frame">
                <img
                    src="<?= e(asset('landing/img/hero-doctor.jpg')) ?>"
                    alt="Doctora de Clínica Vida Salud con uniforme médico"
                    width="1400"
                    height="1750"
                    fetchpriority="high"
                    decoding="async"
                >
                <span class="hero-badge"><span class="status-dot" aria-hidden="true"></span> Agenda abierta</span>
            </div>
            <div class="hero-frame-note" aria-hidden="true">
                <span>Medicina familiar</span>
                <span>SC · BO</span>
            </div>
        </div>
    </div>

    <div class="container signal-rail">
        <div class="signal-item">
            <svg class="icon" aria-hidden="true"><use href="#i-calendar"></use></svg>
            <span>Reserva online 24/7</span>
        </div>
        <div class="signal-item">
            <svg class="icon" aria-hidden="true"><use href="#i-shield"></use></svg>
            <span>Cupos web y presenciales</span>
        </div>
        <div class="signal-item">
            <svg class="icon" aria-hidden="true"><use href="#i-mail"></use></svg>
            <span>Confirmación por correo</span>
        </div>
        <div class="signal-item">
            <svg class="icon" aria-hidden="true"><use href="#i-qr"></use></svg>
            <span>Ficha digital con QR</span>
        </div>
    </div>
</section>

<section class="section specialties" id="especialidades" data-section-reveal>
    <div class="container">
        <div class="specialties-head reveal">
            <h2>Encuentra tu<br><em>especialidad.</em></h2>
            <div class="specialty-search">
                <label class="sr-only" for="buscador-especialidades">Buscar especialidad</label>
                <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
                <input
                    id="buscador-especialidades"
                    type="search"
                    placeholder="Buscar especialidad"
                    autocomplete="off"
                    data-specialty-search
                >
                <span class="search-count" data-specialty-count><?= count($especialidades) ?> especialidades</span>
            </div>
        </div>

        <?php if ($especialidades === []): ?>
            <div class="empty-state">
                <strong>No hay especialidades disponibles.</strong>
                <p>Vuelve a intentarlo en unos minutos.</p>
            </div>
        <?php else: ?>
            <div class="specialty-grid" data-specialty-grid>
                <?php foreach ($especialidades as $indice => $especialidad): ?>
                    <?php
                    $icono = $iconos[(string) ($especialidad['icono'] ?? '')] ?? 'i-spark';
                    $descripcion = trim((string) ($especialidad['descripcion'] ?? ''));
                    $busqueda = mb_strtolower((string) $especialidad['nombre'] . ' ' . $descripcion);
                    $destacada = $indice === 0;
                    $totalDoctores = count($doctoresPorEspecialidad[(int) $especialidad['id']] ?? []);
                    ?>
                    <div
                        class="specialty-shell<?= $destacada ? ' is-featured' : '' ?> reveal"
                        data-specialty-item
                        data-search="<?= e($busqueda) ?>"
                    >
                        <a
                            class="specialty-card"
                            href="<?= e(url('/reservar?especialidad=' . (int) $especialidad['id'])) ?>"
                            data-especialidad-id="<?= e($especialidad['id']) ?>"
                            data-especialidad-nombre="<?= e($especialidad['nombre']) ?>"
                        >
                            <span class="specialty-index" aria-hidden="true"><?= e(str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                            <?php if ($totalDoctores > 0): ?>
                                <span class="specialty-count"><?= e($totalDoctores) ?> <?= $totalDoctores === 1 ? 'médico' : 'médicos' ?></span>
                            <?php endif; ?>
                            <span class="specialty-icon" aria-hidden="true">
                                <svg class="icon"><use href="#<?= e($icono) ?>"></use></svg>
                            </span>
                            <span class="specialty-name"><?= e($especialidad['nombre']) ?></span>
                            <span class="specialty-desc"><?= e($descripcion !== '' ? $descripcion : 'Atención profesional y cercana.') ?></span>
                            <span class="specialty-arrow" aria-hidden="true">
                                <svg class="icon"><use href="#i-arrow-up-right"></use></svg>
                            </span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="specialty-empty" data-specialty-empty hidden>
                <strong>No encontramos esa especialidad.</strong>
                <p>Prueba con otra palabra o <a href="<?= e(url('/reservar')) ?>">reserva una cita</a>.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section doctors" id="medicos" data-section-reveal>
    <div class="container doctors-grid">
        <div class="doctors-intro reveal">
            <h2>Personas que<br><em>escuchan.</em></h2>
            <p>Un equipo que combina experiencia clínica con una atención sin apuros. Elige a tu médico y reserva en línea.</p>

            <div class="doctors-status-row">
                <span class="doctors-status"><span class="status-dot" aria-hidden="true"></span><span data-doctors-count><?= count($medicos) ?> médicos activos</span></span>
                <button class="link-button" type="button" data-doctors-reset hidden>Ver todos</button>
            </div>

            <a class="btn btn-outline" href="<?= e(url('/reservar')) ?>">
                <span>Reservar cita</span>
                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
            </a>
        </div>

        <div class="doctors-directory">
            <?php if ($especialidadesConMedicos !== []): ?>
                <div class="doctor-filters" role="group" aria-label="Filtrar médicos por especialidad">
                    <button class="doctor-filter is-active" type="button" data-doctor-filter="all" aria-pressed="true">
                        Todos
                        <span class="doctor-filter-count"><?= count($medicos) ?></span>
                    </button>
                    <?php foreach ($especialidadesConMedicos as $especialidad): ?>
                        <?php $cantidad = count($doctoresPorEspecialidad[(int) $especialidad['id']] ?? []); ?>
                        <button
                            class="doctor-filter"
                            type="button"
                            data-doctor-filter="<?= e($especialidad['id']) ?>"
                            data-doctor-filter-name="<?= e($especialidad['nombre']) ?>"
                            aria-pressed="false"
                        >
                            <?= e($especialidad['nombre']) ?>
                            <span class="doctor-filter-count"><?= e($cantidad) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="doctors-list" data-doctor-grid aria-live="polite">
                <?php if ($medicos === []): ?>
                    <div class="empty-state empty-state-inline">
                        <strong>El directorio está cargando.</strong>
                        <p>Recarga la página o <a href="<?= e(url('/reservar')) ?>">reserva directamente</a>.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($medicos as $indice => $medico): ?>
                        <article class="doctor-row reveal" data-doctor-id="<?= e($medico['id']) ?>">
                            <span class="doctor-index" aria-hidden="true"><?= e(str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                            <span class="doctor-avatar" aria-hidden="true"><?= e($iniciales($medico)) ?></span>
                            <div class="doctor-main">
                                <h3><?= e($medico['nombre_completo'] ?? ('Dr. ' . $medico['nombres'] . ' ' . $medico['apellidos'])) ?></h3>
                                <ul class="doctor-tags" aria-label="Especialidades">
                                    <?php foreach ($medico['especialidades'] as $especialidad): ?>
                                        <li><?= e($especialidad['nombre']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="doctor-meta">
                                <span class="doctor-duration">
                                    <svg class="icon" aria-hidden="true"><use href="#i-clock"></use></svg>
                                    <?= e($medico['duracion_cita_min']) ?> min
                                </span>
                                <a class="doctor-link" href="<?= e($enlaceReserva($medico)) ?>" aria-label="Agendar cita con <?= e($medico['nombre_completo']) ?>">
                                    <span>Agendar</span>
                                    <svg class="icon" aria-hidden="true"><use href="#i-arrow-up-right"></use></svg>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="section about" id="nosotros" data-section-reveal>
    <div class="container about-grid">
        <div class="about-visual reveal">
            <div class="about-frame">
                <img
                    src="<?= e(asset('landing/img/about-team.jpg')) ?>"
                    alt="Profesional de salud de Clínica Vida Salud"
                    width="1200"
                    height="1500"
                    loading="lazy"
                    decoding="async"
                >
                <span class="about-stamp"><svg class="icon" aria-hidden="true"><use href="#i-spark"></use></svg> Cuidado cercano</span>
            </div>
            <div class="about-fact">
                <strong>3 canales</strong>
                <span>web, kiosco y recepción</span>
            </div>
        </div>

        <div class="about-copy reveal">
            <h2>No es solo una cita.<br><em>Es tiempo para ti.</em></h2>
            <p class="about-lead">Desde la primera llamada hasta tu ficha de atención, cuidamos cada paso para que solo te preocupes por sentirte mejor.</p>

            <div class="principles">
                <div class="principle">
                    <span class="principle-num">01</span>
                    <div>
                        <h3>Escucha primero</h3>
                        <p>Consultas con tiempo para entender qué necesitas, no solo para revisar un síntoma.</p>
                    </div>
                </div>
                <div class="principle">
                    <span class="principle-num">02</span>
                    <div>
                        <h3>Todo en un lugar</h3>
                        <p>Especialidades, agenda y ficha digital coordinadas por el mismo equipo.</p>
                    </div>
                </div>
                <div class="principle">
                    <span class="principle-num">03</span>
                    <div>
                        <h3>Sin complicaciones</h3>
                        <p>Reservas online, confirmación por correo y recordatorios claros.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section access" id="kiosco" data-section-reveal>
    <div class="container">
        <div class="access-head reveal">
            <h2>Dos formas de<br><em>llegar a tu cita.</em></h2>
            <p>Reserva desde casa o saca tu ficha al llegar. Ambos caminos terminan en la misma atención.</p>
        </div>

        <div class="access-grid">
            <article class="access-panel access-panel-pine reveal">
                <div class="access-top">
                    <span class="access-num">01</span>
                    <svg class="icon" aria-hidden="true"><use href="#i-calendar"></use></svg>
                </div>
                <h3>Reserva online</h3>
                <p>Elige especialidad, médico y horario. Recibe la confirmación y llega con tu cita lista.</p>
                <a class="btn btn-light" href="<?= e(url('/reservar')) ?>">
                    <span>Reservar cita</span>
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-up-right"></use></svg></span>
                </a>

                <div class="slot-board" aria-hidden="true">
                    <div class="slot-board-head"><span>Agenda web</span><span>Cupos disponibles</span></div>
                    <div class="slot-board-days"><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span></div>
                    <div class="slot-board-pills">
                        <span>08:00</span><span>08:30</span><span class="is-on">09:00</span><span>10:30</span>
                    </div>
                </div>
            </article>

            <article class="access-panel access-panel-paper reveal">
                <div class="access-top">
                    <span class="access-num">02</span>
                    <svg class="icon" aria-hidden="true"><use href="#i-ticket"></use></svg>
                </div>
                <h3>Kiosco presencial</h3>
                <p>Ingresa tu CI, elige tu horario y obtén una ficha con código y QR. Sin cuenta, sin filas.</p>
                <a class="btn btn-outline" href="<?= e(url('/kiosco')) ?>">
                    <span>Cómo funciona</span>
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
                </a>

                <div class="ticket" aria-hidden="true">
                    <div class="ticket-head"><span>Ficha de atención</span><span>Ejemplo</span></div>
                    <strong>FIC-042</strong>
                    <div class="ticket-barcode"></div>
                    <div class="ticket-foot"><span>Acceso 8420</span><span>Clínica Vida Salud</span></div>
                </div>
            </article>
        </div>
    </div>
</section>

<section class="section news" id="noticias" data-section-reveal>
    <div class="container">
        <div class="news-head reveal">
            <h2>Ideas para<br><em>cuidarte mejor.</em></h2>
        </div>

        <div class="news-grid">
            <article class="news-feature reveal">
                <div class="news-image">
                    <img
                        src="<?= e(asset('landing/img/news-checkup.jpg')) ?>"
                        alt="Estetoscopio sobre una superficie clínica"
                        width="1000"
                        height="750"
                        loading="lazy"
                        decoding="async"
                    >
                </div>
                <div class="news-content">
                    <span class="news-tag">Prevención</span>
                    <h3>Chequeos anuales: cuándo programarlos</h3>
                    <p>Un control periódico ayuda a detectar a tiempo factores de riesgo. Te contamos qué revisar según tu edad.</p>
                </div>
            </article>

            <div class="news-side">
                <article class="news-note news-note-mint reveal">
                    <span class="news-tag">Familia</span>
                    <h3>Control pediátrico en cada etapa</h3>
                    <p>Crecimiento, vacunas y desarrollo: acompañar a tiempo hace la diferencia.</p>
                </article>
                <article class="news-note news-note-coral reveal">
                    <span class="news-tag">Adulto mayor</span>
                    <h3>Movilidad y autonomía después de los 60</h3>
                    <p>La evaluación geriátrica permite adaptar hábitos y prevenir caídas.</p>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="section contact" id="contacto" data-section-reveal>
    <div class="container contact-panel reveal">
        <div class="contact-copy">
            <span class="contact-mark" aria-hidden="true"><svg class="icon"><use href="#i-spark"></use></svg></span>
            <h2>¿Hablamos?</h2>
            <p>Si tienes dudas antes de reservar, escríbenos. Te respondemos en horario de atención.</p>
            <div class="contact-actions">
                <a class="btn btn-dark" href="https://wa.me/59170000000" target="_blank" rel="noopener">
                    <span>Escribir por WhatsApp</span>
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-up-right"></use></svg></span>
                </a>
                <a class="contact-phone" href="tel:+59170000000">+591 700 00000</a>
            </div>
        </div>

        <div class="contact-details">
            <div class="contact-detail">
                <span>Dirección</span>
                <p>Av. Principal #123<br>Santa Cruz de la Sierra</p>
                <a href="https://maps.google.com/?q=Santa+Cruz+de+la+Sierra" target="_blank" rel="noopener">Ver en mapa</a>
            </div>
            <div class="contact-detail">
                <span>Horario</span>
                <p>Lunes a viernes<br>08:00–18:00<br>Sábado 08:00–12:00</p>
            </div>
            <div class="contact-detail">
                <span>Correo</span>
                <a href="mailto:contacto@vidasalud.test">contacto@vidasalud.test</a>
            </div>
        </div>
    </div>
</section>
