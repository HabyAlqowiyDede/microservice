<?php

namespace App\Exceptions;

use Exception;

class ProductNotFoundException extends BusinessRuleException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct("Produk dengan id {$productId} tidak ditemukan di product-service"); //404
    }
}
