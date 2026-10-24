<?php
/** @var string $csrfToken */
?>
<header class="auth-head">
    <span class="auth-kicker">Recuperar acceso</span>
    <h1>Vuelve a tu <em>cuenta.</em></h1>
    <p>Escribe el correo con el que te registraron. Te enviaremos un enlace para crear una nueva contraseña.</p>
</header>

<form class="auth-form" method="post" action="<?= e(url('/auth/recuperar')) ?>"
      data-auth-form data-auth-mode="recuperar" data-success-redirect="/login" novalidate>
    <?= csrf_field() ?>

    <div class="field">
        <label for="email">Correo electrónico</label>
        <div class="input-wrap">
            <svg class="icon input-icon" aria-hidden="true"><use href="#i-mail"></use></svg>
            <input id="email" name="email" type="email" value="<?= e(old('email', '')) ?>"
                   placeholder="nombre@correo.com" autocomplete="email" required autofocus>
        </div>
        <p class="field-hint">Por seguridad, no confirmamos si el correo está registrado.</p>
    </div>

    <button class="btn btn-primary btn-block auth-submit" type="submit">
        <span class="btn-label">Enviar enlace</span>
        <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
    </button>
</form>

<a class="auth-back-link" href="<?= e(url('/login')) ?>">
    <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"></use></svg>
    Volver a iniciar sesión
</a>

<div class="auth-note">
    <svg class="icon" aria-hidden="true"><use href="#i-info"></use></svg>
    <p>El enlace caduca en 30 minutos. Si no llega, revisa la carpeta de spam o pide apoyo a recepción.</p>
</div>
