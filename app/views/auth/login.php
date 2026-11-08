<?php
/** @var string $csrfToken */
?>
<header class="auth-head">
    <span class="auth-kicker">Bienvenido de nuevo</span>
    <h1 id="auth-title">Entra a tu <em>panel.</em></h1>
    <p>Gestiona la agenda del día, pacientes y fichas desde un solo lugar.</p>
</header>

<form class="auth-form" method="post" action="<?= e(url('/auth/login')) ?>"
      data-auth-form data-auth-mode="login" novalidate>
    <?= csrf_field() ?>

    <div class="field">
        <label for="identificador">Usuario, correo o CI</label>
        <div class="input-wrap">
            <svg class="icon input-icon" aria-hidden="true"><use href="#i-user"></use></svg>
            <input id="identificador" name="identificador" type="text"
                   value="<?= e(old('identificador', '')) ?>"
                   placeholder="Ej. recepcion o 10000001"
                   autocomplete="username" autocapitalize="none" spellcheck="false"
                   required autofocus>
        </div>
        <p class="field-hint">El mismo acceso sirve para todo el equipo.</p>
    </div>

    <div class="field">
        <div class="field-label-row">
            <label for="password">Contraseña</label>
            <a class="field-link" href="<?= e(url('/recuperar')) ?>">¿La olvidaste?</a>
        </div>
        <div class="input-wrap">
            <svg class="icon input-icon" aria-hidden="true"><use href="#i-lock"></use></svg>
            <input id="password" name="password" type="password" placeholder="Tu contraseña"
                   autocomplete="current-password" required data-password>
            <button class="input-action" type="button" data-password-toggle aria-label="Mostrar contraseña">
                <svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg>
            </button>
        </div>
    </div>

    <button class="btn btn-primary btn-block auth-submit" type="submit">
        <span class="btn-label">Ingresar al panel</span>
        <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
    </button>
</form>

<div class="auth-divider"><span>o</span></div>

<a class="btn btn-ghost btn-block auth-google" href="<?= e(url('/auth/google')) ?>">
    <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
        <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3c-1.6 4.6-6 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l5.7-5.7C34.5 6 29.5 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.6-.4-3.5z"/>
        <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.6 15.6 18.9 13 24 13c3.1 0 5.8 1.1 8 3l5.7-5.7C34.5 6 29.5 4 24 4c-7.5 0-14 4.3-17.7 10.7z"/>
        <path fill="#4CAF50" d="M24 44c5.4 0 10.3-2.1 14-5.5l-6.5-5.5c-2 1.4-4.6 2.3-7.5 2.3-5.3 0-9.7-3.4-11.3-8.1l-6.5 5C9.9 39.6 16.4 44 24 44z"/>
        <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.1-4 5.5l6.5 5.5C40.3 36.5 44 31 44 24c0-1.3-.1-2.6-.4-3.5z"/>
    </svg>
    <span class="btn-label">Ingresar con Google</span>
</a>
<p class="field-hint auth-google-hint">Solo para administración del sistema.</p>

<div class="auth-links">
    <a class="auth-link-card" href="<?= e(url('/activar')) ?>">
        <span class="auth-link-icon" aria-hidden="true"><svg class="icon"><use href="#i-user-plus"></use></svg></span>
        <span>
            <strong>Activar mi cuenta</strong>
            <small>Para pacientes atendidos por primera vez</small>
        </span>
        <svg class="icon auth-link-arrow" aria-hidden="true"><use href="#i-arrow-right"></use></svg>
    </a>
    <a class="auth-link-card" href="<?= e(url('/reservar')) ?>">
        <span class="auth-link-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar-plus"></use></svg></span>
        <span>
            <strong>Reservar una cita</strong>
            <small>Agenda web para pacientes</small>
        </span>
        <svg class="icon auth-link-arrow" aria-hidden="true"><use href="#i-arrow-up-right"></use></svg>
    </a>
</div>
