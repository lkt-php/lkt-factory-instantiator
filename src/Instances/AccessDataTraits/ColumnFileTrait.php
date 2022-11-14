<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use chillerlan\Filereader\File;
use Lkt\Factory\ValidateData\DataValidator;


trait ColumnFileTrait
{
    /**
     * @param string $field
     * @return File|null
     */
    protected function _getFileVal(string $field)
    {
        if (isset($this->UPDATED[$field])) {
            return $this->UPDATED[$field];
        }
        return $this->DATA[$field];
    }

    /**
     * @param string $field
     * @return bool
     */
    protected function _hasFileVal(string $field) :bool
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
    protected function _setFileVal(string $field, string $value = null)
    {
        $checkField = 'has'.ucfirst($field);
        DataValidator::getInstance($this->TYPE, [
            $field => $value,
        ]);
        $this->UPDATED = $this->UPDATED + DataValidator::getResult();
    }

    /**
     * @param string $field
     * @return string
     */
    protected function _getInternalPath(string $field)
    {
        $file = $this->_getFileVal($field);
        return $file->directory->path;
    }

    /**
     * @param string $field
     * @param string $src
     */
    protected function _setInternalPath(string $field, string $src)
    {
        $file = $this->_getFileVal($field);
        $file->directory->change($src);
    }
}