<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Instantiator\Exceptions\InvalidIntegerChoiceValueException;
use Lkt\Factory\Schemas\Fields\IntegerChoiceField;
use Lkt\Factory\Schemas\Schema;

trait ColumnIntegerChoiceTrait
{
    protected function _getIntegerChoiceVal(string $fieldName) :int
    {
        if (isset($this->UPDATED[$fieldName])) {
            return $this->UPDATED[$fieldName];
        }
        return trim($this->DATA[$fieldName]);
    }

    protected function _hasIntegerChoiceVal(string $fieldName) :bool
    {
        $checkField = 'has'.ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    protected function _integerChoiceIn(string $fieldName, array $values) :bool
    {
        $value = $this->_getIntegerChoiceVal($fieldName);
        return in_array($value, $values, true);
    }

    protected function _integerChoiceEqual(string $fieldName, int $compared) :bool
    {
        $value = $this->_getIntegerChoiceVal($fieldName);
        return $value === $compared;
    }

    protected function _setIntegerChoiceVal(string $fieldName, int $value = null): void
    {
        $schema = Schema::get(static::GENERATED_TYPE);
        /** @var IntegerChoiceField $field */
        $field = $schema->getField($fieldName);
        $availableOptions = $field->getAllowedOptions();

        if (!in_array($value, $availableOptions, true)) {
            throw InvalidIntegerChoiceValueException::getInstance($value, $fieldName, static::GENERATED_TYPE);
        }

        $converter = new RawResultsToInstanceConverter(static::GENERATED_TYPE, [
            $fieldName => $value,
        ], false);

        $this->UPDATED = $this->UPDATED + $converter->parse();
    }
}