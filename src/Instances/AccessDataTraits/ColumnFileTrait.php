<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use chillerlan\Filereader\File;
use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\FileField;
use Lkt\Factory\Schemas\Schema;
use Lkt\MIME;

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
        $value = trim($value);
        $converter = new RawResultsToInstanceConverter(static::GENERATED_TYPE, [
            $fieldName => $value,
        ], false);

        $this->UPDATED = $this->UPDATED + $converter->parse();
        return $this;
    }

    protected function _fileValUpdatedWithBase64Data(string $fieldName): bool
    {
        $src = $this->UPDATED[$fieldName] instanceof File ? $this->UPDATED[$fieldName]->path : null;

        return is_string($src)
            && strlen($src) > 5
            && str_contains($src, ';base64,');
    }

    protected function _storeBase64DataAsFile(string $fieldName, File $file, $fileName): static
    {
        $base64 = explode(';base64,', $file->path)[1];
        $content = base64_decode($base64);

        $f = finfo_open();

        $mime_type = finfo_buffer($f, $content, FILEINFO_MIME_TYPE);
        finfo_close($f);

        $ext = MIME::getExtensionByMime($mime_type);

        $schema = Schema::get(static::COMPONENT);
        $field = $schema->getFileField($fieldName);
        $storePath = $field->getStorePath();

        $storeName = "$fileName";
        $name = "$storePath/$storeName.$ext";

        file_put_contents($name, $content);

        $this->_setFileVal($fieldName, $storeName);
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
        $schema = Schema::get(static::COMPONENT);
        /** @var FileField $field */
        $field = $schema->getField($fieldName);

        if ($field->hasPublicPath()) {
            $r = $field->getPublicPath() . '/' . $this->_getFileName($fieldName);
            $r = str_replace(':component', static::COMPONENT, $r);
            return $r;
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

    /**
     * @param string $fieldName
     * @return FileField|null
     * @throws SchemaNotDefinedException
     */
    protected function _getFileFieldConfig(string $fieldName): ?FileField
    {
        $schema = Schema::get(static::COMPONENT);
        return $schema->getFileField($fieldName);
    }
}