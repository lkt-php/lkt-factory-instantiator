<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Carbon\Carbon;
use DateTime;
use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;

trait ColumnDateTimeTrait
{
    /**
     * @param string $fieldName
     * @return Carbon|null
     */
    protected function _getDateTimeVal(string $fieldName): ?Carbon
    {
        if (isset($this->UPDATED[$fieldName]) && $this->UPDATED[$fieldName] instanceof Carbon) {
            return $this->UPDATED[$fieldName];
        }
        if ($this->DATA[$fieldName] instanceof Carbon) {
            return $this->DATA[$fieldName];
        }
        return null;
    }

    /**
     * @param string $fieldName
     * @param string|null $format
     * @return string
     */
    protected function _getDateTimeFormattedVal(string $fieldName, string $format = null): string
    {
        if (!$this->_hasDateTimeVal($fieldName)) {
            return '';
        }
        $r = $this->_getDateTimeVal($fieldName)->format($format);
        if (str_starts_with($r, '-')) return '';
        return $r;
    }

    /**
     * @param string $fieldName
     * @param string|null $format
     * @return string
     */
    protected function _getDateTimeFormattedIntlVal(string $fieldName, string $format = null): string
    {
        if (!$this->_hasDateTimeVal($fieldName)) {
            return '';
        }
        return \IntlDateFormatter::formatObject($this->_getDateTimeVal($fieldName), $format);
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    protected function _hasDateTimeVal(string $fieldName): bool
    {
        $checkField = 'has' . ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    /**
     * @throws \Lkt\Factory\Schemas\Exceptions\InvalidComponentException
     * @throws \Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException
     */
    protected function _setDateTimeVal(string $fieldName, Carbon|DateTime|int|string|null $value = null): static
    {
        $raeValueToConvert = null;
        if ($value instanceof Carbon) {
            $raeValueToConvert = $value->format('Y-m-d H:i:s');

        } elseif ($value instanceof DateTime) {
            $raeValueToConvert = $value->format('Y-m-d H:i:s');

        } elseif (is_string($value)) {
            $raeValueToConvert = $value;

        } elseif (is_int($value)) {
            $raeValueToConvert = date('Y-m-d H:i:s', $value);
        }

        $converter = new RawResultsToInstanceConverter(static::COMPONENT, [
            $fieldName => $raeValueToConvert,
        ], false);

        foreach ($converter->parse() as $key => $value) {
            $this->UPDATED[$key] = $value;
        }
        return $this;
    }
}