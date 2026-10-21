<?php

declare(strict_types=1);

// Normalizamos los datos para evitar errores si la galería está vacía.
$images = isset($images) && is_array($images) ? $images : [];

// Identificamos al usuario actual y comprobamos que exista un token CSRF.
$currentUserId = isset($currentUserId) && is_int($currentUserId)
    ? $currentUserId
    : null;

$csrfToken = isset($csrfToken) && is_string($csrfToken)
    ? $csrfToken
    : '';

$isLoggedIn = class_exists('Auth')
    && Auth::check()
    && $currentUserId !== null;
?>

<section class="hero">
    <div class="hero-content">
        <h1>Captura tu mundo</h1>

        <p class="hero-description">
            Crea composiciones únicas con tu cámara o tus propias fotografías
            y compártelas con la comunidad Camagru.
        </p>

        <?php if ($isLoggedIn): ?>
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
                Sé de las primeras personas en compartir una imagen.
            </p>

            <?php if ($isLoggedIn): ?>
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
                // Solo aceptamos nombres de archivo generados por el servidor.
                $filename = $image['generated_filename'] ?? '';

                if (
                    !is_string($filename)
                    || !preg_match('/\A[a-f0-9]{64}\.jpg\z/', $filename)
                ) {
                    continue;
                }

                // Escapamos los datos del autor antes de mostrarlos.
                $username = isset($image['username'])
                    && is_string($image['username'])
                    ? $image['username']
                    : 'Usuario';

                $createdAt = isset($image['created_at'])
                    && is_string($image['created_at'])
                    ? $image['created_at']
                    : '';

                // Normalizamos los datos de interacción.
                $imageId = (int) ($image['id'] ?? 0);
                $likesCount = max(0, (int) ($image['likes_count'] ?? 0));
                $userLiked = (bool) ($image['user_liked'] ?? false);

                $comments = isset($image['comments'])
                    && is_array($image['comments'])
                    ? $image['comments']
                    : [];
                ?>

                <article
                    class="gallery-card"
                    id="image-<?= $imageId ?>"
                >
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

                    <section
                        class="gallery-interactions"
                        aria-label="Interacciones de la imagen"
                    >
                        <div class="gallery-like-row">
                            <span
                                class="gallery-like-count"
                                aria-live="polite"
                            >
                                ♥ <?= $likesCount ?>
                                <?= $likesCount === 1 ? 'Me gusta' : 'Me gusta' ?>
                            </span>

                            <?php if ($isLoggedIn): ?>
                                <form
                                    class="like-form"
                                    action="/image-like"
                                    method="post"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="image_id"
                                        value="<?= $imageId ?>"
                                    >

                                    <button
                                        class="btn-like<?= $userLiked ? ' is-liked' : '' ?>"
                                        type="submit"
                                        aria-pressed="<?= $userLiked ? 'true' : 'false' ?>"
                                    >
                                        <?= $userLiked ? '♥ Quitar Me gusta' : '♡ Me gusta' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <a class="interaction-login-link" href="/login">
                                    Inicia sesión para reaccionar
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="gallery-comments">
                            <h3>
                                Comentarios
                                <span class="comment-count">
                                    (<?= count($comments) ?>)
                                </span>
                            </h3>

                            <?php if ($comments === []): ?>
                                <p class="comments-empty">
                                    Todavía no hay comentarios.
                                </p>
                            <?php else: ?>
                                <ul class="comment-list">
                                    <?php foreach ($comments as $comment): ?>
                                        <?php
                                        $commentUsername = isset($comment['username'])
                                            && is_string($comment['username'])
                                            ? $comment['username']
                                            : 'Usuario';

                                        $commentContent = isset($comment['content'])
                                            && is_string($comment['content'])
                                            ? $comment['content']
                                            : '';

                                        $commentDate = isset($comment['created_at'])
                                            && is_string($comment['created_at'])
                                            ? $comment['created_at']
                                            : '';
                                        ?>

                                        <li class="comment-item">
                                            <div class="comment-header">
                                                <strong>
                                                    @<?= htmlspecialchars($commentUsername, ENT_QUOTES, 'UTF-8') ?>
                                                </strong>

                                                <?php if ($commentDate !== ''): ?>
                                                    <time
                                                        class="comment-date"
                                                        datetime="<?= htmlspecialchars($commentDate, ENT_QUOTES, 'UTF-8') ?>"
                                                    >
                                                        <?= htmlspecialchars($commentDate, ENT_QUOTES, 'UTF-8') ?>
                                                    </time>
                                                <?php endif; ?>
                                            </div>

                                            <p class="comment-content"><?=
                                                nl2br(
                                                    htmlspecialchars(
                                                        $commentContent,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    )
                                                )
                                            ?></p>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <?php if ($isLoggedIn): ?>
                                <form
                                    class="comment-form"
                                    action="/comment"
                                    method="post"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="image_id"
                                        value="<?= $imageId ?>"
                                    >

                                    <label
                                        for="comment-<?= $imageId ?>"
                                        class="comment-label"
                                    >
                                        Añadir un comentario
                                    </label>

                                    <textarea
                                        id="comment-<?= $imageId ?>"
                                        name="content"
                                        maxlength="1000"
                                        rows="2"
                                        required
                                        placeholder="Escribe tu comentario..."
                                    ></textarea>

                                    <button
                                        class="btn btn-secondary"
                                        type="submit"
                                    >
                                        Publicar comentario
                                    </button>
                                </form>
                            <?php else: ?>
                                <p class="comment-login-prompt">
                                    <a href="/login">Inicia sesión</a>
                                    para dejar un comentario.
                                </p>
                            <?php endif; ?>
                        </div>
                    </section>
                </article>
            
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php
    // Normalizamos los datos de paginación recibidos del controlador.
    $currentPage = isset($currentPage) && is_int($currentPage)
        ? max(1, $currentPage)
        : 1;

    $totalPages = isset($totalPages) && is_int($totalPages)
        ? max(1, $totalPages)
        : 1;

    $totalImages = isset($totalImages) && is_int($totalImages)
        ? max(0, $totalImages)
        : 0;

    $imagesPerPage = isset($imagesPerPage) && is_int($imagesPerPage)
        ? max(1, $imagesPerPage)
        : 10;

    // Calculamos el intervalo de imágenes de la página actual.
    $firstImage = $totalImages === 0
        ? 0
        : (($currentPage - 1) * $imagesPerPage) + 1;

    $lastImage = min(
        $currentPage * $imagesPerPage,
        $totalImages
    );

    // Mostramos un máximo de cinco números alrededor de la página actual.
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);

    // Completamos la ventana cuando estamos cerca del principio o del final.
    if (($endPage - $startPage) < 4) {
        $startPage = max(1, $endPage - 4);
        $endPage = min($totalPages, $startPage + 4);
    }
    ?>

    <?php if ($totalImages > 0): ?>
        <div class="gallery-pagination-summary">
            <p>
                Mostrando
                <strong><?= $firstImage ?>–<?= $lastImage ?></strong>
                de
                <strong><?= $totalImages ?></strong>
                <?= $totalImages === 1 ? 'creación' : 'creaciones' ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="gallery-pagination" aria-label="Paginación de la galería">
            <?php if ($currentPage > 1): ?>
                <a
                    class="pagination-arrow"
                    href="/?page=<?= $currentPage - 1 ?>#gallery-title"
                    rel="prev"
                    aria-label="Ir a la página anterior"
                >
                    <span aria-hidden="true">←</span>
                    <span class="pagination-arrow-label">Anterior</span>
                </a>
            <?php else: ?>
                <span class="pagination-arrow is-disabled" aria-disabled="true">
                    <span aria-hidden="true">←</span>
                    <span class="pagination-arrow-label">Anterior</span>
                </span>
            <?php endif; ?>

            <div class="pagination-pages">
                <?php if ($startPage > 1): ?>
                    <a class="pagination-page" href="/?page=1#gallery-title">1</a>

                    <?php if ($startPage > 2): ?>
                        <span class="pagination-ellipsis" aria-hidden="true">…</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
                    <?php if ($pageNumber === $currentPage): ?>
                        <span
                            class="pagination-page is-current"
                            aria-current="page"
                            aria-label="Página <?= $pageNumber ?>, actual"
                        >
                            <?= $pageNumber ?>
                        </span>
                    <?php else: ?>
                        <a
                            class="pagination-page"
                            href="/?page=<?= $pageNumber ?>#gallery-title"
                            aria-label="Ir a la página <?= $pageNumber ?>"
                        >
                            <?= $pageNumber ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <span class="pagination-ellipsis" aria-hidden="true">…</span>
                    <?php endif; ?>

                    <a
                        class="pagination-page"
                        href="/?page=<?= $totalPages ?>#gallery-title"
                    >
                        <?= $totalPages ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($currentPage < $totalPages): ?>
                <a
                    class="pagination-arrow"
                    href="/?page=<?= $currentPage + 1 ?>#gallery-title"
                    rel="next"
                    aria-label="Ir a la página siguiente"
                >
                    <span class="pagination-arrow-label">Siguiente</span>
                    <span aria-hidden="true">→</span>
                </a>
            <?php else: ?>
                <span class="pagination-arrow is-disabled" aria-disabled="true">
                    <span class="pagination-arrow-label">Siguiente</span>
                    <span aria-hidden="true">→</span>
                </span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
