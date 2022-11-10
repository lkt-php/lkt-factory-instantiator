<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\ValidateData\DataValidator;

/**
 * Trait ColumnStringTrait
 * @package Lkt\Factory\Instantiator\Instances\AccessDataTraits
 */
trait ColumnStringTrait
{
    /**
     * @param string $field
     * @return string
     */
    protected function _getStringVal(string $field) :string
    {
        if (isset($this->UPDATED[$field])) {
            return $this->UPDATED[$field];
        }
        return trim($this->DATA[$field]);
    }

    /**
     * @param string $field
     * @return bool
     */
    protected function _hasStringVal(string $field) :bool
    {
        $checkField = 'has'.ucfirst($field);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    /**
     * @param string $field
     * @param string|null $value
     */
    protected function _setStringVal(string $field, string $value = null)
    {
        $checkField = 'has'.ucfirst($field);
        DataValidator::getInstance($this->TYPE, [
            $field => $value,
        ]);
        $this->UPDATED = $this->UPDATED + DataValidator::getResult();
    }
}