<?php
/** @var array<int, array<string, mixed>> $especialidades */
?>
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow">Clínica Vida Salud</span>
            <h1>Tu salud, con la atención que mereces.</h1>
            <p class="hero-lead">
                Agenda tu cita online en minutos, elige a tu médico y recibe atención cercana.
                También puedes sacar tu ficha en nuestro kiosco presencial.
            </p>

            <div class="hero-actions">
                <a class="button button-primary button-lg" href="<?= e(url('/reservar')) ?>">Agendar cita</a>
                <a class="button button-outline button-lg" href="<?= e(url('/kiosco')) ?>">Sacar ficha</a>
            </div>

            <ul class="hero-points">
                <li>Especialidades para toda la familia</li>
                <li>Confirmación por correo</li>
                <li>Médicos con cupos web y presenciales</li>
            </ul>
        </div>

        <div class="hero-card" aria-hidden="true">
            <div class="hero-card-head">
                <span class="pulse-dot"></span>
                <span>Agenda disponible</span>
            </div>
            <div class="hero-slot">
                <span class="hero-slot-day">Lun</span>
                <strong>08:00</strong>
                <small>Medicina General</small>
            </div>
            <div class="hero-slot hero-slot-active">
                <span class="hero-slot-day">Lun</span>
                <strong>08:30</strong>
                <small>Pediatría</small>
            </div>
            <div class="hero-slot">
                <span class="hero-slot-day">Mar</span>
                <strong>09:00</strong>
                <small>Geriatría</small>
            </div>
        </div>
    </div>
</section>

<section class="quick-access" aria-label="Accesos rápidos">
    <div class="container quick-grid">
        <a class="quick-card" href="#especialidades">
            <span class="quick-icon" aria-hidden="true">✚</span>
            <strong>Especialidades</strong>
            <small>Encuentra la atención que necesitas.</small>
        </a>
        <a class="quick-card" href="#medicos">
            <span class="quick-icon" aria-hidden="true">◉</span>
            <strong>Médicos</strong>
            <small>Conoce a nuestro equipo profesional.</small>
        </a>
        <a class="quick-card" href="<?= e(url('/kiosco')) ?>">
            <span class="quick-icon" aria-hidden="true">▣</span>
            <strong>Kiosco</strong>
            <small>Ficha presencial con código QR.</small>
        </a>
        <a class="quick-card" href="#contacto">
            <span class="quick-icon" aria-hidden="true">☎</span>
            <strong>Contacto</strong>
            <small>Estamos para ayudarte.</small>
        </a>
    </div>
</section>

<section class="section" id="especialidades">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Atención médica</span>
                <h2>Especialidades</h2>
                <p>Las especialidades destacadas aparecen primero. Selecciona una para ver sus médicos.</p>
            </div>
            <a class="text-link" href="<?= e(url('/reservar')) ?>">Reservar ahora →</a>
        </div>

        <?php if ($especialidades === []): ?>
            <div class="empty-state">
                <strong>No hay especialidades disponibles.</strong>
                <p>Vuelve a intentarlo en unos minutos.</p>
            </div>
        <?php else: ?>
            <div class="specialty-grid" data-specialty-grid>
                <?php foreach ($especialidades as $especialidad): ?>
                    <button
                        type="button"
                        class="specialty-card<?= $especialidad['destacada'] ? ' is-featured' : '' ?>"
                        data-especialidad-id="<?= e($especialidad['id']) ?>"
                        data-especialidad-nombre="<?= e($especialidad['nombre']) ?>"
                    >
                        <?php if ($especialidad['destacada']): ?>
                            <span class="badge">Destacada</span>
                        <?php endif; ?>
                        <strong><?= e($especialidad['nombre']) ?></strong>
                        <span><?= e($especialidad['descripcion'] ?? 'Atención profesional y cercana.') ?></span>
                        <small>Ver médicos →</small>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-muted" id="medicos">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Nuestro equipo</span>
                <h2>Médicos</h2>
                <p id="medicos-subtitulo">Profesionales activos de la clínica, listos para atenderte.</p>
            </div>
            <a class="text-link" href="<?= e(url('/reservar')) ?>">Agendar con un médico →</a>
        </div>

        <div class="doctor-grid" data-doctor-grid aria-live="polite">
            <div class="empty-state" data-doctors-loading>
                <strong>Cargando médicos…</strong>
                <p>Estamos consultando la disponibilidad del equipo.</p>
            </div>
        </div>
    </div>
