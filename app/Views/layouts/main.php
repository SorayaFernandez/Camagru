<?php

declare(strict_types=1);

$title = $title ?? 'Camagru';
$content = $content ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
    <header>
        <h1>Camagru</h1>
    </header>

    <main>
        <?= $content ?>
    </main>

    <footer>
        <p>&copy; Camagru</p>
    </footer>
</body>
</html>