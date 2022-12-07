<?php

namespace Lkt\Factory\Instantiator\Exceptions;

use Exception;

class InvalidIntegerChoiceValueException extends Exception
{
    public function __construct($message = '', $val = 0, Exception $old = null)
    {
        parent::__construct($message, $val, $old);
    }

    public static function getInstance(int $value, string $field, string $schema)
    {
        $message = "InvalidIntegerChoiceValueException: Invalid value '{$value}' for field '{$field}' at schema '{$schema}'";
        return new static($message);
    }
}