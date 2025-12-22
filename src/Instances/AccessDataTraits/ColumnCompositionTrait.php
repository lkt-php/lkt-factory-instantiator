<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Enums\CrudOperation;
use Lkt\Factory\Instantiator\Instances\AbstractInstance;
use Lkt\Factory\Schemas\Enums\AccessPolicyEndOfLife;
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

    protected function _feedAnonymousComposedInstance(AbstractInstance $instance): AbstractInstance
    {
        $composedSchema = Schema::get($instance::COMPONENT);
        $remoteIdentifierPointingToMe = $composedSchema->getOneFieldPointingToComponent(static::COMPONENT);

        if ($remoteIdentifierPointingToMe) {
            $setter = $remoteIdentifierPointingToMe->getSetterForPrimitiveValue();
            $instance->{$setter}((int)$this?->getIdColumnValue());
        }

        return $instance;
    }

    protected function _getCompositionInstance(string $composedComponent, array $additionalData = []): mixed
    {
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
                        $emptyInstance->{$setter}((int)$additionalData[$identifier->getName()]?->getIdColumnValue());

                    } elseif($identifier instanceof ForeignKeyField) {
                        $setter = $identifier->getSetterForPrimitiveValue();
                        $content = (int)$additionalData[$identifier->getName()] instanceof AbstractInstance ? $additionalData[$identifier->getName()]?->getIdColumnValue() : $additionalData[$identifier->getName()];
                        $emptyInstance->{$setter}($content);

                    } else {
                        $setter = $identifier->getSetter();
                        $emptyInstance->{$setter}($additionalData[$identifier->getName()]);
                    }
                } elseif (method_exists($identifier, 'getComponent') && $identifier?->getComponent() === static::COMPONENT) {
                    $setter = $identifier->getSetterForPrimitiveValue();
                    $emptyInstance->{$setter}((int)$this->getIdColumnValue());
                }
            }

            $backPointerField = $composedSchema->getOneFieldPointingToComponent(static::COMPONENT);

            if ($backPointerField) {
                $setter = $backPointerField?->getSetterForPrimitiveValue();
                if ($setter) $emptyInstance->{$setter}((int)$this?->getIdColumnValue());
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

        if (is_object($composedInstance)) {
            $composedSchema = $nestedComposedSchema ?? Schema::get($field->getComponent());
            $composedField = $composedSchema->getField($composedFieldName);
            $composedFieldSetter = $composedField->getSetterForPrimitiveValue();
            $composedInstance->{$composedFieldSetter}($value);
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
        $compositionField = $compositionSchema->getCompositionField($composedComponent);
        $compositionContent = $compositionField->getCompositionContent();
        $composedFieldName = $compositionContent[$fieldName];

        if (is_object($composedInstance)) {
            $composedSchema = Schema::get($compositionField->getComponent());
            $composedField = $composedSchema->getField($composedFieldName);
            $composedFieldGetter = $composedField->getGetterForChecker();
            return $composedInstance->{$composedFieldGetter}();
        }
        return false;
    }

    protected function _saveCompositionValues(bool $isUpdate = false)
    {
        $schema = Schema::get(static::COMPONENT);

        foreach ($this->COMPOSED_DATA as $fieldName => $composedInstance) {

            if (!$isUpdate){
                $this->_feedAnonymousComposedInstance($composedInstance);
            }

            $relatedAccessPolicy = null;
            if ($this->accessPolicy) {
                $field = $schema->getCompositionField($fieldName);
                $relatedAccessPolicy = $schema->getAccessPolicyForRelationalField($this->accessPolicy, $field);
            }

            if (is_object($composedInstance) && is_callable([$composedInstance, 'save'])) {
                if ($relatedAccessPolicy) $composedInstance->setAccessPolicy($relatedAccessPolicy, AccessPolicyEndOfLife::UntilNextWrite);
                $composedInstance->save();
            }
        }
    }
}