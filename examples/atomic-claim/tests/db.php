<?php

declare(strict_types=1);

/** Conexión a la base de la demo (coincide con docker-compose.yml). */
function demo_pdo(): PDO
{
    $dsn  = getenv('DEMO_DSN')  ?: 'pgsql:host=127.0.0.1;port=5433;dbname=field_demo';
    $user = getenv('DEMO_USER') ?: 'demo';
    $pass = getenv('DEMO_PASS') ?: 'demo';

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}
