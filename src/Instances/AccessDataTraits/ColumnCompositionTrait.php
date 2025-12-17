<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Enums\CrudOperation;
use Lkt\Factory\Instantiator\Instances\AbstractInstance;
use Lkt\Factory\Schemas\CompositionSchema;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\AbstractField;
use Lkt\Factory\Schemas\Fields\BooleanField;
use Lkt\Factory\Schemas\Fields\ForeignKeyField;
use Lkt\Factory\Schemas\Fields\IntegerField;
use Lkt\Factory\Schemas\Fields\StringField;
use Lkt\Factory\Schemas\Schema;

trait ColumnCompositionTrait
{
    protected array $COMPOSED_DATA_UPDATED = [];
    protected array $COMPOSED_DATA = [];
    protected array $COMPOSED_DATA_ADDITIONAL_DATA = [];

    protected function _getCompositionAdditionalData(array $additionalData = [], string $fieldName = null, mixed $reflectedInstance = null, string $reflectedMethod = null)
    {
        $compositionSchema = Schema::get(static::COMPONENT);

        $compositionValuesFields = $fieldName ? $compositionSchema->getCompositionValueFields($fieldName) : $compositionSchema->getAllCompositionValueFields();

        /**
         * @var  $key
         * @var AbstractField $compositionValueField
         */
        foreach ($compositionValuesFields as $key => $compositionValueField) {
            if (!$additionalData[$key]) {
                if ($compositionValueField instanceof ForeignKeyField) {
                    $getterAux = $compositionValueField->getGetterForData();
                } else {
                    $getterAux = $compositionValueField->getGetterForPrimitiveValue();
                }

                if (is_callable([$this, $getterAux])) {
                    $additionalData[$key] = $this->{$getterAux}();
                }
            }
        }

        if ($reflectedInstance && $reflectedMethod) {

            $reflectionMethod = new \ReflectionMethod($reflectedInstance, $reflectedMethod);

            $params = $reflectionMethod->getParameters();

            $paramsKeys = array_map(function (\ReflectionParameter $param){ return $param->getName();}, $params);

            foreach (array_keys($additionalData) as $key) {
                if (!in_array($key, $paramsKeys)) unset($additionalData[$key]);
            }
        }

        return $additionalData;
    }

    protected function _getCompositionInstance(string $composedComponent, array $additionalData = []): mixed
    {
        dump(['_getCompositionInstance', static::COMPONENT, $composedComponent, $additionalData]);
        if (isset($this->COMPOSED_DATA[$composedComponent])) return $this->COMPOSED_DATA[$composedComponent];

        $this->COMPOSED_DATA_ADDITIONAL_DATA[$composedComponent] = $additionalData;
        $schema = Schema::get(static::COMPONENT);
        $compositionField = $schema->getCompositionField($composedComponent);
        $composedSchema = Schema::get($compositionField->getComponent());

        if ($compositionField instanceof ForeignKeyField) {
            $getter = $compositionField->getGetterForData();
        } else {
            $getter = $compositionField->getGetterForPrimitiveValue();
        }

        if (!is_callable([$this, $getter])) {
            $this->COMPOSED_DATA[$composedComponent] = null;
            return null;
        }

        $additionalData = $this->_getCompositionAdditionalData($additionalData, $composedComponent, $this, $getter);

        if (count($additionalData) > 0) {
            $composedInstance = call_user_func_array([$this, $getter], $additionalData);
        } else {
            $composedInstance = $this->{$getter}();
        }
        if (is_array($composedInstance)) {
            if (count($composedInstance) > 0) $composedInstance = $composedInstance[0];
            else  $composedInstance = null;
        }

        if ($composedInstance === null) {
            $appClass = $composedSchema->getInstanceSettings()->getAppClass();
            $emptyInstance = $appClass::getInstance();
            $emptyInstance::feedInstance($emptyInstance, $emptyInstance->prepareCrudData($additionalData, CrudOperation::Create));

            foreach ($composedSchema->getIdentifiers() as $identifier) {
                if (isset($additionalData[$identifier->getName()])) {
                    if ($additionalData[$identifier->getName()] instanceof AbstractInstance) {
                        $setter = $identifier->getSetterForPrimitiveValue();
                        $emptyInstance->{$setter}($additionalData[$identifier->getName()]?->getIdColumnValue());

                    } elseif($identifier instanceof ForeignKeyField) {
                        $setter = $identifier->getSetterForPrimitiveValue();
                        $content = $additionalData[$identifier->getName()] instanceof AbstractInstance ? $additionalData[$identifier->getName()]?->getIdColumnValue() : $additionalData[$identifier->getName()];
                        $emptyInstance->{$setter}($content);

                    } else {
                        $setter = $identifier->getSetter();
                        $emptyInstance->{$setter}($additionalData[$identifier->getName()]);
                    }
                } elseif ($identifier->getComponent() === static::COMPONENT) {
                    $setter = $identifier->getSetterForPrimitiveValue();
                    $emptyInstance->{$setter}($this->getIdColumnValue());
                }
            }

            $backPointerField = $composedSchema->getOneFieldPointingToComponent(static::COMPONENT);

            if ($backPointerField) {
                $setter = $identifier->getSetterForPrimitiveValue();
                $emptyInstance->{$setter}($this?->getIdColumnValue());
            }

            $composedInstance = $emptyInstance;
        }

        $this->COMPOSED_DATA[$composedComponent] = $composedInstance;
        return $this->COMPOSED_DATA[$composedComponent];
    }

