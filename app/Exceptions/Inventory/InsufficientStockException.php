<?php

namespace App\Exceptions\Inventory;

use Exception;

class InsufficientStockException extends Exception
{
    protected $message = 'Insufficient stock for product';

    protected $code = 400;
}
