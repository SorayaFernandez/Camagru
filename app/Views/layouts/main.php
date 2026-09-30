<?php

declare(strict_types=1);

/**
 * Layout principal de Camagru.
 *
 * Este archivo contiene la estructura HTML común a todas las páginas:
 *
 * - Cabecera.
 * - Navegación.
 * - Contenedor principal.
 * - Footer.
 *
 * La navegación cambia dependiendo de si existe una sesión
 * autenticada.
 */
?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Camagru - Comparte tus fotografías."
    >

    <title>Camagru</title>

    <!--
        Hoja de estilos principal de Camagru.
    -->
    <link
        rel="stylesheet"
        href="/css/style.css"
    >

</head>

<body>

    <!--
        Cabecera principal de la aplicación.
    -->
    <header class="site-header">

        <div class="container header-inner">

            <!--
                Logo de Camagru.
                Al pulsarlo volvemos a la página principal.
            -->
            <a
                href="/"
                class="logo"
                aria-label="Camagru - Inicio"
            >
                <span class="logo-pink">CAMA</span><span class="logo-purple">GRU</span>
            </a>

            <!--
                Navegación principal.
            -->
            <nav
                class="main-nav"
                aria-label="Navegación principal"
            >

                <!--
                    Enlace disponible para todos los usuarios.
                -->
                <a href="/">
                    Inicio
                </a>

                <?php if (Auth::check()): ?>

                    <!--
                        Opciones disponibles para usuarios autenticados.
                    -->
                    <a href="/profile">
                        Perfil
                    </a>

                    <!--
                        Cerrar sesión utiliza POST porque modifica
                        el estado de autenticación.
                    -->
                    <form
                        method="POST"
                        action="/logout"
                        class="logout-form"
                    >

                        <!--
                            Token CSRF para proteger el logout.
                        -->
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                Csrf::token(),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                        <button
                            type="submit"
                            class="nav-button"
                        >
                            Cerrar sesión
                        </button>

                    </form>

                <?php else: ?>

                    <!--
                        Opciones disponibles para usuarios no autenticados.
                    -->
                    <a href="/register">
                        Crear cuenta
                    </a>

                    <a href="/login">
                        Iniciar sesión
                    </a>

                <?php endif; ?>

            </nav>

        </div>

    </header>

    <!--
        Contenido específico de cada página.
    -->
    <main class="site-main">

        <div class="container">

            <?= $content ?>

        </div>

    </main>

    <!--
        Pie de página.
    -->
    <footer class="site-footer">

        <div class="container">

            <p>
                © <?= date('Y') ?> Camagru
            </p>

        </div>

    </footer>

</body>

</html>