<?php

namespace App\Exceptions;

use RuntimeException;

class DatabaseBackupException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus = 500,
    ) {
        parent::__construct($message);
    }
}
