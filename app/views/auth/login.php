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
