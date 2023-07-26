<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;

trait ColumnBooleanTrait
{
    /**
     * @param string $fieldName
     * @return bool
     */
    protected function _getBooleanVal(string $fieldName): bool
    {
        if (isset($this->UPDATED[$fieldName])) {
            return $this->UPDATED[$fieldName];
        }
        return $this->DATA[$fieldName] === true;
    }

    /**
     * @param string $fieldName
     * @param bool $value
     * @return void
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _setBooleanVal(string $fieldName, bool $value = false): static
    {
        $converter = new RawResultsToInstanceConverter(static::GENERATED_TYPE, [
            $fieldName => $value,
        ], false);

        $this->UPDATED = $this->UPDATED + $converter->parse();
        return $this;
    }
}