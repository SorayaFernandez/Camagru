<?php

declare(strict_types=1);
?>

<section>
    <h2>Registration successful</h2>

    <p>
        Your account has been created.
    </p>

    <p>
        A confirmation email will be sent to:
        <?= htmlspecialchars(
            $email,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>
</section>