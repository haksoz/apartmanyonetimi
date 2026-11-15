<?php

namespace App\Support\Paytr;

use RuntimeException;

class CallbackRejected extends RuntimeException
{
    public function __construct(public readonly int $httpStatus, string $message)
    {
        parent::__construct($message);
    }
}
