<?php

namespace App\Exceptions\Purchase;

use Exception;
use Throwable;

class PurchaseReceiveException extends Exception
{
    protected $message = 'Receive Error';
    protected $code = 500;

    public function __construct(string $message = "", int $code = 0)
    {
        $this->message = $message;
        $this->code = $code;
        return parent::__construct($this->message, $this->code);
    }
}
