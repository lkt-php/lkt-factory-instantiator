<?php

namespace Lkt\Factory\Instantiator\Exceptions;

use Exception;

class UnsetFieldStorePathException extends Exception
{
    public function __construct($message = '', $val = 0, Exception $old = null)
    {
        parent::__construct($message, $val, $old);
    }

    public static function getInstance(string $field, string $schema)
    {
        $message = "UnsetFieldStorePathException: Missing storePath for field '{$field}' at schema '{$schema}'";
        return new static($message);
    }
}