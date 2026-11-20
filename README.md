# Field Dispatch · Demo

![tests](https://github.com/WillSinatra/field-dispatch-demo/actions/workflows/tests.yml/badge.svg)

Demo técnica de una plataforma de gestión de servicio técnico para un ISP, basada en mi experiencia construyendo sistemas de este tipo: convierte tickets y pedidos en órdenes de trabajo, las despacha a cuadrillas y sigue cada una hasta el cierre.

Este repositorio **no contiene código de producción**: tiene la arquitectura documentada y dos piezas técnicas reescritas con datos y nombres ficticios (empresa ficticia: *Acme ISP*), cada una con sus tests.

## Qué resuelve

- Los reclamos y pedidos llegan por distintos caminos (tickets, alta rápida, tareas internas) y terminan en una **orden de trabajo** con estado, prioridad y SLA.
- Las órdenes se **ofrecen a cuadrillas** y la primera que la toma se la queda, sin doble asignación.
- Cada empleado ve solo las **secciones** del panel que le corresponden.

## Stack

| Capa | Tecnología |
|------|-----------|
| Panel web | React, TypeScript, Vite, TanStack Query, Zustand, Tailwind |
| API | PHP con MVC propio (front controller, controladores, PDO) |
| Base de datos | PostgreSQL |
| Despliegue | Apache |
| App de técnicos | React Native / Expo (proyecto aparte) |

## Qué hay en este repositorio

| Carpeta | Contenido |
|---------|-----------|
| [`docs/architecture.md`](docs/architecture.md) | Arquitectura con diagramas: componentes, ticket → orden, estados, despacho, seguridad, modelo de datos |
| [`docs/decisions.md`](docs/decisions.md) | Cinco decisiones técnicas con contexto, alternativas y consecuencias |
| [`examples/atomic-claim`](examples/atomic-claim) | Claim atómico de órdenes en PHP + PostgreSQL, con **test de concurrencia con procesos reales** |
| [`examples/section-permissions`](examples/section-permissions) | Permisos por sección: modelo real en el panel + guard de servidor propuesto, con tests |

## Las dos piezas

### 1. Claim atómico de órdenes

Varias cuadrillas tocan "Tomar" a la vez; solo una gana y las demás reciben `409`. La exclusión mutua está en una sentencia (`UPDATE … WHERE estado = 'pendiente'`) dentro de una transacción que también acepta la oferta ganadora y cancela las demás.

El test lanza 8 procesos con sesiones de Postgres distintas, 25 rondas, y verifica un único ganador. También incluye una versión *check-then-act* incorrecta para demostrar que el test **sí detecta** la carrera.

### 2. Permisos por sección

Cada empleado tiene una lista de secciones habilitadas, validada contra una lista blanca. El panel filtra menús y rutas por rol y por sección. Hoy la API autoriza solo por rol, así que el ejemplo suma un `SectionGuard` del lado servidor con tests de 403, **marcado como propuesta** y no como código que corre en producción.

## Cómo correr los tests

```bash
# Claim atómico (Docker + PHP 8.1+ con pdo_pgsql)
docker compose up -d
cd examples/atomic-claim && ./run-tests.sh

# Permisos: backend (PHP) y frontend (Node 18+)
php examples/section-permissions/backend/tests/section_guard_test.php
cd examples/section-permissions/frontend && npm install && npm test && npm run typecheck
```

## Sobre la sanitización

- Todos los nombres de empresa, personas, clientes, dominios y datos son ficticios.
- El código fue **reescrito y simplificado**, no copiado; los identificadores se tradujeron a nombres genéricos.
- No hay credenciales, dumps ni configuración real. La única contraseña (`demo`) es de la base local de `docker-compose.yml`.
- Lo que es propuesta y no producción está marcado como tal en cada README.

## Autor

**Santiago Sánchez** · [LinkedIn](https://www.linkedin.com/in/santiagosanchez-programming) · [GitHub](https://github.com/WillSinatra)

## Licencia

[PolyForm Noncommercial 1.0.0](LICENSE). Ver también [NOTICE](NOTICE).

El código se puede **leer, estudiar y ejecutar** para aprender, evaluar o con fines no comerciales. **No se puede usar con fines comerciales** (venderlo, incluirlo en un producto o servicio de pago, ni usarlo dentro de una empresa con fines de lucro) sin permiso escrito del autor. Para consultas de uso comercial, escribime por [LinkedIn](https://www.linkedin.com/in/santiagosanchez-programming).
