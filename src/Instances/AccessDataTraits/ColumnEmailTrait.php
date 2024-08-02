<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;

trait ColumnEmailTrait
{
    protected function _getEmailVal(string $fieldName): string
    {
        if (isset($this->UPDATED[$fieldName])) {
            return $this->UPDATED[$fieldName];
        }
        return trim($this->DATA[$fieldName]);
    }

    protected function _hasEmailVal(string $fieldName): bool
    {
        $checkField = 'has' . ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    protected function _setEmailVal(string $fieldName, string $value = null): static
    {
        $converter = new RawResultsToInstanceConverter(static::COMPONENT, [
            $fieldName => $value,
        ], false);

        foreach ($converter->parse() as $key => $value) {
            $this->UPDATED[$key] = $value;
        }
        return $this;
    }
}