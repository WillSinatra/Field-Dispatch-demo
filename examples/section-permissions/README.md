# Permisos por sección

Cada empleado tiene una lista de **secciones** del panel que puede usar (`orders`, `tickets`, `crews`, …), configurable por un administrador.

## Qué es real y qué es propuesta

| Pieza | Estado |
|-------|--------|
| Lista blanca de secciones validada al guardar (`SectionGuard::validate`) | Refleja el backend actual |
| Filtro de menú y de rutas por **rol** y por **secciones** en el panel (`frontend/`) | Refleja el panel actual |
| `SectionGuard::require()` en el servidor, con tests de 403 | **Propuesta** de refuerzo: hoy la API autoriza solo por rol |

La razón del refuerzo: si las secciones solo se aplican en la interfaz, alguien con acceso a la API puede llamar a un endpoint de una sección deshabilitada. El guard cierra esa brecha.

## Reglas

- El **administrador siempre pasa**: configura a los demás y una lista mal armada no puede dejarlo afuera.
- Lista vacía = "todavía no configurado". En modo compatible no restringe (igual que la interfaz); en modo estricto (`strict: true`) deniega todo lo que no esté listado.
- Un ítem de navegación sin `section` (Dashboard, Configuración) nunca se filtra por sección.

## Cómo correrlo

```bash
# Backend (PHP 8.1+, sin dependencias)
php backend/tests/section_guard_test.php

# Frontend (Node 18+)
cd frontend
npm install
npm test            # 10 tests de las reglas de visibilidad
npm run typecheck   # incluye RouteGuard.tsx
```

## Cómo se usaría en un controlador

```php
public function index(): void
{
    $actor = Auth::requireUser();                       // ya existe: autenticación
    Auth::requireRole($actor, ['admin', 'planner']);    // ya existe: rol
    SectionGuard::require($actor, 'reports');           // propuesta: sección → 403 si no corresponde
    // ...
}
```

## Archivos

| Archivo | Para qué |
|---------|----------|
| `backend/SectionGuard.php` | Validación de la lista blanca y guard con 403 |
| `backend/tests/section_guard_test.php` | Tests del guard (403, admin, lista vacía en ambos modos, 400) |
| `frontend/navigation.ts` | Navegación y reglas de visibilidad por rol y por sección |
| `frontend/RouteGuard.tsx` | Bloqueo de acceso directo por URL |
| `frontend/navigation.test.ts` | Tests de las reglas (vitest) |
