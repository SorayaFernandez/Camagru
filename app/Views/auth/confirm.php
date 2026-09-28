<?php

declare(strict_types=1);
?>

<section>
    <?php if ($success): ?>

        <h2>Account confirmed</h2>

        <p>
            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>

        <p>
            <a href="/login">Go to login</a>
        </p>

    <?php else: ?>

        <h2>Confirmation failed</h2>

        <p>
            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>

        <p>
            Please request a new confirmation email if necessary.
        </p>

    <?php endif; ?>
</section>