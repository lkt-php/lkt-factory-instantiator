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
     * @return File|null|File[]
     */
    protected function _getFileVal(string $fieldName): File|array|null
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var FileField $field */
        $field = $schema->getField($fieldName);
        if ($field->isMultiple()) {
            if (isset($this->UPDATED[$fieldName])) {
                return $this->UPDATED[$fieldName];
            }
            if ($this->DATA[$fieldName]) {
                return $this->DATA[$fieldName];
            }
            return [];
        }

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
    protected function _setFileVal(string $fieldName, string|array $value = null): static
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var FileField $field */
        $field = $schema->getField($fieldName);

        if ($field->isMultiple()) {

            $raw = $this->_getFileVal($fieldName);
            $current = $this->_getPublicPath($fieldName);
            $parsed = [];
            foreach ($value as $v) {
                $p = array_search($v, $current);
                if ($p !== false) {
                    if ($raw[$p] instanceof File) {
                        $parsed[] = $raw[$p]->name;
                    } else {
                        $parsed[] = $raw[$p];
                    }
                } else {
                    $parsed[] = $v;
                }
            }

            $this->UPDATED[$fieldName] = $parsed;
            return $this;
        }

        if ($value === $this->_getPublicPath($fieldName)) return $this;
        $value = trim($value);

        if (str_contains($value, ';base64,')) {
            $this->UPDATED[$fieldName] = $value;

        } else {
            $converter = new RawResultsToInstanceConverter(static::COMPONENT, [
                $fieldName => $value,
            ], false, $this);

            foreach ($converter->parse() as $key => $value) {
                $this->UPDATED[$key] = $value;
            }
        }
        return $this;
    }

    /**
     * @param string $fieldName
     * @param string|null $value
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _setFileValWithHttpFile(string $fieldName, array $value = null): static
    {
        $this->UPLOADING_FILES[$fieldName] = $value;
        return $this;
    }

    protected function _fileValUpdatedWithBase64Data(string $fieldName): bool
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var FileField $field */
        $field = $schema->getField($fieldName);

        if ($field->isMultiple()) {
            $r = false;
            foreach ($this->UPDATED[$fieldName] as $item) {
                $src = $item instanceof File ? $item->path : trim($item);
                if (is_string($src) && strlen($src) > 5 && str_contains($src, ';base64,')) {
                    $r = true;
                    break;
                }
            }
            return $r;
        }

        $src = $this->UPDATED[$fieldName] instanceof File ? $this->UPDATED[$fieldName]->path : trim($this->UPDATED[$fieldName]);

        return is_string($src)
            && strlen($src) > 5
            && str_contains($src, ';base64,');
    }

    protected function _storeBase64DataAsFile(string $fieldName, File|string $file, $id): static
    {
        $content = $file instanceof File ? $file->path : $file;
        $base64 = explode(';base64,', $content)[1];
        $content = base64_decode($base64);

        $f = finfo_open();

        $mime_type = finfo_buffer($f, $content, FILEINFO_MIME_TYPE);
        finfo_close($f);

        $ext = MIME::getExtensionByMime($mime_type);

        $schema = Schema::get(static::COMPONENT);
        $field = $schema->getFileField($fieldName);
        $storePath = $field->getStorePath($this);

        $component = static::COMPONENT;
        $storeName = "$component-$id-$fieldName.$ext";
        $name = "$storePath/$storeName";

        file_put_contents($name, $content);

        $this->_setFileVal($fieldName, $storeName);
        return $this;
    }

    protected function _storeBase64DataAsFiles(string $fieldName, array $files, $id): static
    {
        $finalValue = [];
        foreach ($files as $i => $file) {
            $content = $file instanceof File ? $file->path : $file;
            $base64 = explode(';base64,', $content)[1];
            $content = base64_decode($base64);

            $f = finfo_open();

            $mime_type = finfo_buffer($f, $content, FILEINFO_MIME_TYPE);
            finfo_close($f);

            $ext = MIME::getExtensionByMime($mime_type);

            $schema = Schema::get(static::COMPONENT);
            $field = $schema->getFileField($fieldName);
            $storePath = $field->getStorePath($this);

            $component = static::COMPONENT;
            $j = $i + 1;
            $storeName = "$component-$id-$fieldName-$j.$ext";
            $name = "$storePath/$storeName";

            file_put_contents($name, $content);
            $finalValue[] = $storeName;
        }

        $this->_setFileVal($fieldName, $finalValue);
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
    protected function _getPublicPath(string $fieldName): string|array
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var FileField $field */
        $field = $schema->getField($fieldName);

        if ($field->hasPublicPath() && $field->isMultiple()) {
            $r = [];
            $path = $field->getPublicPath($this);
            foreach ($this->_getFileVal($fieldName) as $i => $item) {
                $r[] = $this->parseFileName($path, $field, $i + 1);
            }
            return $r;
        }

        if ($field->hasPublicPath($this)) {
//            $r = $field->getPublicPath() . '/' . $this->_getFileName($fieldName);
            $r = $field->getPublicPath($this);
            $r = str_replace(':component', static::COMPONENT, $r);
            $r = str_replace(':field', $fieldName, $r);
            $r = str_replace(':id', $this->getIdColumnValue(), $r);
            $r = str_replace(':value', $this->_getFileName($fieldName), $r);
            return $r;
        }
        return '';
    }

    /**
     * @param string $fieldName
     * @return string
     */
    protected function _getFileName(string $fieldName, int $index = 0): string
    {
        $field = $this->_getFileFieldConfig($fieldName);

        if ($field->isMultiple()) {
            $items = $this->_getFileVal($fieldName);
            return trim($items[$index]->name);
        }

        $file = $this->_getFileVal($fieldName);
        return trim($file->name);
    }

    public function parseFileName(string $name, FileField $field, int|null $index = null): string
    {
        $fieldName = $field->getName();
        $r = str_replace(':component', static::COMPONENT, $name);
        $r = str_replace(':field', $fieldName, $r);
        $r = str_replace(':id', $this->getIdColumnValue(), $r);
        $r = str_replace(':value', $this->_getFileName($fieldName, $index - 1), $r);
        if (is_numeric($index)) $r = str_replace(':index', $index, $r);
        return $r;
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

    /**
     * @param string $fieldName
     * @return FileField|null
     * @throws SchemaNotDefinedException
     */
    protected function _getFileContent(string $fieldName, int $index = 0): ?string
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var FileField $field */
        $field = $schema->getField($fieldName);

//        if ($field->isMultiple()) {
//            $items = $this->_getFileVal($fieldName);
//
//        }

        $name = $this->_getFileName($fieldName, $index);
        return file_get_contents($field->getStorePath().'/'.$name);
    }

    /**
     * @param string $fieldName
     * @return FileField|null
     * @throws SchemaNotDefinedException
     */
    protected function _getFileExtension(string $fieldName, int $index = 0): ?string
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var FileField $field */
        $field = $schema->getField($fieldName);

        $name = $this->_getFileName($fieldName, $index);
        return file_get_contents($field->getStorePath().'/'.$name);
    }

    /**
     * @param string $fieldName
     * @return FileField|null
     * @throws SchemaNotDefinedException
     */
    protected function _getFileLastModified(string $fieldName, int $index = 0): false|int
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var FileField $field */
        $field = $schema->getField($fieldName);

        $name = $this->_getFileName($fieldName, $index);
        return filemtime($field->getStorePath().'/'.$name);
    }

    /**
     * @param string $fieldName
     * @return FileField|null
     * @throws SchemaNotDefinedException
     */
    protected function _getFileSize(string $fieldName, int $index = 0): false|int
    {
        $schema = Schema::get(static::COMPONENT);
        /** @var FileField $field */
        $field = $schema->getField($fieldName);

        $name = $this->_getFileName($fieldName, $index);
        return filesize($field->getStorePath().'/'.$name);
    }
}