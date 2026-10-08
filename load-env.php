<?php

$environmentFile = __DIR__ . '/.env';
if (!is_file($environmentFile) || !is_readable($environmentFile)) {
    return;
}

$lines = file($environmentFile, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    throw new RuntimeException('Could not read the project .env file.');
}

foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    if (preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $matches) !== 1) {
        throw new RuntimeException('Invalid entry in the project .env file.');
    }

    $name = $matches[1];
    $value = trim($matches[2]);
    if (
        strlen($value) >= 2
        && (($value[0] === '"' && str_ends_with($value, '"'))
            || ($value[0] === "'" && str_ends_with($value, "'")))
    ) {
        $value = substr($value, 1, -1);
    }

    if (getenv($name) === false) {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
