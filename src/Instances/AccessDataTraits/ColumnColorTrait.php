<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\ValidateData\DataValidator;
use function Lkt\Tools\Color\decToHex;
use function Lkt\Tools\Color\hexToDec;


trait ColumnColorTrait
{
    /**
     * @param string $field
     * @return string
     */
    protected function _getColorVal(string $field) :string
    {
        if (isset($this->UPDATED[$field])) {
            return $this->UPDATED[$field];
        }
        return trim($this->DATA[$field]);
    }

    /**
     * @param string $field
     * @return array
     */
    protected function _getColorRgbVal(string $field, float $opacity = null) :array
    {
        $r = trim($this->DATA[$field]);
        if (isset($this->UPDATED[$field])) {
            $r = trim($this->UPDATED[$field]);
        }

        $r = hexToDec($r);
        if ($opacity !== null) {
            $r[] = $opacity;
        }

        return $r;
    }

    protected function _getColorRgbStringVal(string $field, float $opacity = null) :string
    {
        $color = $this->_getColorRgbVal($field, $opacity);
        $base = 'rgb';
        if (count($color) === 4) {
            $base .= 'a';
        }

        $r = implode(',', $color);

        return "{$base}($r)";
    }

    /**
     * @param string $field
     * @return bool
     */
    protected function _hasColorVal(string $field) :bool
    {
        $checkField = 'has'.ucfirst($field);
        if (isset($this->UPDATED[$checkField])) {
            return $this->UPDATED[$checkField];
        }
        return $this->DATA[$checkField] === true;
    }

    /**
     * @param string $field
     * @param string|array|null $value
     */
    protected function _setColorVal(string $field, $value = null)
    {
        $checkField = 'has'.ucfirst($field);
        $v = $value;
        if (is_array($v)) {
            $v = decToHex($v);
        }
        DataValidator::getInstance($this->TYPE, [
            $field => $v,
        ]);
        $this->UPDATED = $this->UPDATED + DataValidator::getResult();
    }
}