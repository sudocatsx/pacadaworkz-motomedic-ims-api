<?php

namespace App\Exceptions\Auth;

use Exception;

class UserNotFoundException extends Exception
{
    protected $message = 'No account found with this email';

    protected $code = 404;

    public function __construct(string $identifier, string $type = 'email')
    {
        if ($type === 'email') {
            $message = "No account found with email: {$identifier}";
        } elseif ($type === 'id') {
            $message = "User with ID {$identifier} not found";
        } else {
            $message = $identifier;
        }

        parent::__construct($message);
    }
}
