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

        $schema = Schema::get(static::COMPONENT);
        /** @var JSONField $field */
        $field = $schema->getField($fieldName);

        if ($field->isAssoc()) {
            if (is_null($r)) $r = json_decode('{}', true);
            if (is_string($r)) $r = json_decode($r, true);
            /** @var array $r */
            return $r;
        }


        if (is_null($r)) {
            return json_decode('{}');
        }
        if (is_string($r)) {
            return json_decode($r);
        }
        /** @var StdClass $r */
        $r = json_decode(json_encode($r));
        return $r;
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    protected function _hasJsonVal(string $fieldName): bool
    {
        $checkField = 'has' . ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    /**
     * @param string $fieldName
     * @param $value
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _setJsonVal(string $fieldName, $value = null): static
    {
        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);

        } elseif (!is_array($value)) {
            $value = [];
        }
        $converter = new RawResultsToInstanceConverter(static::COMPONENT, [
            $fieldName => $value,
        ], false);

        foreach ($converter->parse() as $key => $value) {
            $this->UPDATED[$key] = $value;
        }
        return $this;
    }
}