</section>

<section class="section" id="nosotros">
    <div class="container about-grid">
        <div>
            <span class="eyebrow">Quiénes somos</span>
            <h2>Medicina cercana, con procesos simples.</h2>
            <p>
                En Clínica Vida Salud combinamos atención profesional con una experiencia digital clara:
                reserva online, recordatorios por correo y fichas con código QR para que llegues y seas atendido.
            </p>
            <p>
                Nuestro equipo trabaja por especialidades y coordina cupos web y presenciales para reducir
                esperas y mejorar tu experiencia.
            </p>
        </div>

        <div class="stat-grid">
            <div class="stat-card">
                <strong>+10</strong>
                <span>especialidades médicas</span>
            </div>
            <div class="stat-card">
                <strong>3</strong>
                <span>canales de atención</span>
            </div>
            <div class="stat-card">
                <strong>100%</strong>
                <span>citas con ficha digital</span>
            </div>
        </div>
    </div>
</section>

<section class="section section-muted" id="noticias">
    <div class="container">
        <div class="section-head">
            <div>
                <span class="eyebrow">Campañas</span>
                <h2>Noticias y prevención</h2>
                <p>Consejos breves para cuidar tu salud durante todo el año.</p>
            </div>
        </div>

        <div class="news-grid">
            <article class="news-card">
                <span class="news-tag">Prevención</span>
                <h3>Chequeos anuales: cuándo programarlos</h3>
                <p>Un control periódico ayuda a detectar a tiempo factores de riesgo cardiovascular.</p>
            </article>
            <article class="news-card">
                <span class="news-tag">Familia</span>
                <h3>Control pediátrico en cada etapa</h3>
                <p>El seguimiento del crecimiento y las vacunas acompañan el desarrollo infantil.</p>
            </article>
            <article class="news-card">
                <span class="news-tag">Adulto mayor</span>
                <h3>Movilidad y autonomía después de los 60</h3>
                <p>La evaluación geriátrica permite adaptar hábitos y prevenir caídas.</p>
            </article>
        </div>
    </div>
</section>

<section class="section" id="contacto">
    <div class="container contact-grid">
        <div>
            <span class="eyebrow">Contacto</span>
            <h2>Estamos para ayudarte</h2>
            <p>Escríbenos o acércate a la clínica. Si ya tienes una cita, trae tu código de ficha.</p>

            <ul class="contact-list">
                <li><strong>Teléfono</strong><a href="tel:+59170000000">+591 700 00000</a></li>
                <li><strong>WhatsApp</strong><a href="https://wa.me/59170000000" rel="noopener">+591 700 00000</a></li>
                <li><strong>Correo</strong><a href="mailto:contacto@vidasalud.test">contacto@vidasalud.test</a></li>
                <li><strong>Dirección</strong><span>Av. Principal #123, Santa Cruz de la Sierra</span></li>
                <li><strong>Horario</strong><span>Lunes a viernes 08:00–18:00 · Sábados 08:00–12:00</span></li>
            </ul>
        </div>

        <div class="contact-card">
            <h3>¿Cómo reservar?</h3>
            <ol class="steps-list">
                <li><span>1</span> Inicia sesión o activa tu cuenta de paciente.</li>
                <li><span>2</span> Elige especialidad, médico, fecha y horario.</li>
                <li><span>3</span> Recibe tu confirmación y tu código de ficha.</li>
            </ol>
            <a class="button button-primary" href="<?= e(url('/reservar')) ?>">Agendar cita</a>
            <a class="button button-outline" href="<?= e(url('/kiosco')) ?>">Ver kiosco</a>
        </div>
    </div>
</section>
