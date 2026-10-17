<?php

namespace App\Exceptions;

use RuntimeException;

class KpoInvoiceConflictException extends RuntimeException
{
    /** @param array<string, mixed> $conflict */
    public function __construct(public readonly array $conflict)
    {
        parent::__construct('Izmena fakture zahteva potvrdu KPO promena.');
    }
}
