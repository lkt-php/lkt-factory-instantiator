<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Schemas\CompositionSchema;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Schema;

trait ColumnCompositionTrait
{
    protected array $COMPOSED_DATA_UPDATED = [];

    /**
     * @param string $composedComponent
     * @param string $fieldName
     * @return mixed
     * @throws SchemaNotDefinedException
     */
    protected function _getCompositionVal(string $composedComponent, string $fieldName): mixed
    {
        $compositionSchema = CompositionSchema::get(static::COMPONENT);
        $compositionContent = $compositionSchema->getCompositionContent($composedComponent);
        $compositionField = $compositionContent->getRelatedField();

        $getter = $compositionField->getGetterForPrimitiveValue();
        if (!is_callable([$this, $getter])) {
            return null;
        }

        $composedInstance = $this->{$getter}();

        if (is_object($composedInstance)) {
            $composedFieldName = $compositionContent->fields[$fieldName];
            $composedSchema = Schema::get($compositionField->getComponent());
            $composedField = $composedSchema->getField($composedFieldName);
            $composedFieldGetter = $composedField->getGetterForPrimitiveValue();
            return $composedInstance->{$composedFieldGetter}();
        }
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
    protected function _setCompositionVal(string $composedComponent, string $fieldName, mixed $value): static
    {
        $compositionSchema = CompositionSchema::get(static::COMPONENT);
        $compositionContent = $compositionSchema->getCompositionContent($composedComponent);
        $compositionField = $compositionContent->getRelatedField();

        $getter = $compositionField->getGetterForPrimitiveValue();
        if (!is_callable([$this, $getter])) {
            return $this;
        }

        $composedInstance = $this->{$getter}();

        if (is_object($composedInstance)) {
            $composedFieldName = $compositionContent->fields[$fieldName];
            $composedSchema = Schema::get($compositionField->getComponent());
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
    protected function _hasCompositionVal(string $composedComponent, string $fieldName): bool
    {
        $compositionSchema = CompositionSchema::get(static::COMPONENT);
        $compositionContent = $compositionSchema->getCompositionContent($composedComponent);
        $compositionField = $compositionContent->getRelatedField();

        $getter = $compositionField->getGetterForPrimitiveValue();
        if (!is_callable([$this, $getter])) {
            return false;
        }

        $composedInstance = $this->{$getter}();

        if (is_object($composedInstance)) {
            $composedFieldName = $compositionContent->fields[$fieldName];
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
            $compositionSchema = CompositionSchema::get(static::COMPONENT);
            $compositionContent = $compositionSchema->getCompositionContent($composedComponent);
            $compositionField = $compositionContent->getRelatedField();

            $getter = $compositionField->getGetterForPrimitiveValue();
            if (!is_callable([$this, $getter])) {
                return null;
            }

            $composedInstance = $this->{$getter}();

            if (is_object($composedInstance) && is_callable([$composedInstance, 'save'])) {
                $composedInstance->save();
            }
            return null;
        }
    }
}