<?php

declare(strict_types=1);

/**
 * Proceso trabajador: intenta tomar una orden en un instante exacto.
 * Lo lanza concurrency_test.php (uno por cuadrilla, todos a la vez).
 *
 * Uso: php worker.php <orderId> <crewId> <startAtMicrotime> <atomic|naive>
 * Salida (stdout): una línea JSON {"crew": "...", "result": "OK|CONFLICT|ERROR"}
 */

require_once __DIR__ . '/../src/ClaimService.php';
require_once __DIR__ . '/../src/NaiveClaimService.php';
require_once __DIR__ . '/db.php';

[, $orderId, $crewId, $startAt, $impl] = $argv;

$pdo = demo_pdo();   // conexión propia: cada proceso = sesión de Postgres distinta
$service = $impl === 'naive' ? new FieldDemo\NaiveClaimService($pdo) : new FieldDemo\ClaimService($pdo);

// Espera activa hasta el instante de largada, para maximizar la contención.
while (microtime(true) < (float) $startAt) {
    // busy wait
}

try {
    $service->claim($orderId, $crewId);
    $result = 'OK';
} catch (FieldDemo\AlreadyClaimedException) {
    $result = 'CONFLICT';
} catch (Throwable $e) {
    $result = 'ERROR: ' . $e->getMessage();
}

echo json_encode(['crew' => $crewId, 'result' => $result]), PHP_EOL;
