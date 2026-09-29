<?php

declare(strict_types=1);

/**
 * Versión de texto plano del correo de confirmación.
 *
 * Algunos clientes de correo no muestran HTML.
 * Por eso enviamos también una versión sencilla en texto.
 *
 * Variables esperadas:
 *
 * - $username
 * - $confirmationUrl
 */
?>

Camagru
=======

¡Bienvenido, <?= $username ?>!

Gracias por registrarte en Camagru.

Para completar tu registro y activar tu cuenta, confirma tu dirección
de correo electrónico utilizando el siguiente enlace:

<?= $confirmationUrl ?>


Este enlace es personal y tiene una validez limitada.

Si no has creado esta cuenta, puedes ignorar este mensaje.

© Camagru