<?php

declare(strict_types=1);

// Valores anteriores para no obligar al usuario a escribirlos de nuevo
// si otro campo del formulario contiene un error.
$oldUsername = $old['username'] ?? '';
$oldEmail = $old['email'] ?? '';

$usernameError = $errors['username'] ?? null;
$emailError = $errors['email'] ?? null;
$passwordError = $errors['password'] ?? null;
?>

<!-- Panel de registro con el estilo global de Camagru. -->
<section class="page-heading">
    <h1>Create your account</h1>

    <p>
        Join Camagru and start creating your own visual stories.
    </p>
</section>

<section class="panel">
    <form method="POST" action="/register" class="form">

        <!-- Token de seguridad contra ataques CSRF. -->
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $csrfToken,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <div class="form-group">
            <label class="form-label" for="username">
                Username
            </label>

            <input
                class="form-control"
                type="text"
                id="username"
                name="username"
                value="<?= htmlspecialchars(
                    $oldUsername,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                minlength="3"
                maxlength="50"
                pattern="[A-Za-z0-9_]{3,50}"
                required
                autocomplete="username"
                placeholder="Choose a username"
            >

            <?php if ($usernameError !== null): ?>
                <p class="alert alert-error">
                    <?= htmlspecialchars(
                        $usernameError,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label class="form-label" for="email">
                Email address
            </label>

            <input
                class="form-control"
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars(
                    $oldEmail,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                maxlength="255"
                required
                autocomplete="email"
                placeholder="you@example.com"
            >

            <?php if ($emailError !== null): ?>
                <p class="alert alert-error">
                    <?= htmlspecialchars(
                        $emailError,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">
                Password
            </label>

            <input
                class="form-control"
                type="password"
                id="password"
                name="password"
                minlength="8"
                required
                autocomplete="new-password"
                placeholder="Create a strong password"
            >

            <p class="form-help">
                At least 8 characters, including uppercase,
                lowercase and a number.
            </p>

            <?php if ($passwordError !== null): ?>
                <p class="alert alert-error">
                    <?= htmlspecialchars(
                        $passwordError,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>
            <?php endif; ?>
        </div>

        <button class="button button-primary" type="submit">
            Create account
        </button>
    </form>
</section>