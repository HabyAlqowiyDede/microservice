<?php

namespace App\Exceptions;

use Exception;

class EmptyCartException extends BusinessRuleException
{
    public function __construct(public readonly int $userId)
    {
        parent::__construct("Keranjang user {$userId} kosong, tidak ada yang dapat dipesan"); //409
    }
}
