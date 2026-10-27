<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Induk semua pelanggaran aturan bisnis. Controller menerjemahkannya menjadi 409.
 */
abstract class BusinessRuleException extends RuntimeException
{
    //
}
