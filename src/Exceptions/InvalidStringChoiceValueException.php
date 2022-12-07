<?php

namespace Lkt\Factory\Instantiator\Exceptions;

use Exception;

class InvalidStringChoiceValueException extends Exception
{
    public function __construct($message = '', $val = 0, Exception $old = null)
    {
        parent::__construct($message, $val, $old);
    }

    public static function getInstance(string $value, string $field, string $schema)
    {
        $message = "InvalidStringChoiceValueException: Invalid value '{$value}' for field '{$field}' at schema '{$schema}'";
        return new static($message);
    }
}