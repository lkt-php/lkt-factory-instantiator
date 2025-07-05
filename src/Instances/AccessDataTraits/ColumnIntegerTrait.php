<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Schemas\Fields\IntegerField;
use Lkt\Factory\Schemas\Schema;

trait ColumnIntegerTrait
{
    protected function _getIntegerVal(string $fieldName): int|array
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var IntegerField $field */
        $field = $schema->getField($fieldName);

        if (isset($this->UPDATED[$fieldName])) return $this->UPDATED[$fieldName];
        if (isset($this->DATA[$fieldName])) return $this->DATA[$fieldName];
        if ($field->isMultiple()) return [];
        return 0;
    }

    protected function _hasIntegerVal(string $fieldName): bool
    {
        $checkField = 'has' . ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) return $this->UPDATED[$checkField];
        if (isset($this->DATA[$checkField])) return $this->DATA[$checkField] === true;
        return false;
    }

    protected function _setIntegerVal(string $fieldName, int|array $value = null): static
    {
        $converter = new RawResultsToInstanceConverter(static::COMPONENT, [
            $fieldName => $value,
        ], false);

        foreach ($converter->parse() as $key => $value) $this->UPDATED[$key] = $value;
        return $this;
    }
}