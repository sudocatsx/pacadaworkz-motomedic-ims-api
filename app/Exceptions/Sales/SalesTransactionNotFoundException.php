<?php

namespace App\Exceptions\Sales;

use Exception;

class SalesTransactionNotFoundException extends Exception
{
    protected $message = 'Sales transaction not found';

    protected $code = 404;
}
