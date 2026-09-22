<?php

declare(strict_types=1);

function loadEnv(string $file): void
{
    if (!is_readable($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $separator = strpos($line, '=');

        if ($separator === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));

        if ($name !== '' && getenv($name) === false) {
            putenv($name . '=' . $value);
        }
    }
}

loadEnv(dirname(__DIR__) . '/.env');

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    return $value;
}