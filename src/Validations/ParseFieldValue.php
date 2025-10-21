<?php

namespace Lkt\Factory\Instantiator\Validations;


use Carbon\Carbon;
use chillerlan\Filereader\File;
use Lkt\Factory\Schemas\Fields\AbstractField;
use Lkt\Factory\Schemas\Fields\BooleanField;
use Lkt\Factory\Schemas\Fields\ColorField;
use Lkt\Factory\Schemas\Fields\DateTimeField;
use Lkt\Factory\Schemas\Fields\EmailField;
use Lkt\Factory\Schemas\Fields\EncryptField;
use Lkt\Factory\Schemas\Fields\FileField;
use Lkt\Factory\Schemas\Fields\FloatField;
use Lkt\Factory\Schemas\Fields\ForeignKeysField;
use Lkt\Factory\Schemas\Fields\HTMLField;
use Lkt\Factory\Schemas\Fields\IntegerField;
use Lkt\Factory\Schemas\Fields\JSONField;
use Lkt\Factory\Schemas\Fields\StringField;
use Lkt\Factory\Schemas\Fields\UnixTimeStampField;

class ParseFieldValue
{
    /**
     * @param AbstractField $field
     * @param $value
     * @return array|bool|Carbon|File|float|int|string|null
     */
    public static function parse(AbstractField $field, $value = null, $instance = null)
    {
        if ($field instanceof HTMLField) return ParseColumn::HTMLDatumToInstance($value);

        if ($field instanceof ValueListField) return ParseColumn::HTMLDatumToInstance($value);

        if ($field instanceof StringField
            || $field instanceof EmailField
            || $field instanceof ColorField
            || $field instanceof EncryptField
            || $field instanceof ForeignKeysField) return ParseColumn::stringDatum($value);

        if ($field instanceof BooleanField) return ParseColumn::booleanDatum($value);

        if ($field instanceof IntegerField && $field->isMultiple()) return ParseColumn::integerArrayDatum($value);
        if ($field instanceof IntegerField) return ParseColumn::integerDatum($value);

        if ($field instanceof FloatField) return ParseColumn::floatDatum($value);

        if ($field instanceof UnixTimeStampField) return ParseColumn::unixTimeStampDatum($value);

        if ($field instanceof DateTimeField) return ParseColumn::dateTimeDatum($value);

        if ($field instanceof JSONField && !$field->isI18nJson()) return ParseColumn::JSONDatumToInstance($value);
        elseif ($field instanceof JSONField && $field->isI18nJson()) return $value;

        if ($field instanceof FileField && $field->isMultiple()) return ParseColumn::multipleFileDatumToInstance($value, $field, $instance);
        elseif($field instanceof FileField) return ParseColumn::fileDatumToInstance($value, $field, $instance);

        return null;
    }
}