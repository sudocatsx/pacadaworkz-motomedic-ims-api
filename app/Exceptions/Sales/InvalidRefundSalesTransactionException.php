<?php

namespace App\Exceptions\Sales;

use Exception;

class InvalidRefundSalesTransactionException extends Exception
{
    protected $message = 'Invalid Refund of Sales Transaction';

    protected $code = 400;
}
