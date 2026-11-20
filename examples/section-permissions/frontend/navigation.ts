/**
 * Navegación del panel y reglas de visibilidad.
 *
 * Dos filtros independientes, que se combinan:
 *   1. por ROL        -> `hiddenForRoles`: roles a los que se oculta el ítem y se bloquea la ruta;
 *   2. por SECCIONES  -> `section`: identificador que coincide con la lista blanca del backend.
 *
 * Un ítem sin `section` (Dashboard, Configuración) nunca se filtra por sección.
 */

export interface NavItem {
  name: string;
  href: string;
  section?: string;
  hiddenForRoles?: string[];
}

export const navigation: NavItem[] = [
  { name: 'Dashboard', href: '/dashboard' },
  { name: 'Work orders', href: '/orders', section: 'orders' },
  { name: 'Tickets', href: '/tickets', section: 'tickets' },
  { name: 'Support inbox', href: '/support', section: 'support', hiddenForRoles: ['warehouse', 'technician'] },
  { name: 'Tasks', href: '/tasks', section: 'tasks' },
  { name: 'Crews', href: '/crews', section: 'crews' },
  { name: 'Employees', href: '/employees', section: 'employees', hiddenForRoles: ['warehouse', 'planner'] },
  { name: 'Materials', href: '/materials', section: 'materials', hiddenForRoles: ['planner'] },
  { name: 'Checklists', href: '/checklists', section: 'checklists', hiddenForRoles: ['warehouse', 'technician', 'operator'] },
  { name: 'Customers', href: '/customers', section: 'customers' },
  { name: 'Reports', href: '/reports', section: 'reports', hiddenForRoles: ['warehouse', 'planner'] },
  { name: 'Settings', href: '/settings' },
];

/** Busca el ítem al que pertenece una ruta (por prefijo). */
function findItem(pathname: string): NavItem | undefined {
  return navigation.find((item) => pathname.startsWith(item.href));
}

/** ¿La ruta está oculta para este rol? Un ítem sin `hiddenForRoles` nunca bloquea. */
export function isRouteHiddenForRole(pathname: string, role: string | undefined): boolean {
  const item = findItem(pathname);
  if (!item) return false;
  return !!item.hiddenForRoles?.includes(role ?? '');
}

/**
 * ¿La ruta es visible según las secciones habilitadas del empleado?
 *
 *  - admin siempre pasa (configura a los demás; una lista mal armada no puede dejarlo afuera);
 *  - lista vacía o ausente = "todavía no configurado": no filtra;
 *  - ítems sin `section` siempre son visibles.
 */
export function isRouteVisibleForSections(
  sections: string[] | undefined,
  pathname: string,
  role?: string,
): boolean {
  if (role === 'admin') return true;
  if (!sections || sections.length === 0) return true;
  const item = findItem(pathname);
  if (!item || !item.section) return true;
  return sections.includes(item.section);
}
