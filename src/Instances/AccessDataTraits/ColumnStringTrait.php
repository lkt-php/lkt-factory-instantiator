<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;

trait ColumnStringTrait
{
    /**
     * @param string $fieldName
     * @return string
     */
    protected function _getStringVal(string $fieldName) :string
    {
        if (isset($this->UPDATED[$fieldName])) {
            return $this->UPDATED[$fieldName];
        }
        return trim($this->DATA[$fieldName]);
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    protected function _hasStringVal(string $fieldName) :bool
    {
        $checkField = 'has'.ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    /**
     * @param string $fieldName
     * @param string|null $value
     * @return void
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _setStringVal(string $fieldName, string $value = null): void
    {
        $converter = new RawResultsToInstanceConverter(static::GENERATED_TYPE, [
            $fieldName => $value,
        ]);

        $this->UPDATED = $this->UPDATED + $converter->parse();
    }
}