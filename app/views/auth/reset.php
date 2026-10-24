<?php
/** @var string $csrfToken */
/** @var string $token */
?>
<header class="auth-head">
    <span class="auth-kicker">Nueva contraseña</span>
    <h1>Crea una clave <em>segura.</em></h1>
    <p>Usa al menos 8 caracteres e incluye una letra y un número.</p>
</header>

<?php if (($token ?? '') === ''): ?>
    <div class="auth-empty">
        <span class="auth-empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-alert"></use></svg></span>
        <h2>El enlace no es válido</h2>
        <p>Puede haber caducado o estar incompleto. Solicita uno nuevo para continuar.</p>
        <a class="btn btn-primary" href="<?= e(url('/recuperar')) ?>">
            Solicitar otro enlace
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
        </a>
    </div>
<?php else: ?>
    <form class="auth-form" method="post" action="<?= e(url('/auth/reset')) ?>"
          data-auth-form data-auth-mode="reset" data-success-redirect="/login" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="field">
            <label for="password">Nueva contraseña</label>
            <div class="input-wrap">
                <svg class="icon input-icon" aria-hidden="true"><use href="#i-lock"></use></svg>
                <input id="password" name="password" type="password" placeholder="Mínimo 8 caracteres"
                       autocomplete="new-password" minlength="8" required autofocus data-password>
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
                       placeholder="Confirma tu nueva contraseña" autocomplete="new-password" minlength="8"
                       required data-password>
                <button class="input-action" type="button" data-password-toggle aria-label="Mostrar contraseña">
                    <svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg>
                </button>
            </div>
        </div>

        <button class="btn btn-primary btn-block auth-submit" type="submit">
            <span class="btn-label">Guardar contraseña</span>
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
        </button>
    </form>

    <a class="auth-back-link" href="<?= e(url('/login')) ?>">
        <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"></use></svg>
        Volver a iniciar sesión
    </a>
<?php endif; ?>
