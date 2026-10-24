<?php
/** @var string $csrfToken */
?>
<header class="auth-head">
    <span class="auth-kicker">Primera vez aquí</span>
    <h1>Activa tu <em>cuenta.</em></h1>
    <p>Si ya te atendimos en la clínica, confirma tus datos para entrar a tu portal de paciente.</p>
</header>

<form class="auth-form" method="post" action="<?= e(url('/auth/activar')) ?>"
      data-auth-form data-auth-mode="activar" data-success-redirect="/login" novalidate>
    <?= csrf_field() ?>

    <div class="field-grid">
        <div class="field">
            <label for="ci">Cédula de identidad</label>
            <div class="input-wrap">
                <svg class="icon input-icon" aria-hidden="true"><use href="#i-card"></use></svg>
                <input id="ci" name="ci" type="text" inputmode="numeric" value="<?= e(old('ci', '')) ?>"
                       placeholder="Ej. 10000001" autocomplete="off" required autofocus>
            </div>
        </div>
        <div class="field">
            <label for="telefono">Teléfono registrado</label>
            <div class="input-wrap">
                <svg class="icon input-icon" aria-hidden="true"><use href="#i-phone"></use></svg>
                <input id="telefono" name="telefono" type="tel" inputmode="tel" value="<?= e(old('telefono', '')) ?>"
                       placeholder="Ej. 70000000" autocomplete="tel" required>
            </div>
        </div>
    </div>

    <div class="field">
        <label for="email">Correo electrónico <span class="label-hint">(opcional)</span></label>
        <div class="input-wrap">
            <svg class="icon input-icon" aria-hidden="true"><use href="#i-mail"></use></svg>
            <input id="email" name="email" type="email" value="<?= e(old('email', '')) ?>"
                   placeholder="nombre@correo.com" autocomplete="email">
        </div>
        <p class="field-hint">Lo usaremos para avisos de tus citas y recuperación de acceso.</p>
    </div>

    <div class="field">
        <label for="password">Crea tu contraseña</label>
        <div class="input-wrap">
            <svg class="icon input-icon" aria-hidden="true"><use href="#i-lock"></use></svg>
            <input id="password" name="password" type="password" placeholder="Mínimo 8 caracteres"
                   autocomplete="new-password" minlength="8" required data-password>
            <button class="input-action" type="button" data-password-toggle aria-label="Mostrar contraseña">
                <svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg>
            </button>
        </div>
        <div class="password-meter" data-password-meter aria-hidden="true">
            <span></span><span></span><span></span><span></span>
        </div>
    </div>

    <div class="field">
        <label for="password_confirmation">Repite la contraseña</label>
        <div class="input-wrap">
            <svg class="icon input-icon" aria-hidden="true"><use href="#i-shield"></use></svg>
            <input id="password_confirmation" name="password_confirmation" type="password"
                   placeholder="Confirma tu contraseña" autocomplete="new-password" minlength="8"
                   required data-password>
            <button class="input-action" type="button" data-password-toggle aria-label="Mostrar contraseña">
                <svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg>
            </button>
        </div>
    </div>

    <button class="btn btn-primary btn-block auth-submit" type="submit">
        <span class="btn-label">Activar mi cuenta</span>
        <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
    </button>
</form>

<a class="auth-back-link" href="<?= e(url('/login')) ?>">
    <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"></use></svg>
    Ya tengo acceso, iniciar sesión
</a>

<div class="auth-note">
    <svg class="icon" aria-hidden="true"><use href="#i-info"></use></svg>
    <p>¿Aún no estás registrado? Saca tu ficha en el <a href="<?= e(url('/kiosco')) ?>">kiosco de la clínica</a> y vuelve con tu CI y teléfono.</p>
</div>
