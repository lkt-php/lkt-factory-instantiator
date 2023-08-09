<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use chillerlan\Filereader\File;
use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\FileField;
use Lkt\Factory\Schemas\Schema;

trait ColumnFileTrait
{
    /**
     * @param string $fieldName
     * @return File|null
     */
    protected function _getFileVal(string $fieldName): ?File
    {
        if (isset($this->UPDATED[$fieldName]) && $this->UPDATED[$fieldName] instanceof File) {
            return $this->UPDATED[$fieldName];
        }
        if ($this->DATA[$fieldName] instanceof File) {
            return $this->DATA[$fieldName];
        }
        return null;
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    protected function _hasFileVal(string $fieldName): bool
    {
        $checkField = 'has' . ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    /**
     * @param string $fieldName
     * @param string|null $value
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _setFileVal(string $fieldName, string $value = null): static
    {
        $converter = new RawResultsToInstanceConverter(static::GENERATED_TYPE, [
            $fieldName => $value,
        ], false);

        $this->UPDATED = $this->UPDATED + $converter->parse();
        return $this;
    }

    /**
     * @param string $fieldName
     * @return string
     */
    protected function _getInternalPath(string $fieldName): string
    {
        $file = $this->_getFileVal($fieldName);
        return trim($file->directory->path);
    }

    /**
     * @param string $fieldName
     * @return string
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getPublicPath(string $fieldName): string
    {
        $schema = Schema::get(static::GENERATED_TYPE);
        /** @var FileField $field */
        $field = $schema->getField($fieldName);

        if ($field->hasPublicPath()) {
            return $field->getPublicPath() . '/' . $this->_getFileName($fieldName);
        }
        return '';
    }

    /**
     * @param string $fieldName
     * @return string
     */
    protected function _getFileName(string $fieldName): string
    {
        $file = $this->_getFileVal($fieldName);
        return trim($file->name);
    }

    /**
     * @param string $fieldName
     * @param string $src
     * @return void
     */
    protected function _setInternalPath(string $fieldName, string $src)
    {
        $file = $this->_getFileVal($fieldName);
        $file->directory->change($src);
    }
}