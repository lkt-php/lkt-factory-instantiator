<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Instantiator\Exceptions\InvalidIntegerChoiceValueException;
use Lkt\Factory\Schemas\Fields\IntegerChoiceField;
use Lkt\Factory\Schemas\Schema;

trait ColumnIntegerChoiceTrait
{
    protected function _getIntegerChoiceVal(string $fieldName): int|array
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var IntegerField $field */
        $field = $schema->getField($fieldName);

        if (isset($this->UPDATED[$fieldName])) return $this->UPDATED[$fieldName];
        if (isset($this->DATA[$fieldName])) return $this->DATA[$fieldName];
        if ($field->isMultiple()) return [];
        return 0;
    }

    protected function _hasIntegerChoiceVal(string $fieldName): bool
    {
        $checkField = 'has' . ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    protected function _integerChoiceIn(string $fieldName, array $values): bool
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var IntegerField $field */
        $field = $schema->getField($fieldName);

        if ($field->isMultiple()) {
            /** @var int[] $value */
            $value = $this->_getIntegerChoiceVal($fieldName);
            if (count($value) === 0) return false;

            $r = true;
            foreach ($value as $val) {
                $r = $r && in_array($val, $values, true);
            }

            return $r;
        }

        $value = $this->_getIntegerChoiceVal($fieldName);
        return in_array($value, $values, true);
    }

    protected function _integerChoiceEqual(string $fieldName, int|array $compared): bool
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var IntegerField $field */
        $field = $schema->getField($fieldName);

        if ($field->isMultiple()) {
            /** @var int[] $value */
            $value = $this->_getIntegerChoiceVal($fieldName);
            return count($value) === count($compared)
                && count(array_intersect($value, $compared)) === 0;
        }

        $value = $this->_getIntegerChoiceVal($fieldName);
        return $value === $compared;
    }

    protected function _setIntegerChoiceVal(string $fieldName, int|array $value = null): static
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var IntegerChoiceField $field */
        $field = $schema->getField($fieldName);
        $availableOptions = $field->getAllowedOptions();

        if (is_array($value)) {
            foreach ($value as $val) {
                if (!in_array($val, $availableOptions, true)) {
                    throw InvalidIntegerChoiceValueException::getInstance($val, $fieldName, static::COMPONENT);
                }
            }
        } else {
            if (!in_array($value, $availableOptions, true)) {
                throw InvalidIntegerChoiceValueException::getInstance($value, $fieldName, static::COMPONENT);
            }
        }

        $converter = new RawResultsToInstanceConverter(static::COMPONENT, [
            $fieldName => $value,
        ], false);

        foreach ($converter->parse() as $key => $value) {
            $this->UPDATED[$key] = $value;
        }
        return $this;
    }
}