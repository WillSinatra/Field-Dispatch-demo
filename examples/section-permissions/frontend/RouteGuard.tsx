import type { ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { isRouteHiddenForRole, isRouteVisibleForSections } from './navigation';

interface RouteGuardProps {
  role: string | undefined;
  sections: string[] | undefined;
  children: ReactNode;
}

/**
 * Bloquea el acceso directo por URL, aunque el ítem no aparezca en el menú.
 * Primero por rol y luego por secciones del empleado; ambos redirigen al dashboard.
 *
 * Importante: esto es control de INTERFAZ. La autorización real de cada
 * acción debe repetirse en el servidor (ver ../backend/SectionGuard.php).
 */
export function RouteGuard({ role, sections, children }: RouteGuardProps) {
  const { pathname } = useLocation();

  if (isRouteHiddenForRole(pathname, role)) {
    return <Navigate to="/dashboard" replace />;
  }
  if (!isRouteVisibleForSections(sections, pathname, role)) {
    return <Navigate to="/dashboard" replace />;
  }
  return <>{children}</>;
}
