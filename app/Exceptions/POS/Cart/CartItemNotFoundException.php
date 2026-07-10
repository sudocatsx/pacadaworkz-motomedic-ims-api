<?php

namespace App\Exceptions\POS\Cart;

use Exception;

class CartItemNotFoundException extends Exception
{
    protected $message = 'Cart item not found';

    protected $code = 404;
}
