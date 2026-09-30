<?php

declare(strict_types=1);

/**
 * Vista de inicio de sesión.
 *
 * Variables esperadas:
 *
 * - $csrfToken
 * - $errors
 * - $old
 */
?>

<div class="auth-page">

    <section class="panel auth-panel">

        <!--
            Cabecera de la pantalla de autenticación.
        -->
        <div class="panel-header">

            <h1>Iniciar sesión</h1>

            <p class="panel-description">
                Accede a tu cuenta para compartir tus fotografías.
            </p>
        </div>

        <!--
            Mostramos los errores generales de autenticación.
        -->
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error" role="alert">
                <?= htmlspecialchars(
                    (string) $errors['general'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>
        <?php endif; ?>

        <!--
            Formulario de login.
        -->
        <form
            method="POST"
            action="/login"
            class="auth-form"
        >

            <!--
                Token CSRF.
            -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrfToken,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <!-- Email -->
            <div class="form-group">

                <label for="email">
                    Correo electrónico
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars(
                        (string) ($old['email'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    autocomplete="email"
                    required
                >

            </div>

            <!-- Contraseña -->
            <div class="form-group">

                <label for="password">
                    Contraseña
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

            </div>

            <!-- Botón -->
            <button
                type="submit"
                class="button button-primary"
            >
                Iniciar sesión
            </button>

        </form>

        <!--
            Enlaces relacionados con la autenticación.
            El restablecimiento de contraseña lo implementaremos
            posteriormente.
        -->
        <div class="auth-links">

            <p>
                ¿Todavía no tienes una cuenta?
                <a href="/register">
                    Crear cuenta
                </a>
            </p>

        </div>

    </section>

</div>