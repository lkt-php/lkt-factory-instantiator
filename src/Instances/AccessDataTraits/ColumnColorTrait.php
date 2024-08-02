<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use function Lkt\Tools\Color\decToHex;
use function Lkt\Tools\Color\hexToDec;

trait ColumnColorTrait
{
    /**
     * @param string $fieldName
     * @return string
     */
    protected function _getColorVal(string $fieldName): string
    {
        if (isset($this->UPDATED[$fieldName])) {
            return $this->UPDATED[$fieldName];
        }
        return trim($this->DATA[$fieldName]);
    }

    /**
     * @param string $fieldName
     * @param float|null $opacity
     * @return array
     */
    protected function _getColorRgbVal(string $fieldName, float $opacity = null): array
    {
        $r = trim($this->DATA[$fieldName]);
        if (isset($this->UPDATED[$fieldName])) {
            $r = trim($this->UPDATED[$fieldName]);
        }

        $r = hexToDec($r);
        if ($opacity !== null) {
            $r[] = $opacity;
        }

        return $r;
    }

    /**
     * @param string $fieldName
     * @param float|null $opacity
     * @return string
     */
    protected function _getColorRgbStringVal(string $fieldName, float $opacity = null): string
    {
        $color = $this->_getColorRgbVal($fieldName, $opacity);
        $base = 'rgb';
        if (count($color) === 4) {
            $base .= 'a';
        }

        $r = implode(',', $color);

        return "{$base}($r)";
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    protected function _hasColorVal(string $fieldName): bool
    {
        $checkField = 'has' . ucfirst($fieldName);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    /**
     * @param string $fieldName
     * @param $value
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _setColorVal(string $fieldName, $value = null): static
    {
        $v = $value;
        if (is_array($v)) {
            $v = decToHex($v);
        }
        $converter = new RawResultsToInstanceConverter(static::COMPONENT, [
            $fieldName => $v,
        ], false);

        foreach ($converter->parse() as $key => $value) {
            $this->UPDATED[$key] = $value;
        }
        return $this;
    }
}