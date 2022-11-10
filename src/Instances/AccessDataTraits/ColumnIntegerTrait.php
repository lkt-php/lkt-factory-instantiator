<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\ValidateData\DataValidator;

/**
 * Trait ColumnIntegerTrait
 * @package Lkt\Factory\Instantiator\Instances\AccessDataTraits
 */
trait ColumnIntegerTrait
{
    /**
     * @param string $field
     * @return string
     */
    protected function _getIntegerVal(string $field) :int
    {
        if (isset($this->UPDATED[$field])) {
            return $this->UPDATED[$field];
        }
        return (int)$this->DATA[$field];
    }

    /**
     * @param string $field
     * @return bool
     */
    protected function _hasIntegerVal(string $field) :bool
    {
        $checkField = 'has'.ucfirst($field);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    /**
     * @param string $field
     * @param int|null $value
     */
    protected function _setIntegerVal(string $field, int $value = null)
    {
        $checkField = 'has'.ucfirst($field);
        DataValidator::getInstance($this->TYPE, [
            $field => $value,
        ]);

        $this->UPDATED = $this->UPDATED + DataValidator::getResult();
    }
}