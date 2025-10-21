<?php

namespace Lkt\Factory\Instantiator\Validations;


use Lkt\Factory\Schemas\Fields\AbstractField;
use Lkt\Factory\Schemas\Fields\BooleanField;
use Lkt\Factory\Schemas\Fields\ColorField;
use Lkt\Factory\Schemas\Fields\DateTimeField;
use Lkt\Factory\Schemas\Fields\EmailField;
use Lkt\Factory\Schemas\Fields\FileField;
use Lkt\Factory\Schemas\Fields\FloatField;
use Lkt\Factory\Schemas\Fields\ForeignKeysField;
use Lkt\Factory\Schemas\Fields\HTMLField;
use Lkt\Factory\Schemas\Fields\IntegerField;
use Lkt\Factory\Schemas\Fields\JSONField;
use Lkt\Factory\Schemas\Fields\StringField;
use Lkt\Factory\Schemas\Fields\UnixTimeStampField;

class ValidateFieldValue
{
    /**
     * @param AbstractField $field
     * @param $value
     * @return bool
     */
    public static function validate(AbstractField $field, $value = null): bool
    {
        if ($field instanceof HTMLField) {
            if ($value === null) return false;
            return trim($value) !== '';
        }

        if ($field instanceof StringField
            || $field instanceof EmailField
            || $field instanceof ColorField
            || $field instanceof ForeignKeysField) {
            if ($value === null) return false;
            return trim($value) !== '';
        }

        if ($field instanceof BooleanField) return (bool)$value === true;

        if ($field instanceof IntegerField && $field->isMultiple()) return is_array($value) && count($value) > 0;
        else if ($field instanceof IntegerField) return (int)$value > 0;

        if ($field instanceof FloatField) return (float)$value > 0;

        if ($field instanceof UnixTimeStampField) return $value !== null;

        if ($field instanceof DateTimeField) return $value !== null;

        if ($field instanceof JSONField)  return $value !== null;

        if ($field instanceof FileField && $field->isMultiple()) return is_array($value) && count($value) > 0;
        elseif ($field instanceof FileField) return $value !== null;
        return false;
    }
}