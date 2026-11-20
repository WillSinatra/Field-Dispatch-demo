<?php

declare(strict_types=1);

/**
 * Test de concurrencia del claim.
 *
 * Por cada ronda: crea una orden pendiente con ofertas para N cuadrillas,
 * lanza N procesos PHP (N sesiones de Postgres distintas) que intentan tomarla
 * al mismo instante, y verifica los invariantes:
 *
 *   1. exactamente UNA cuadrilla recibe OK y las otras N-1 reciben CONFLICT (409);
 *   2. la orden queda 'assigned' con la cuadrilla ganadora;
 *   3. hay exactamente una oferta 'accepted' (la de la ganadora) y el resto 'cancelled'.
 *
 * Uso:
 *   php tests/concurrency_test.php            # implementación atómica  -> debe PASAR
 *   php tests/concurrency_test.php --naive    # check-then-act ingenuo  -> debe FALLAR
 *
 * Con --naive el script sale con código 0 solo si detectó la carrera (es decir,
 * si el test es capaz de atrapar el bug). Con la versión atómica sale con 0
 * solo si ningún invariante se rompió en ninguna ronda.
 */

require_once __DIR__ . '/db.php';

const CREWS_PER_ROUND = 8;
const ROUNDS          = 25;

$naive = in_array('--naive', $argv, true);
$impl  = $naive ? 'naive' : 'atomic';
$pdo   = demo_pdo();

$failures = [];

for ($round = 1; $round <= ROUNDS; $round++) {
    // --- preparación -----------------------------------------------------
    $crewIds = [];
    for ($i = 0; $i < CREWS_PER_ROUND; $i++) {
        $crewIds[] = $pdo->query("INSERT INTO crews (name) VALUES ('crew-r{$round}-{$i}') RETURNING id")
                         ->fetchColumn();
    }
    $orderId = $pdo->query("INSERT INTO work_orders (title) VALUES ('race-round-{$round}') RETURNING id")
                   ->fetchColumn();
    $offer = $pdo->prepare(
        "INSERT INTO dispatch_offers (work_order_id, crew_id, mode) VALUES (:o, :c, 'open')"
    );
    foreach ($crewIds as $crewId) {
        $offer->execute(['o' => $orderId, 'c' => $crewId]);
    }

    // --- largada simultánea ---------------------------------------------
    $startAt   = microtime(true) + 0.6; // margen para que todos los procesos arranquen
    $processes = [];
    foreach ($crewIds as $crewId) {
        $cmd = [PHP_BINARY, __DIR__ . '/worker.php', $orderId, $crewId, (string) $startAt, $impl];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $processes[] = [$proc, $pipes];
    }

    $results = [];
    foreach ($processes as [$proc, $pipes]) {
        $out = trim(stream_get_contents($pipes[1]));
        $err = trim(stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $results[] = $out !== '' ? json_decode($out, true) : ['crew' => '?', 'result' => 'ERROR: ' . $err];
    }

    // --- verificación de invariantes ------------------------------------
    $winners   = array_values(array_filter($results, fn ($r) => $r['result'] === 'OK'));
    $conflicts = array_filter($results, fn ($r) => $r['result'] === 'CONFLICT');
    $errors    = array_filter($results, fn ($r) => str_starts_with($r['result'], 'ERROR'));

    $order = $pdo->query("SELECT status, crew_id FROM work_orders WHERE id = '{$orderId}'")->fetch();
    $accepted  = (int) $pdo->query(
        "SELECT count(*) FROM dispatch_offers WHERE work_order_id = '{$orderId}' AND status = 'accepted'"
    )->fetchColumn();
    $cancelled = (int) $pdo->query(
        "SELECT count(*) FROM dispatch_offers WHERE work_order_id = '{$orderId}' AND status = 'cancelled'"
    )->fetchColumn();

    $problems = [];
    if (count($errors) > 0) {
        $problems[] = 'errores inesperados: ' . json_encode(array_values($errors));
    }
    if (count($winners) !== 1) {
        $problems[] = count($winners) . ' cuadrillas recibieron OK (se esperaba 1)';
    }
    if (count($winners) === 1 && count($conflicts) !== CREWS_PER_ROUND - 1) {
        $problems[] = count($conflicts) . ' conflictos (se esperaban ' . (CREWS_PER_ROUND - 1) . ')';
    }
    if ($order['status'] !== 'assigned') {
        $problems[] = "estado final '{$order['status']}' (se esperaba 'assigned')";
    }
    if (count($winners) === 1 && $order['crew_id'] !== $winners[0]['crew']) {
        $problems[] = 'la cuadrilla guardada en la orden no es la ganadora';
    }
    if (!$naive && ($accepted !== 1 || $cancelled !== CREWS_PER_ROUND - 1)) {
        $problems[] = "ofertas: {$accepted} aceptadas / {$cancelled} canceladas";
    }

    if ($problems) {
        $failures[$round] = $problems;
    }
}

// --- resultado ---------------------------------------------------------------
echo "Implementación: {$impl} | rondas: " . ROUNDS . ' | procesos concurrentes por ronda: ' . CREWS_PER_ROUND . PHP_EOL;

if ($naive) {
    $n = count($failures);
    echo "Rondas con carrera detectada: {$n}/" . ROUNDS . PHP_EOL;
    if ($n > 0) {
        echo "OK: el test detecta la carrera en la versión ingenua (comportamiento esperado)." . PHP_EOL;
        exit(0);
    }
    echo "FALLO: la versión ingenua no mostró carrera; el test no sería confiable." . PHP_EOL;
    exit(1);
}

if ($failures === []) {
    echo "OK: en las " . ROUNDS . " rondas ganó exactamente una cuadrilla y el resto recibió 409." . PHP_EOL;
    exit(0);
}

foreach ($failures as $round => $problems) {
    echo "Ronda {$round}: " . implode('; ', $problems) . PHP_EOL;
}
exit(1);
