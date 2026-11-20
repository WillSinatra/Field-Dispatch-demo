<?php

declare(strict_types=1);

/** Test sin dependencias: php tests/section_guard_test.php  (sale con 1 si algo falla) */

require_once __DIR__ . '/../SectionGuard.php';

use FieldDemo\ForbiddenException;
use FieldDemo\SectionGuard;
use FieldDemo\ValidationException;

$failed = 0;

function check(string $name, bool $condition): void
{
    global $failed;
    echo ($condition ? 'ok   ' : 'FAIL ') . $name . PHP_EOL;
    if (!$condition) {
        $failed++;
    }
}

function status_of(callable $fn): int
{
    try {
        $fn();
        return 200;
    } catch (ForbiddenException | ValidationException $e) {
        return $e->httpStatus();
    }
}

$tecnico = ['role' => 'planner', 'sections' => ['orders', 'tickets']];
$sinConfigurar = ['role' => 'planner', 'sections' => []];
$admin = ['role' => 'admin', 'sections' => ['tickets']]; // lista "mal armada" a propósito

// --- 403 cuando falta el permiso -------------------------------------------
check('sección habilitada -> 200', status_of(fn () => SectionGuard::require($tecnico, 'orders')) === 200);
check('sección NO habilitada -> 403', status_of(fn () => SectionGuard::require($tecnico, 'reports')) === 403);
check('403 también en otra sección fuera de la lista', status_of(fn () => SectionGuard::require($tecnico, 'materials')) === 403);

// --- admin ------------------------------------------------------------------
check('admin pasa aunque su lista no incluya la sección', status_of(fn () => SectionGuard::require($admin, 'reports')) === 200);

// --- lista vacía: compatible vs. estricto ------------------------------------
check('lista vacía + modo compatible -> permitido', status_of(fn () => SectionGuard::require($sinConfigurar, 'reports')) === 200);
check('lista vacía + modo estricto -> 403', status_of(fn () => SectionGuard::require($sinConfigurar, 'reports', strict: true)) === 403);

// --- validación al guardar (400) ---------------------------------------------
check('lista válida -> 200', status_of(fn () => SectionGuard::validate(['orders', 'crews'])) === 200);
check('sección inexistente -> 400', status_of(fn () => SectionGuard::validate(['orders', 'nope'])) === 400);
check('no-array -> 400', status_of(fn () => SectionGuard::validate('orders')) === 400);

echo PHP_EOL . ($failed === 0 ? 'Todos los tests pasaron.' : "{$failed} test(s) fallaron.") . PHP_EOL;
exit($failed === 0 ? 0 : 1);
