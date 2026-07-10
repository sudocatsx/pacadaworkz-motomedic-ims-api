<?php

namespace App\Exceptions\POS;

use Exception;

class InsufficientPaymentException extends Exception
{
    protected $message = 'Insufficient payment';

    protected $code = 400;
}
