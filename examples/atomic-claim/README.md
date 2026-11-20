# Claim atómico de órdenes

Varias cuadrillas tocan "Tomar" sobre la misma orden casi a la vez. Solo una gana; las demás reciben `409 Conflict`.

## La idea en una sentencia

```sql
UPDATE work_orders SET status = 'assigned', crew_id = :crew, assigned_at = now()
WHERE id = :id AND status = 'pending';
```

Si `rowCount() === 0`, otra cuadrilla llegó antes. En `READ COMMITTED` la segunda transacción espera el commit de la primera, reevalúa el `WHERE` y no encuentra la orden pendiente. No hace falta un `SELECT … FOR UPDATE` previo. Todo ocurre en una transacción que además acepta la oferta ganadora y cancela las demás.

Código: [`src/ClaimService.php`](src/ClaimService.php).

## Cómo correrlo

Requisitos: Docker, PHP 8.1+ con `pdo_pgsql`.

```bash
# desde la raíz del repositorio
docker compose up -d          # PostgreSQL con el esquema y datos ficticios, en el puerto 5433
cd examples/atomic-claim
./run-tests.sh
```

## Qué verifica el test

[`tests/concurrency_test.php`](tests/concurrency_test.php) hace 25 rondas. En cada una lanza 8 procesos PHP, cada uno con su propia sesión de Postgres, que esperan un instante de largada común y luego intentan tomar la misma orden. Comprueba:

1. exactamente una cuadrilla recibe OK y las otras 7 reciben CONFLICT (409);
2. la orden queda `assigned` con la cuadrilla ganadora;
3. hay una oferta `accepted` (la ganadora) y las demás quedan `cancelled`.

## Prueba de que el test sirve

[`src/NaiveClaimService.php`](src/NaiveClaimService.php) es la versión incorrecta a propósito: lee el estado, decide en PHP y después escribe sin condición (*check-then-act*). `php tests/concurrency_test.php --naive` debe **detectar la carrera** (varios ganadores) y salir con código 0 solo si la detecta. Si el test no pudiera fallar con código roto, no probaría nada.

Salida esperada:

```
== 1) Implementación atómica (debe pasar) ==
OK: en las 25 rondas ganó exactamente una cuadrilla y el resto recibió 409.

== 2) Implementación ingenua (el test debe detectar la carrera) ==
Rondas con carrera detectada: 25/25
OK: el test detecta la carrera en la versión ingenua (comportamiento esperado).
```

## Archivos

| Archivo | Para qué |
|---------|----------|
| `schema.sql` / `seed.sql` | Esquema reducido (órdenes, cuadrillas, ofertas) y datos ficticios |
| `src/ClaimService.php` | Implementación atómica |
| `src/NaiveClaimService.php` | Versión incorrecta, solo para el contraste |
| `tests/concurrency_test.php`, `tests/worker.php` | Test de concurrencia con procesos reales |
