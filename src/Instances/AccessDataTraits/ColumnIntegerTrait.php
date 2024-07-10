<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;

trait ColumnIntegerTrait
{
    protected function _getIntegerVal(string $fieldName): int
    {
        if (isset($this->UPDATED[$fieldName])) {
            return $this->UPDATED[$fieldName];
        }
        if (isset($this->DATA[$fieldName])) {
            return (int)$this->DATA[$fieldName];
        }
        return 0;
    }

    protected function _hasIntegerVal(string $fieldName): bool
    {
        $checkField = 'has' . ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        if (isset($this->DATA[$checkField])) {
            return $this->DATA[$checkField] === true;
        }
        return false;
    }

    protected function _setIntegerVal(string $fieldName, int $value = null): static
    {
        $converter = new RawResultsToInstanceConverter(static::GENERATED_TYPE, [
            $fieldName => $value,
        ], false);
        $this->UPDATED = $this->UPDATED + $converter->parse();
        return $this;
    }
}