    /**
     * @param string $composedComponent
     * @param string $fieldName
     * @return mixed
     * @throws SchemaNotDefinedException
     */
    protected function _getCompositionVal(string $composedComponent, string $fieldName, array $additionalData = []): mixed
    {
        $composedInstance = $this->_getCompositionInstance($composedComponent, $additionalData);

        $compositionSchema = Schema::get(static::COMPONENT);
        $compositionField = $compositionSchema->getCompositionField($composedComponent);
        $compositionContent = $compositionField->getCompositionContent();
        $composedFieldName = $compositionContent[$fieldName];

        $composedSchema = Schema::get($compositionField->getComponent());
        $composedField = $composedSchema->getField($composedFieldName);

        if (is_object($composedInstance)) {
            if ($composedField) {
                $composedFieldGetter = $composedField?->getGetterForPrimitiveValue();
                if (!$composedFieldGetter) return null;

                $additionalData = $this->_getCompositionAdditionalData($additionalData, $composedComponent, $composedInstance, $composedFieldGetter);

                if (count($additionalData) > 0) {
                    return call_user_func_array([$composedInstance, $composedFieldGetter], $additionalData);
                } else {
                    return $composedInstance?->{$composedFieldGetter}();
                }
            }

            $composedSchema = Schema::get($compositionField->getComponent());
            $composedField = $composedSchema->getCompositionField($composedFieldName);
            $composedFieldGetter = $composedField?->getGetterForPrimitiveValue();
            if (!$composedFieldGetter) return null;

            $additionalData = $this->_getCompositionAdditionalData($additionalData, $composedComponent, $composedInstance, $composedFieldGetter);

            if (count($additionalData) > 0) {
                return call_user_func_array([$composedInstance, $composedFieldGetter], $additionalData);
            } else {
                return $composedInstance?->{$composedFieldGetter}();
            }
        }

        if ($composedField instanceof BooleanField) return false;
        if ($composedField instanceof StringField) return '';
        if ($composedField instanceof IntegerField) return 0;

        return null;
    }

    /**
     * @param string $component
     * @param string $composedComponent
     * @param string $fieldName
     * @param mixed $value
     * @return $this
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _setCompositionVal(string $composedComponent, string $fieldName, mixed $value, array $additionalData = []): static
    {
        dump(['_setCompositionVal', static::COMPONENT, $composedComponent, $fieldName, $value, $additionalData]);
        $composedInstance = $this->_getCompositionInstance($composedComponent, $additionalData);

        $schema = Schema::get(static::COMPONENT);
        $field = $schema->getCompositionField($fieldName);
        $composedFieldName = $fieldName;

        if (!$field) {
            $nestedCompositionField = $schema->getCompositionFieldComposingThisField($fieldName);
            $nestedComposedSchema = Schema::get($nestedCompositionField->getComponent());
            $field = $nestedComposedSchema->getField($fieldName);
            $composedFieldName = $field->getName();
        }
        dump(['_setCompositionVal 2', $composedInstance, $schema, $field, $fieldName]);

        if (is_object($composedInstance)) {
            $composedSchema = $nestedComposedSchema ?? Schema::get($field->getComponent());
            $composedField = $composedSchema->getField($composedFieldName);
            $composedFieldSetter = $composedField->getSetterForPrimitiveValue();
            $composedInstance->{$composedFieldSetter}($value);
            if (!in_array($composedComponent, $this->COMPOSED_DATA_UPDATED)) {
                $this->COMPOSED_DATA_UPDATED[] = $composedComponent;
            }
        }
        return $this;
    }

    /**
     * @param string $composedComponent
     * @param string $fieldName
     * @return bool
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _hasCompositionVal(string $composedComponent, string $fieldName, array $additionalData = []): bool
    {
        $composedInstance = $this->_getCompositionInstance($composedComponent, $additionalData);

        $compositionSchema = Schema::get(static::COMPONENT);
        $compositionField = $compositionSchema->getCompositionField($fieldName);
        $compositionContent = $compositionSchema->getCompositionContent();

        if (is_object($composedInstance)) {
            $composedFieldName = $compositionContent[$fieldName];
            $composedSchema = Schema::get($compositionField->getComponent());
            $composedField = $composedSchema->getField($composedFieldName);
            $composedFieldGetter = $composedField->getGetterForChecker();
            return $composedInstance->{$composedFieldGetter}();
        }
        return false;
    }

    protected function _saveCompositionValues()
    {
        foreach ($this->COMPOSED_DATA_UPDATED as $composedComponent) {
            $schema = Schema::get(static::COMPONENT);
            $field = $schema->getCompositionField($composedComponent);

            $getter = $field->getGetterForPrimitiveValue();
            if (!is_callable([$this, $getter])) {
                return null;
            }

            $composedInstance = $this->_getCompositionInstance($composedComponent, $this->COMPOSED_DATA_ADDITIONAL_DATA[$composedComponent]);

            if (is_object($composedInstance) && is_callable([$composedInstance, 'save'])) {
                $composedInstance->save();
            }
            return null;
        }
    }
}