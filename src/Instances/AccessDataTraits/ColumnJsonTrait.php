<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\FactorySettings;
use Lkt\Factory\Schemas\Fields\JSONField;
use Lkt\Factory\ValidateData\DataValidator;


trait ColumnJsonTrait
{
    /**
     * @param string $field
     * @return string
     */
    protected function _getJsonVal(string $field)
    {
        $r = [];
        if (isset($this->UPDATED[$field])) {
            $r = $this->UPDATED[$field];
        } else {
            $r = $this->DATA[$field];
        }

        $field = FactorySettings::getComponentField(static::GENERATED_TYPE, $field);
        /** @var JSONField $field */
        if ($field->isAssoc()){
            return $r;
        }
        return json_decode(json_encode($r));
    }

    /**
     * @param string $field
     * @return bool
     */
    protected function _hasJsonVal(string $field) :bool
    {
        $checkField = 'has'.ucfirst($field);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    /**
     * @param string $field
     * @param string|null $value
     */
    protected function _setJsonVal(string $field, $value = null)
    {
        if (is_object($value)){
            $value = json_decode(json_encode($value), true);

        } elseif (!is_array($value)){
            $value = [];
        }
        $checkField = 'has'.ucfirst($field);
        DataValidator::getInstance($this->TYPE, [
            $field => $value,
        ]);
        $this->UPDATED = $this->UPDATED + DataValidator::getResult();
    }
}