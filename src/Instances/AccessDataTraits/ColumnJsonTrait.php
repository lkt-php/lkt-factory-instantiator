<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\JSONField;
use Lkt\Factory\Schemas\Schema;
use StdClass;

trait ColumnJsonTrait
{
    /**
     * @param string $fieldName
     * @return array|StdClass
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getJsonVal(string $fieldName)
    {
        if (isset($this->UPDATED[$fieldName])) {
            $r = $this->UPDATED[$fieldName];
        } else {
            $r = $this->DATA[$fieldName];
        }

        $schema = Schema::get(static::GENERATED_TYPE);
        /** @var JSONField $field */
        $field = $schema->getField($fieldName);

        if ($field->isAssoc()){
            /** @var array $r */
            return $r;
        }
        /** @var StdClass $r */
        $r = json_decode(json_encode($r));
        return $r;
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    protected function _hasJsonVal(string $fieldName) :bool
    {
        $checkField = 'has'.ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    /**
     * @param string $fieldName
     * @param $value
     * @return void
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _setJsonVal(string $fieldName, $value = null)
    {
        if (is_object($value)){
            $value = json_decode(json_encode($value), true);

        } elseif (!is_array($value)){
            $value = [];
        }
        $converter = new RawResultsToInstanceConverter(static::GENERATED_TYPE, [
            $fieldName => $value,
        ], false);
        $this->UPDATED = $this->UPDATED + $converter->parse();
    }
}