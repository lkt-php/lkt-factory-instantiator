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
        $status = false;

        if ($field instanceof HTMLField) {
            $status = trim($value) !== '';
        }

        if ($field instanceof StringField
            || $field instanceof EmailField
            || $field instanceof ColorField
            || $field instanceof ForeignKeysField) {
            $status = trim($value) !== '';
        }

        if ($field instanceof BooleanField) {
            $status = (bool)$value === true;
        }

        if ($field instanceof IntegerField) {
            $status = (int)$value > 0;
        }

        if ($field instanceof FloatField) {
            $status = (float)$value > 0;
        }

        if ($field instanceof UnixTimeStampField) {
            $status = $value !== null;
        }

        if ($field instanceof DateTimeField) {
            $status = $value !== null;
        }

        if ($field instanceof JSONField) {
            $status = $value !== null;
        }

        if ($field instanceof FileField) {
            $status = $value !== null;
        }
        return $status;
    }
}