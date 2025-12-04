<?php

namespace Lkt\Factory\Instantiator\Exceptions;

use Exception;

class InvalidCountableFieldException extends Exception
{
    public function __construct($message = '', $val = 0, Exception $old = null)
    {
        parent::__construct($message, $val, $old);
    }

    public static function getInstance(string $trigger, string $schema)
    {
        $message = "InvalidCountableFieldException: Invalid countable field provided at schema '{$schema}' while attempting to use '{$trigger}'";
        return new static($message);
    }
}