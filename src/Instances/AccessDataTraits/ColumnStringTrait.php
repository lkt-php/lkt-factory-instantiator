<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Schemas\Exceptions\DuplicatedValueException;
use Lkt\Factory\Schemas\Schema;

trait ColumnStringTrait
{
    protected function _getStringVal(string $fieldName): string
    {
        if (isset($this->UPDATED[$fieldName])) {
            return $this->UPDATED[$fieldName];
        }
        return trim($this->DATA[$fieldName]);
    }

    protected function _hasStringVal(string $fieldName): bool
    {
        $checkField = 'has' . ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    protected function _setStringVal(string $fieldName, string $value = null): static
    {
        $converter = new RawResultsToInstanceConverter(static::COMPONENT, [
            $fieldName => $value,
        ], false);

        $schema = Schema::get(static::COMPONENT);
        $field = $schema->getField($fieldName);
        if (is_object($field) && method_exists($field, 'isUnique') && $field->isUnique()) {
            $setter = 'and' . ucfirst($fieldName) . 'Equal';
            $builder = static::getQueryCaller()->{$setter}($value);
            $result = static::getOne($builder);
            if ($result instanceof static && $result->getIdColumnValue() !== $this->getIdColumnValue()) {
                throw DuplicatedValueException::getInstance($value);
            }
        }

        foreach ($converter->parse() as $key => $value) {
            $this->UPDATED[$key] = $value;
        }
        return $this;
    }
}