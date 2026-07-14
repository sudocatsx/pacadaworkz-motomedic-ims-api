<?php

namespace App\Exceptions\Auth;

use Exception;

class InvalidGoogleTokenException extends Exception
{
    protected $message = 'Invalid Google Token';

    protected $code = 401;
}
