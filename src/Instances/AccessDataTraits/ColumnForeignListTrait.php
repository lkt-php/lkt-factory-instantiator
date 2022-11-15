<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Instantiator\Instances\AbstractInstance;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\ForeignKeysField;
use Lkt\Factory\Schemas\Schema;

trait ColumnForeignListTrait
{
    /**
     * @param string $fieldName
     * @return array
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getForeignListIds(string $fieldName) :array
    {
        $schema = Schema::get(static::GENERATED_TYPE);

        /** @var ForeignKeysField $field */
        $field = $schema->getField($fieldName);
        $allowAnonymous = $field->anonymousAllowed();

        $items = explode(';', trim($this->_getForeignListVal($fieldName)));
        $items = array_filter($items, function ($item) use ($allowAnonymous) {
            $t = trim($item);
            if ($t === ''){
                return false;
            }
            if ($allowAnonymous){
                return true;
            }
            return (int)$t > 0;
        });

        return array_values($items);
    }

    /**
     * @param string $fieldName
     * @return array
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     * @throws InvalidSchemaAppClassException
     */
    protected function _getForeignListData(string $fieldName) :array
    {
        $schema = Schema::get(static::GENERATED_TYPE);

        /** @var ForeignKeysField $field */
        $field = $schema->getField($fieldName);


        $items = $this->_getForeignListIds($fieldName);

        $r = [];

        foreach ($items as $item){
            if (is_numeric($item)){
                $t = Instantiator::make($field->getComponent(), $item);
                if ($t instanceof AbstractInstance && !$t->isAnonymous()){
                    $r[] = $t;
                }
            } else {
                $r[] = $item;
            }
        }

        return $r;
    }

    /**
     * @param string $fieldName
     * @return string
     */
    protected function _getForeignListVal(string $fieldName) :string
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
    protected function _hasForeignListVal(string $fieldName) :bool
    {
        $checkField = 'has'.ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    /**
     * @param string $fieldName
     * @param string|array|null $value
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _setForeignListVal(string $fieldName, $value = null)
    {
        if (is_array($value)){
            $value = implode(';', $value);
        } elseif (!is_string($value)){
            $value = trim($value);
        }
        $converter = new RawResultsToInstanceConverter(static::GENERATED_TYPE, [
            $fieldName => $value,
        ], false);

        $this->UPDATED = $this->UPDATED + $converter->parse();
    }
}