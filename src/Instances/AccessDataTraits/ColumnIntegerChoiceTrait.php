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

        $comparedValues = array_map(function ($v){
            $c = $v;
            if (is_object($v) && property_exists($v, 'value') && isset($v->value)) {
                $c = $v->value;
            }
            return $c;

        }, $values);

        if ($field->isMultiple()) {
            /** @var int[] $value */
            $value = $this->_getIntegerChoiceVal($fieldName);
            if (count($value) === 0) return false;

            $r = true;
            foreach ($value as $val) {
                $r = $r && in_array($val, $comparedValues, true);
            }

            return $r;
        }

        $value = $this->_getIntegerChoiceVal($fieldName);
        return in_array($value, $comparedValues, true);
    }

    protected function _integerChoiceEqual(string $fieldName, int|array|object $compared): bool
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var IntegerField $field */
        $field = $schema->getField($fieldName);

        if ($field->isMultiple()) {
            /** @var int[] $value */
            $value = $this->_getIntegerChoiceVal($fieldName);

            $comparedValues = array_map(function ($v){
                $c = $v;
                if (is_object($v) && property_exists($v, 'value') && isset($v->value)) {
                    $c = $v->value;
                }
                return $c;

            }, $compared);

            return count($value) === count($comparedValues)
                && count(array_intersect($value, $comparedValues)) === 0;
        }

        $c = $compared;
        if (is_object($compared) && property_exists($compared, 'value') && isset($compared->value)) {
            $c = $compared->value;
        }

        $value = $this->_getIntegerChoiceVal($fieldName);
        return $value === $c;
    }

    /**
     * @note Object type value it's intended to match with an enum object
     */
    protected function _setIntegerChoiceVal(string $fieldName, int|array|object $value = null): static
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var IntegerChoiceField $field */
        $field = $schema->getField($fieldName);
        $availableOptions = $field->getAllowedOptions();

        if (is_array($value)) {
            foreach ($value as $val) {

                $v = $val;
                if (is_object($v) && isset($v->value)) {
                    $v = $v->value;
                }

                if (!in_array($v, $availableOptions, true)) {
                    throw InvalidIntegerChoiceValueException::getInstance($v, $fieldName, static::COMPONENT);
                }
            }
        } else {

            if (is_object($value) && property_exists($value, 'value') && isset($value->value)) {
                $value = $value->value;
            }

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