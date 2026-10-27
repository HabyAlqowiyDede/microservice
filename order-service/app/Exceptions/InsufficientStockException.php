<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends BusinessRuleException
{
    public function __construct(
        public readonly string $productName,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(
            "Stok {$productName} tidak mencukupi: diminta {$requested}, tersedia {$available}" //409
        );
    }
}
