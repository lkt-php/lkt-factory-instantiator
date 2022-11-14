<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Parsers\DataParser;
use Lkt\Factory\ValidateData\DataValidator;


trait ColumnFloatTrait
{
    /**
     * @param string $field
     * @return string
     */
    protected function _getFloatVal(string $field) :float
    {
        if (isset($this->UPDATED[$field])) {
            return $this->UPDATED[$field];
        }
        return (float)$this->DATA[$field];
    }

    /**
     * @param string $field
     * @return string
     */
    protected function _getFloatFormattedVal(string $field) :string
    {
        $formatter = DataParser::getDecimalNumberFormatter();
        return $formatter->format($this->_getFloatVal($field));
    }

    /**
     * @param string $field
     * @return bool
     */
    protected function _hasFloatVal(string $field) :bool
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
    protected function _setFloatVal(string $field, float $value = null)
    {
        $checkField = 'has'.ucfirst($field);
        DataValidator::getInstance($this->TYPE, [
            $field => $value,
        ]);
        $this->UPDATED = $this->UPDATED + DataValidator::getResult();
    }
}