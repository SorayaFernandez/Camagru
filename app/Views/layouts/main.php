<?php

declare(strict_types=1);

// Valores predeterminados para las páginas que usan el layout.
$title = $title ?? 'Camagru';
$content = $content ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <!-- Permite adaptar la página a pantallas móviles. -->
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <!-- Evita que las páginas aparezcan sin título. -->
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- Hoja de estilos compartida por toda la aplicación. -->
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

    <!-- Cabecera común de todas las páginas. -->
    <header class="site-header">
        <div class="container header-inner">

            <!-- Marca de Camagru. -->
            <a class="brand" href="/" aria-label="Camagru home">
                <span class="brand-icon" aria-hidden="true">✧</span>
                <span class="brand-name">CAMAGRU</span>
            </a>

            <!-- Navegación principal. -->
            <nav class="main-nav" aria-label="Main navigation">
                <a class="nav-link" href="/">Home</a>
                <a class="button button-primary" href="/register">
                    Get started
                </a>
            </nav>

        </div>
    </header>

    <!-- Contenido específico de cada página. -->
    <main>
        <div class="container">
            <?= $content ?>
        </div>
    </main>

    <!-- Pie de página común. -->
    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Camagru. Create. Remix. Share.</p>
        </div>
    </footer>

</body>
</html>