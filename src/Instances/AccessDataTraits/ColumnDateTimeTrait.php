<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Carbon\Carbon;
use Lkt\Factory\ValidateData\DataValidator;


trait ColumnDateTimeTrait
{
    /**
     * @param string $field
     * @return Carbon|null
     */
    protected function _getDateTimeVal(string $field)
    {
        if (isset($this->UPDATED[$field])) {
            return $this->UPDATED[$field];
        }
        return $this->DATA[$field];
    }

    /**
     * @param string $field
     * @param string|null $format
     * @return string
     */
    protected function _getDateTimeFormattedVal(string $field, string $format = null) :string
    {
        if (!$this->_hasDateTimeVal($field)) {
            return '';
        }
        return $this->_getDateTimeVal($field)->format($format);
    }

    /**
     * @param string $field
     * @return bool
     */
    protected function _hasDateTimeVal(string $field) :bool
    {
        $checkField = 'has'.ucfirst($field);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    /**
     * @param string $field
     * @param Carbon|\DateTime|string|int|null $value
     */
    protected function _setDateTimeVal(string $field, $value = null)
    {
        $realValue = null;
        if ($value instanceof \DateTime) {
            $realValue = $value->format('Y-m-d H:i:s');
        } elseif ($value instanceof Carbon) {
            $realValue = $value->format('Y-m-d H:i:s');
        } elseif (is_string($value)) {
            $realValue = $value;
        } elseif (is_int($value)) {
            $realValue = date('Y-m-d H:i:s', $value);
        }

        DataValidator::getInstance($this->TYPE, [
            $field => $realValue,
        ]);
        $this->UPDATED = $this->UPDATED + DataValidator::getResult();
    }
}