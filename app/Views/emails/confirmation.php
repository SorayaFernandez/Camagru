<?php

declare(strict_types=1);

/**
 * Plantilla del correo de confirmación de Camagru.
 *
 * Esta plantilla genera únicamente la versión HTML del correo.
 *
 * Variables esperadas:
 *
 * - $username
 *   Nombre de usuario del nuevo miembro.
 *
 * - $confirmationUrl
 *   URL completa que permitirá confirmar la cuenta.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Confirma tu cuenta - Camagru</title>
</head>

<body style="
    margin: 0;
    padding: 0;
    background-color: #09090f;
    color: #f5f5f5;
    font-family: Arial, Helvetica, sans-serif;
">

    <!--
        Contenedor principal del correo.

        Utilizamos estilos inline porque muchos clientes de correo
        eliminan o limitan las hojas de estilo externas.
    -->
    <table
        width="100%"
        cellpadding="0"
        cellspacing="0"
        border="0"
        style="background-color: #09090f; padding: 40px 20px;"
    >
        <tr>
            <td align="center">

                <!--
                    Tarjeta principal del mensaje.
                -->
                <table
                    width="100%"
                    cellpadding="0"
                    cellspacing="0"
                    border="0"
                    style="
                        max-width: 600px;
                        background-color: #11111b;
                        border: 1px solid #a855f7;
                        border-radius: 16px;
                        box-shadow: 0 0 30px rgba(168, 85, 247, 0.25);
                    "
                >
                    <tr>
                        <td style="padding: 40px;">

                            <!-- Logo -->
                            <h1 style="
                                margin: 0 0 30px;
                                text-align: center;
                                font-size: 32px;
                                letter-spacing: 4px;
                                color: #ffffff;
                            ">
                                <span style="color: #ec4899;">CAMA</span><span style="color: #a855f7;">GRU</span>
                            </h1>

                            <!-- Saludo -->
                            <h2 style="
                                margin: 0 0 20px;
                                color: #ffffff;
                                font-size: 24px;
                            ">
                                ¡Bienvenido, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>!
                            </h2>

                            <!-- Texto principal -->
                            <p style="
                                margin: 0 0 16px;
                                color: #d4d4dc;
                                font-size: 16px;
                                line-height: 1.7;
                            ">
                                Gracias por registrarte en Camagru.
                            </p>

                            <p style="
                                margin: 0 0 30px;
                                color: #d4d4dc;
                                font-size: 16px;
                                line-height: 1.7;
                            ">
                                Para completar tu registro y activar tu cuenta,
                                confirma tu dirección de correo electrónico:
                            </p>

                            <!--
                                Botón de confirmación.

                                La URL procede del servidor y contiene el token
                                generado específicamente para este registro.
                            -->
                            <table
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                align="center"
                                style="margin: 0 auto 30px;"
                            >
                                <tr>
                                    <td
                                        align="center"
                                        style="
                                            background-color: #ec4899;
                                            border-radius: 10px;
                                            box-shadow: 0 0 20px rgba(236, 72, 153, 0.35);
                                        "
                                    >
                                        <a
                                            href="<?= htmlspecialchars($confirmationUrl, ENT_QUOTES, 'UTF-8') ?>"
                                            style="
                                                display: inline-block;
                                                padding: 15px 28px;
                                                color: #ffffff;
                                                text-decoration: none;
                                                font-size: 16px;
                                                font-weight: bold;
                                            "
                                        >
                                            Confirmar mi cuenta
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!--
                                Mostramos también el enlace como texto.

                                Algunos clientes de correo pueden bloquear
                                botones HTML o determinados estilos.
                            -->
                            <p style="
                                margin: 0 0 10px;
                                color: #9999aa;
                                font-size: 13px;
                                line-height: 1.6;
                            ">
                                Si el botón no funciona, copia y pega este enlace
                                en tu navegador:
                            </p>

                            <p style="
                                margin: 0 0 30px;
                                color: #3b82f6;
                                font-size: 13px;
                                line-height: 1.6;
                                word-break: break-all;
                            ">
                                <?= htmlspecialchars($confirmationUrl, ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <!-- Seguridad -->
                            <p style="
                                margin: 0;
                                padding-top: 20px;
                                border-top: 1px solid #292938;
                                color: #77778a;
                                font-size: 12px;
                                line-height: 1.6;
                            ">
                                Este enlace de confirmación es personal y tiene
                                una validez limitada. Si no has creado esta cuenta,
                                puedes ignorar este mensaje.
                            </p>

                        </td>
                    </tr>
                </table>

                <!-- Footer -->
                <p style="
                    margin: 20px 0 0;
                    color: #666675;
                    font-size: 12px;
                    text-align: center;
                ">
                    © Camagru
                </p>

            </td>
        </tr>
    </table>

</body>
</html>