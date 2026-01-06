<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Instantiator\Exceptions\InvalidStringChoiceValueException;
use Lkt\Factory\Schemas\Fields\StringChoiceField;
use Lkt\Factory\Schemas\Schema;

trait ColumnStringChoiceTrait
{
    protected function _getStringChoiceVal(string $fieldName): string
    {
        if (isset($this->UPDATED[$fieldName])) {
            return $this->UPDATED[$fieldName];
        }
        return trim($this->DATA[$fieldName]);
    }

    protected function _hasStringChoiceVal(string $fieldName): bool
    {
        $checkField = 'has' . ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    protected function _stringChoiceIn(string $fieldName, array $values): bool
    {
        $value = $this->_getStringChoiceVal($fieldName);
        return in_array($value, $values, true);
    }

    protected function _stringChoiceEqual(string $fieldName, string|object $compared): bool
    {
        $c = $compared;
        if (is_object($compared) && property_exists($compared, 'value') && isset($compared->value)) {
            $c = $compared->value;
        }

        $value = $this->_getStringChoiceVal($fieldName);
        return $value === $c;
    }

    /**
     * @note Object type value it's intended to match with an enum object
     */
    protected function _setStringChoiceVal(string $fieldName, string|array|object $value = null): static
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var StringChoiceField $field */
        $field = $schema->getField($fieldName);
        $availableOptions = $field->getAllowedOptions();

        if (is_array($value)) {
            foreach ($value as $val) {

                $v = $val;
                if (is_object($v) && isset($v->value)) {
                    $v = $v->value;
                }

                if (!in_array($v, $availableOptions, true)) {
                    throw InvalidStringChoiceValueException::getInstance($v, $fieldName, static::COMPONENT);
                }
            }

        } else {

            if (is_object($value) && property_exists($value, 'value') && isset($value->value)) {
                $value = $value->value;
            }

            if (!in_array($value, $availableOptions, true)) {
                throw InvalidStringChoiceValueException::getInstance($value, $fieldName, static::COMPONENT);
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