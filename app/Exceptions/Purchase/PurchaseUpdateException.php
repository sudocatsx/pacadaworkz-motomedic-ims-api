<?php

namespace App\Exceptions\Purchase;

use Exception;

class PurchaseUpdateException extends Exception
{
    protected $message = 'Update Error';
    protected $code = 400;

    public function __construct(string $message = "", int $code = 400)
    {
        parent::__construct($message ?: $this->message, $code ?: $this->code);
    }
}
