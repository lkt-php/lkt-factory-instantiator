<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;

trait ColumnStringTrait
{
    protected function _getStringVal(string $fieldName) :string
    {
        if (isset($this->UPDATED[$fieldName])) {
            return $this->UPDATED[$fieldName];
        }
        return trim($this->DATA[$fieldName]);
    }

    protected function _hasStringVal(string $fieldName) :bool
    {
        $checkField = 'has'.ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    protected function _setStringVal(string $fieldName, string $value = null): static
    {
        $converter = new RawResultsToInstanceConverter(static::GENERATED_TYPE, [
            $fieldName => $value,
        ], false);

        $this->UPDATED = $this->UPDATED + $converter->parse();
        return $this;
    }
}