<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Instances\AbstractInstance;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Schema;

trait ColumnForeignTrait
{
    /**
     * @param string $fieldName
     * @return AbstractInstance|null
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    protected function _getForeignVal($type = '', $id = 0, string $fieldName = ''): ?AbstractInstance
    {
        if ($fieldName !== '') {
            $schema = Schema::get(static::COMPONENT);
            $field = $schema->getField($fieldName);

            if ($field) {
                $type = $field->getComponent();
                $dynamicComponentFieldName = $field->getDynamicComponentField();
                if ($dynamicComponentFieldName !== '') {
                    $dynamicComponentField = $schema->getField($dynamicComponentFieldName);
                    $getter = $dynamicComponentField->getGetterForPrimitiveValue();
                    $dynamicType = $this->{$getter}();
                    if ($dynamicType !== '') $type = $dynamicType;
                }
                $id = $this->_getIntegerVal($fieldName . 'Id');
            }
        }

        if (!$type || $id <= 0) {
            return null;
        }
        return Instantiator::make($type, $id);
    }

    /**
     * @param string $fieldName
     * @return bool
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    protected function _hasForeignVal($type = '', $id = 0, string $fieldName = ''): bool
    {
        $schema = Schema::get(static::COMPONENT);
        $field = $schema->getForeignKeyField($fieldName);

        if ($field) {
            $type = $field->getComponent();
            $id = $this->_getIntegerVal($fieldName . 'Id');
        }

        return is_object($this->_getForeignVal($type, $id));
    }
}