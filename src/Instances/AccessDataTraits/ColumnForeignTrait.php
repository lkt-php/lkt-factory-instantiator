<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Instances\AbstractInstance;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;

trait ColumnForeignTrait
{
    /**
     * @param $type
     * @param $id
     * @return AbstractInstance|null
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    protected function _getForeignVal($type = '', $id = 0): ?AbstractInstance
    {
        if (!$type || $id <= 0) {
            return null;
        }
        return Instantiator::make($type, $id);
    }

    /**
     * @param string $type
     * @param $id
     * @return bool
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    protected function _hasForeignVal(string $type = '', $id = 0): bool
    {
        return is_object($this->_getForeignVal($type, $id));
    }
}