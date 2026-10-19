<?php

declare(strict_types=1);

// Normalizamos los datos recibidos para que la vista siempre trabaje
// con un array de imágenes, incluso si no hay resultados.
$images = isset($images) && is_array($images) ? $images : [];
?>

<section class="hero">
    <div class="hero-content">

        <h1>Captura tu mundo<br></h1>

        <p class="hero-description">
            Crea composiciones únicas con tu cámara o tus propias fotografías
            y compartelas con la comunidad Camagru.
        </p>

        <?php if (class_exists('Auth') && Auth::check()): ?>
            <a class="btn btn-primary" href="/create-image">
                Crear una imagen
            </a>
        <?php else: ?>
            <a class="btn btn-primary" href="/register">
                Empieza a crear
            </a>
            <a class="btn btn-secondary" href="/login">
                Iniciar sesión
            </a>
        <?php endif; ?>
    </div>
</section>

<section class="gallery-section" aria-labelledby="gallery-title">
    <div class="gallery-heading">
        <div>
            <p class="eyebrow">CREACIONES DE LA COMUNIDAD</p>
            <h2 id="gallery-title">Galería reciente</h2>
            <p class="gallery-description">
                Descubre las últimas imágenes creadas en Camagru.
            </p>
        </div>
    </div>

    <?php if ($images === []): ?>
        <div class="gallery-empty">
            <h3>La galería está esperando su primera creación</h3>
            <p>
                Sé de las primeras personas en compartir una imagen
            </p>

            <?php if (class_exists('Auth') && Auth::check()): ?>
                <a class="btn btn-primary" href="/create-image">
                    Crear la primera imagen
                </a>
            <?php else: ?>
                <a class="btn btn-primary" href="/register">
                    Crear una cuenta
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="gallery-grid">
            <?php foreach ($images as $image): ?>
                <?php
                // Validamos el nombre antes de construir la URL.
                $filename = $image['generated_filename'] ?? '';

                if (
                    !is_string($filename)
                    || !preg_match('/\A[a-f0-9]{64}\.jpg\z/', $filename)
                ) {
                    continue;
                }

                $username = isset($image['username'])
                    && is_string($image['username'])
                    ? $image['username']
                    : 'Usuario';

                $createdAt = isset($image['created_at'])
                    && is_string($image['created_at'])
                    ? $image['created_at']
                    : '';
                ?>

                <article class="gallery-card">
                    <a
                        class="gallery-image-link"
                        href="/generated-image?filename=<?= rawurlencode($filename) ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Abrir imagen creada por <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <img
                            class="gallery-image"
                            src="/generated-image?filename=<?= rawurlencode($filename) ?>"
                            alt="Imagen creada por <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                            loading="lazy"
                        >
                    </a>

                    <div class="gallery-card-info">
                        <span class="gallery-author">
                            @<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                        </span>

                        <?php if ($createdAt !== ''): ?>
                            <time
                                class="gallery-date"
                                datetime="<?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?>
                            </time>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
