<?php

declare(strict_types=1);

/*
 * Vista del perfil del usuario.
 *
 * Esta página permite:
 * - Consultar los datos actuales de la cuenta.
 * - Modificar username y email.
 * - Cambiar la contraseña.
 *
 * Los formularios utilizan POST y protección CSRF.
 *
 * Variables recibidas desde ProfileController:
 * - $user
 * - $csrfToken
 * - $errors
 * - $success
 * - $old
 */

// Evitamos errores si alguna variable no llega correctamente.
$errors = $errors ?? [];
$success = $success ?? '';
$old = $old ?? [];

/*
 * Escapamos los datos antes de imprimirlos en HTML.
 *
 * Esto evita que un username o email almacenado en la base de datos
 * pueda interpretarse como código HTML o JavaScript.
 */
$username = htmlspecialchars(
    $old['username'] ?? $user['username'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);

$email = htmlspecialchars(
    $old['email'] ?? $user['email'] ?? '',
    ENT_QUOTES,
    'UTF-8'
);
?>

<section class="page-section">

    <div class="section-heading">
        <p class="eyebrow">CUENTA</p>

        <h1>Mi perfil</h1>

        <p>
            Gestiona tus datos personales y la contraseña de tu cuenta.
        </p>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" role="alert">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>


    <!--
     * Formulario para modificar username y email.
     *
     * Se mantiene separado del formulario de contraseña para que
     * cada operación tenga su propio endpoint y validación.
     -->
    <div class="panel">

        <div class="panel-heading">
            <h2>Datos de la cuenta</h2>

            <p>
                Actualiza tu nombre de usuario y dirección de correo.
            </p>
        </div>

        <form method="POST" action="/profile">

            <!-- Protección contra ataques CSRF. -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
            >

            <!-- Indica al controlador qué operación debe realizar. -->
            <input
                type="hidden"
                name="action"
                value="account"
            >

            <div class="form-group">
                <label for="username">Nombre de usuario</label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= $username ?>"
                    minlength="3"
                    maxlength="50"
                    pattern="[A-Za-z0-9_]+"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Correo electrónico</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= $email ?>"
                    autocomplete="email"
                    required
                >
            </div>

            <button type="submit" class="button button-primary">
                Guardar cambios
            </button>
        </form>
    </div>


    <!--
     * Formulario independiente para cambiar la contraseña.
     *
     * La contraseña actual es necesaria para demostrar que el usuario
     * que está realizando el cambio conoce la credencial actual.
     -->
    <div class="panel">

        <div class="panel-heading">
            <h2>Cambiar contraseña</h2>

            <p>
                Utiliza una contraseña diferente y segura.
            </p>
        </div>

        <form method="POST" action="/profile">

            <!-- Protección CSRF. -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
            >

            <!-- Indica al controlador que queremos cambiar la contraseña. -->
            <input
                type="hidden"
                name="action"
                value="password"
            >

            <div class="form-group">
                <label for="current_password">Contraseña actual</label>

                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="form-group">
                <label for="new_password">Nueva contraseña</label>

                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <small>
                    Mínimo 8 caracteres, con mayúsculas, minúsculas y números.
                </small>
            </div>

            <div class="form-group">
                <label for="confirm_password">
                    Repetir nueva contraseña
                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button type="submit" class="button button-secondary">
                Cambiar contraseña
            </button>
        </form>
    </div>

</section>
