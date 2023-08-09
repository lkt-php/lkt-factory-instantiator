<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Schemas\Schema;

trait ColumnConcatTrait
{
    protected function _getConcatVal(string $fieldName): string
    {
        $schema = Schema::get(static::COMPONENT);
        $field = $schema->getField($fieldName);
        $r = [];
        foreach ($field->getConcatenatedFields() as $concatenatedField) {
            $f = $schema->getField($concatenatedField);
            $r[] = $this->{$f->getGetterForComputed()}();
        }
        return trim(implode($field->getSeparator(), $r));
    }

    protected function _hasConcatVal(string $fieldName): bool
    {
        return $this->_getConcatVal($fieldName) !== '';
    }
}