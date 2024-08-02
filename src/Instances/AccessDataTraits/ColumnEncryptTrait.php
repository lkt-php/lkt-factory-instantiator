<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Instantiator\Encrypt\EncryptFieldHelper;
use Lkt\Factory\Schemas\Schema;

trait ColumnEncryptTrait
{
    protected function _getEncryptVal(string $fieldName): string
    {
        if (isset($this->UPDATED[$fieldName])) {
            return $this->UPDATED[$fieldName];
        }
        return trim($this->DATA[$fieldName]);
    }

    protected function _getDecryptedVal(string $fieldName): string
    {
        if (isset($this->DECRYPT_UPDATED[$fieldName])) {
            return $this->DECRYPT_UPDATED[$fieldName];
        }
        if (isset($this->DECRYPT[$fieldName])) {
            return $this->DECRYPT[$fieldName];
        }

        $value = $this->_getEncryptVal($fieldName);

        $schema = Schema::get(static::COMPONENT);
        $field = $schema->getField($fieldName);

        if ($field->hasAlgorithmSHA256()) {
            $secureSeed = $field->getSecureSeed();

            $this->DECRYPT_UPDATED[$fieldName] = $value;

            $this->DECRYPT[$fieldName] = EncryptFieldHelper::encryptSHA256($value, $secureSeed);
        }

        return trim($this->DECRYPT[$fieldName]);
    }

    protected function _hasEncryptVal(string $fieldName): bool
    {
        $checkField = 'has' . ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    protected function _setEncryptVal(string $fieldName, string $value = null): static
    {
        $schema = Schema::get(static::COMPONENT);
        $field = $schema->getField($fieldName);

        if ($field->hasAlgorithmSHA256() && !$field->isHashMode()) {
            $this->DECRYPT_UPDATED[$fieldName] = $value;
        }
        $value = EncryptFieldHelper::autoEncryptSchemaFieldValue(static::COMPONENT, $fieldName, $value);

        $converter = new RawResultsToInstanceConverter(static::COMPONENT, [
            $fieldName => $value,
        ], false);

        foreach ($converter->parse() as $key => $value) {
            $this->UPDATED[$key] = $value;
        }
        return $this;
    }
}