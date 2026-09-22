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

<section>
    <h2>Create your account</h2>

    <form method="POST" action="/register">

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

        <div>
            <label for="username">Username</label>

            <input
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
            >

            <?php if ($usernameError !== null): ?>
                <p>
                    <?= htmlspecialchars(
                        $usernameError,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>
            <?php endif; ?>
        </div>

        <div>
            <label for="email">Email</label>

            <input
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
            >

            <?php if ($emailError !== null): ?>
                <p>
                    <?= htmlspecialchars(
                        $emailError,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>
            <?php endif; ?>
        </div>

        <div>
            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
                required
                autocomplete="new-password"
            >

            <p>
                At least 8 characters, including uppercase,
                lowercase and a number.
            </p>

            <?php if ($passwordError !== null): ?>
                <p>
                    <?= htmlspecialchars(
                        $passwordError,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>
            <?php endif; ?>
        </div>

        <button type="submit">Create account</button>
    </form>
</section>