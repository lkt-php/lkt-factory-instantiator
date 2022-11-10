<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\FactorySettings;
use Lkt\Factory\InstanceFactory;
use Lkt\Factory\Schemas\Fields\ForeignKeysField;
use Lkt\Factory\ValidateData\DataValidator;

/**
 * Trait ColumnForeignListTrait
 *
 * @package Lkt\Factory\Instantiator\Instances\AccessDataTraits
 */
trait ColumnForeignListTrait
{
    /**
     * @param string $field
     * @return array
     */
    protected function _getForeignListIds(string $field) :array
    {
        /** @var ForeignKeysField $fieldData */
        $fieldData = FactorySettings::getComponentField(static::GENERATED_TYPE, $field);
        $allowAnonymous = $fieldData->anonymousAllowed();
        $items = explode(';', trim($this->_getForeignListVal($field)));
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
     * @param string $field
     * @return array
     */
    protected function _getForeignListData(string $field) :array
    {
        /** @var ForeignKeysField $fieldData */
        $fieldData = FactorySettings::getComponentField(static::GENERATED_TYPE, $field);

        $items = $this->_getForeignListIds($field);

        $r = [];

        foreach ($items as $item){
            if (is_numeric($item)){
                /** @var \Lkt\Factory\AbstractInstances\AbstractInstance $t */
                $t = InstanceFactory::getInstance($fieldData->getComponent(), $item)->instance();
                if (!$t->isAnonymous()){
                    $r[] = $t;
                }
            } else {
                $r[] = $item;
            }
        }

        return $r;
    }

    /**
     * @param string $field
     * @return string
     */
    protected function _getForeignListVal(string $field) :string
    {
        if (isset($this->UPDATED[$field])) {
            return $this->UPDATED[$field];
        }
        return trim($this->DATA[$field]);
    }

    /**
     * @param string $field
     * @return bool
     */
    protected function _hasForeignListVal(string $field) :bool
    {
        $checkField = 'has'.ucfirst($field);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    /**
     * @param string $field
     * @param string|array|null $value
     */
    protected function _setForeignListVal(string $field, $value = null)
    {
        if (is_array($value)){
            $value = implode(';', $value);
        } elseif (!is_string($value)){
            $value = trim($value);
        }
        $checkField = 'has'.ucfirst($field);
        DataValidator::getInstance($this->TYPE, [
            $field => $value,
        ]);
        $this->UPDATED = $this->UPDATED + DataValidator::getResult();
    }
}