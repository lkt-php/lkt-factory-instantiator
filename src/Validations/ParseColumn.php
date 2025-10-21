<?php

namespace Lkt\Factory\Instantiator\Validations;

use Carbon\Carbon;
use chillerlan\Filereader\Directory;
use chillerlan\Filereader\File;
use Lkt\Factory\Instantiator\SystemConnections\FileSystemConnection;
use Lkt\Factory\Schemas\Fields\FileField;

class ParseColumn
{
    /**
     * @param $value
     * @return bool
     */
    public static function booleanDatum($value): bool
    {
        return (int)$value === 1;
    }

    /**
     * @param $value
     * @return string
     */
    public static function stringDatum($value): string
    {
        if (is_null($value)) $value = '';
        return trim($value);
    }

    /**
     * @param $value
     * @return string
     */
    public static function HTMLDatumToInstance($value): string
    {
        $value = str_replace(':LKT_SLASH:', '\\', $value);
        $value = str_replace(':LKT_QUESTION_MARK:', '?', $value);
        $value = str_replace(':LKT_SINGLE_QUOTE:', "'", $value);
        return trim(str_replace('\"', '"', $value));
    }

    /**
     * @param $value
     * @return string
     */
    public static function valueListToArray($value): string
    {
        $value = str_replace(':LKT_SLASH:', '\\', $value);
        $value = str_replace(':LKT_QUESTION_MARK:', '?', $value);
        $value = str_replace(':LKT_SINGLE_QUOTE:', "'", $value);
        return trim(str_replace('\"', '"', $value));
    }

    /**
     * @param $value
     * @return string
     */
    public static function HTMLDatumToDatabase($value): string
    {
        $value = str_replace('\\', ':LKT_SLASH:', $value);
        $value = str_replace('?', ':LKT_QUESTION_MARK:', $value);
        return trim(str_replace("'", ':LKT_SINGLE_QUOTE:', $value));
    }

    /**
     * @param $value
     * @return int
     */
    public static function integerDatum($value): int
    {
        return (int)$value;
    }

    /**
     * @param $value
     * @return int[]
     */
    public static function integerArrayDatum($value): array
    {
        if (is_null($value)) return [];
        if (is_string($value)) {
            $value = explode(';', $value);
        }
        if (!is_array($value)) $value = [$value];
        $r = [];
        foreach ($value as $item) {
            $r[] = (int)$item;
        }
        return $r;
    }

    /**
     * @param $value
     * @return float
     */
    public static function floatDatum($value): float
    {
        return (float)$value;
    }

    /**
     * @param $value
     * @return Carbon|null
     */
    public static function unixTimeStampDatum($value): ?Carbon
    {
        if (is_string($value)) {
            $str = trim($value);
        } else {
            $str = date('Y-m-d H:i:s', (int)$value);
        }
        if ($str === '') return null;
        try {
            return new Carbon($str);

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param $value
     * @return Carbon|null
     */
    public static function dateTimeDatum($value): ?Carbon
    {
        if (is_null($value)) {
            $value = '';
        } else {
            $value = trim($value);
        }
        if ($value === '') return null;

        try {
            $date = new \DateTime($value);
            unset($date);
            return new Carbon($value);

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param $value
     * @return array|null
     */
    public static function JSONDatumToInstance($value)
    {
        if (is_string($value)){
            $value = htmlspecialchars_decode($value);
            $value = ParseColumn::HTMLDatumToInstance($value);
            return json_decode($value, true);
        }
        if (is_object($value)) return json_decode(json_encode($value), true);
        if (!is_array($value)) return null;
        return $value;
    }

    /**
     * @param $value
     * @param FileField $field
     * @return File|null
     */
    public static function fileDatumToInstance($value, FileField $field, $instance = null):? File
    {
        $value = trim($value);
        if ($value === '') return null;

        $directory = new Directory(FileSystemConnection::getDiskDriver(), $field->getStorePath($instance));
        return new File(FileSystemConnection::getDiskDriver(), $directory, $value);
    }

    /**
     * @param $value
     * @param FileField $field
     * @return File[]
     */
    public static function multipleFileDatumToInstance($value, FileField $field, $instance = null): array
    {

        if (is_null($value)) return [];
        if (is_string($value)) {
            $value = explode(';', $value);
        }
        if (!is_array($value)) $value = [$value];
        $r = [];
        foreach ($value as $item) {
            $directory = new Directory(FileSystemConnection::getDiskDriver(), $field->getStorePath($instance));
            $r[] = new File(FileSystemConnection::getDiskDriver(), $directory, $item);
        }

        return $r;
    }
}