<?php

namespace App\Exceptions;

use Exception;
use RuntimeException;
use Throwable;

/**
 * Service lain (cart/product) tidak dapat dihubungi atau mengembalikan error.
 * Controller menerjemahkannya menjadi 503.
 */
class ExternalServiceException extends RuntimeException
{
    public function __construct(
        public readonly string $serviceName,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous); //503
    }
}
