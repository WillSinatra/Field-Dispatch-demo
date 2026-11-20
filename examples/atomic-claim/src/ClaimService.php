<?php

declare(strict_types=1);

namespace FieldDemo;

require_once __DIR__ . '/Exceptions.php';

/**
 * "Tomar" una orden de trabajo: la cuadrilla que llega primero se la queda.
 *
 * La garantía de exclusión mutua está en UNA sola sentencia:
 *
 *     UPDATE work_orders SET status = 'assigned', ... WHERE id = ? AND status = 'pending'
 *
 * Bajo READ COMMITTED (el nivel por defecto de PostgreSQL), si dos transacciones
 * ejecutan ese UPDATE sobre la misma fila, la segunda espera el commit de la
 * primera, vuelve a evaluar el WHERE contra la fila ya modificada y encuentra
 * status = 'assigned': afecta 0 filas. Ese rowCount() === 0 es la señal de
 * "perdiste la carrera" y se traduce en HTTP 409.
 *
 * No hace falta un SELECT ... FOR UPDATE previo: el propio UPDATE toma el lock
 * de la fila, y el predicado del WHERE actúa como compare-and-set.
 */
final class ClaimService
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @return array<string,mixed> la orden ya asignada
     * @throws OrderNotFoundException si la orden no existe
     * @throws AlreadyClaimedException si otra cuadrilla la tomó antes
     */
    public function claim(string $orderId, string $crewId): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE work_orders
                    SET status = 'assigned', crew_id = :crew, assigned_at = now(), updated_at = now()
                  WHERE id = :id AND status = 'pending'"
            );
            $stmt->execute(['crew' => $crewId, 'id' => $orderId]);

            if ($stmt->rowCount() === 0) {
                throw $this->orderExists($orderId)
                    ? new AlreadyClaimedException()
                    : new OrderNotFoundException();
            }

            // La oferta de la cuadrilla ganadora pasa a "aceptada"...
            $this->pdo->prepare(
                "UPDATE dispatch_offers
                    SET status = 'accepted', responded_at = now()
                  WHERE work_order_id = :id AND crew_id = :crew AND status = 'pending'"
            )->execute(['id' => $orderId, 'crew' => $crewId]);

            // ...y las ofertas pendientes de las demás se cancelan, en la MISMA
            // transacción: nadie puede ver una orden asignada con ofertas vivas.
            $this->pdo->prepare(
                "UPDATE dispatch_offers
                    SET status = 'cancelled', responded_at = now()
                  WHERE work_order_id = :id AND crew_id <> :crew AND status = 'pending'"
            )->execute(['id' => $orderId, 'crew' => $crewId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $this->fetchOrder($orderId);
    }

    private function orderExists(string $orderId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM work_orders WHERE id = :id');
        $stmt->execute(['id' => $orderId]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return array<string,mixed> */
    private function fetchOrder(string $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM work_orders WHERE id = :id');
        $stmt->execute(['id' => $orderId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
