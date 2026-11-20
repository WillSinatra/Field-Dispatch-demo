<?php

declare(strict_types=1);

namespace FieldDemo;

require_once __DIR__ . '/Exceptions.php';

/**
 * VERSIÓN INCORRECTA, a propósito. Existe solo para demostrar que el test de
 * concurrencia detecta la carrera: lee el estado, decide en PHP y recién
 * después escribe, sin condición en el UPDATE (check-then-act).
 *
 * El usleep() agranda la ventana entre la lectura y la escritura para que el
 * fallo sea reproducible; en producción la ventana sería de microsegundos, pero
 * la carrera existiría igual.
 */
final class NaiveClaimService
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function claim(string $orderId, string $crewId): array
    {
        $stmt = $this->pdo->prepare('SELECT status FROM work_orders WHERE id = :id');
        $stmt->execute(['id' => $orderId]);
        $status = $stmt->fetchColumn();

        if ($status === false) {
            throw new OrderNotFoundException();
        }
        if ($status !== 'pending') {
            throw new AlreadyClaimedException();
        }

        usleep(20_000); // ventana de la carrera

        // Sin "AND status = 'pending'": el último que escribe pisa al anterior.
        $this->pdo->prepare(
            "UPDATE work_orders
                SET status = 'assigned', crew_id = :crew, assigned_at = now(), updated_at = now()
              WHERE id = :id"
        )->execute(['crew' => $crewId, 'id' => $orderId]);

        $stmt = $this->pdo->prepare('SELECT * FROM work_orders WHERE id = :id');
        $stmt->execute(['id' => $orderId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
