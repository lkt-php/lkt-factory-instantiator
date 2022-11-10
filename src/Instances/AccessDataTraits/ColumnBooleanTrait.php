<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Schemas\Schema;
use Lkt\Factory\ValidateData\DataValidator;

/**
 * Trait ColumnStringTrait
 * @package Lkt\Factory\Instantiator\Instances\AccessDataTraits
 */
trait ColumnBooleanTrait
{
    /**
     * @param string $field
     * @return bool
     */
    protected function _getBooleanVal(string $field) :bool
    {
        if (isset($this->UPDATED[$field])) {
            return $this->UPDATED[$field];
        }
        return $this->DATA[$field] === true;
    }

    /**
     * @param string $field
     * @param bool $value
     */
    protected function _setBooleanVal(string $field, bool $value = false)
    {
        $checkField = 'has'.ucfirst($field);
        DataValidator::getInstance($this->TYPE, [
            $field => $value,
        ]);
//        $schema = Schema::get($this->TYPE);
        //@todo: auto validate data

        $this->UPDATED = $this->UPDATED + DataValidator::getResult();
    }
}