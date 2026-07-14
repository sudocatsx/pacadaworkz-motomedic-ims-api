<?php

namespace App\Exceptions\POS\Cart;

use Exception;

class EmptyCartException extends Exception
{
    protected $message = 'Cart is empty';

    protected $code = 409;
}
