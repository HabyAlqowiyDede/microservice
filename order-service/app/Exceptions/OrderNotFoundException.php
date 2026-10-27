<?php

namespace App\Exceptions;

use Exception;
use RuntimeException;

class OrderNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $orderId)
    {
        parent::__construct("Pesanan dengan id {$orderId} tidak ditemukan"); //404
    }
}
