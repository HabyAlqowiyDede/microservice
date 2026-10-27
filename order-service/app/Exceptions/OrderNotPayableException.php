<?php

namespace App\Exceptions;

use Exception;

class OrderNotPayableException extends BusinessRuleException
{
    public function __construct(public readonly string $currentStatus)
    {
        parent::__construct(
            "Pesanan berstatus {$currentStatus}, hanya pesanan PENDING yang dapat dibayar" //409
        );
    }
}
