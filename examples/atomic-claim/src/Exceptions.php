<?php

declare(strict_types=1);

namespace FieldDemo;

/** Base: cada excepción sabe a qué código HTTP se traduce en la capa de API. */
abstract class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus,
        private readonly string $errorCode,
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}

final class OrderNotFoundException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Orden no encontrada', 404, 'NOT_FOUND');
    }
}

/** La orden ya no está pendiente: otra cuadrilla ganó la carrera. */
final class AlreadyClaimedException extends ApiException
{
    public function __construct()
    {
        parent::__construct('Esta orden ya fue tomada por otra cuadrilla', 409, 'CONFLICT');
    }
}
