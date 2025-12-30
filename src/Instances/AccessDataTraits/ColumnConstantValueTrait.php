<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Schemas\Schema;

trait ColumnConstantValueTrait
{
    protected function _getConstantValueVal(string $fieldName): mixed
    {
        $schema = Schema::get(static::COMPONENT);
        $field = $schema->getField($fieldName);
        return $field->getConstantValue();
    }
}