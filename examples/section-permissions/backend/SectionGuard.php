<?php

declare(strict_types=1);

namespace FieldDemo;

final class ValidationException extends \RuntimeException
{
    public function httpStatus(): int
    {
        return 400;
    }
}

final class ForbiddenException extends \RuntimeException
{
    public function httpStatus(): int
    {
        return 403;
    }
}

/**
 * Permisos por sección, configurables por empleado.
 *
 * Modelo (real en el proyecto): cada empleado tiene una lista JSON `sections`
 * con las secciones del panel que puede usar. Se valida contra una lista blanca
 * al guardarla.
 *
 * Qué es REAL y qué es PROPUESTA en esta demo:
 *   - validate(): refleja lo que hace el backend hoy (lista blanca al guardar).
 *   - require() / allows(): refuerzo del lado servidor, PROPUESTO. Hoy el panel
 *     aplica las secciones en la interfaz (ver ../frontend) y la API controla por
 *     rol; este guard cierra la brecha de que alguien llame a la API directamente.
 */
final class SectionGuard
{
    public const VALID_SECTIONS = [
        'orders', 'tickets', 'support', 'tasks', 'crews',
        'employees', 'materials', 'checklists', 'customers', 'reports',
    ];

    /**
     * Valida la lista antes de guardarla. Lanza ValidationException (HTTP 400).
     *
     * @param mixed $sections
     */
    public static function validate(mixed $sections): void
    {
        if (!is_array($sections)) {
            throw new ValidationException('sections debe ser un array');
        }
        foreach ($sections as $section) {
            if (!in_array($section, self::VALID_SECTIONS, true)) {
                throw new ValidationException('Sección inválida: ' . (is_scalar($section) ? $section : gettype($section)));
            }
        }
    }

    /**
     * ¿Puede el actor usar esta sección?
     *
     * Reglas (las mismas que aplica la interfaz):
     *   - admin siempre pasa: es quien configura a los demás y no debe poder
     *     dejarse afuera por una lista mal armada;
     *   - lista vacía = "todavía no configurado": en modo compatible no restringe;
     *     en modo estricto ($strict = true) deniega todo lo que no esté listado;
     *   - en cualquier otro caso, la sección debe estar en la lista.
     *
     * @param array{role?: string, sections?: array<int,string>} $actor
     */
    public static function allows(array $actor, string $section, bool $strict = false): bool
    {
        if (($actor['role'] ?? '') === 'admin') {
            return true;
        }
        $sections = $actor['sections'] ?? [];
        if ($sections === []) {
            return !$strict;
        }
        return in_array($section, $sections, true);
    }

    /**
     * Igual que allows(), pero lanza ForbiddenException (HTTP 403) si no corresponde.
     * Se invoca al inicio de cada acción de un controlador, junto al control de rol.
     *
     * @param array{role?: string, sections?: array<int,string>} $actor
     */
    public static function require(array $actor, string $section, bool $strict = false): void
    {
        if (!self::allows($actor, $section, $strict)) {
            throw new ForbiddenException("No tiene acceso a la sección '{$section}'");
        }
    }
}
