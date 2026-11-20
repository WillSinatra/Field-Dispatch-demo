import { describe, expect, it } from 'vitest';
import { isRouteHiddenForRole, isRouteVisibleForSections } from './navigation';

describe('isRouteVisibleForSections', () => {
  const sections = ['orders', 'tickets'];

  it('permite una sección habilitada', () => {
    expect(isRouteVisibleForSections(sections, '/orders', 'planner')).toBe(true);
  });

  it('bloquea una sección que no está en la lista', () => {
    expect(isRouteVisibleForSections(sections, '/reports', 'planner')).toBe(false);
  });

  it('bloquea también subrutas de una sección no habilitada', () => {
    expect(isRouteVisibleForSections(sections, '/reports/monthly', 'planner')).toBe(false);
  });

  it('admin pasa siempre, aunque su lista no incluya la sección', () => {
    expect(isRouteVisibleForSections(['tickets'], '/reports', 'admin')).toBe(true);
  });

  it('lista vacía o ausente = no configurado: no filtra', () => {
    expect(isRouteVisibleForSections([], '/reports', 'planner')).toBe(true);
    expect(isRouteVisibleForSections(undefined, '/reports', 'planner')).toBe(true);
  });

  it('Dashboard y Settings (sin section) siempre son visibles', () => {
    expect(isRouteVisibleForSections(sections, '/dashboard', 'planner')).toBe(true);
    expect(isRouteVisibleForSections(sections, '/settings', 'planner')).toBe(true);
  });
});

describe('isRouteHiddenForRole', () => {
  it('oculta rutas configuradas para ese rol', () => {
    expect(isRouteHiddenForRole('/employees', 'planner')).toBe(true);
  });

  it('no oculta si el rol no está en hiddenForRoles', () => {
    expect(isRouteHiddenForRole('/employees', 'admin')).toBe(false);
  });

  it('una ruta sin hiddenForRoles nunca se bloquea', () => {
    expect(isRouteHiddenForRole('/orders', 'technician')).toBe(false);
  });

  it('una ruta desconocida no se bloquea', () => {
    expect(isRouteHiddenForRole('/nope', 'planner')).toBe(false);
  });
});
