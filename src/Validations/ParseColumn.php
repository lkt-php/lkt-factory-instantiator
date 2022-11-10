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
        if ($str === '') {
            return null;
        }
        try {
//            $date = new \DateTime($str);
//            unset($date);
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
        $value = trim($value);
        if ($value === '') {
            return null;
        }

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
            $value = json_decode($value, true);
        } elseif (is_object($value)){
            $value = json_decode(json_encode($value), true);
        } elseif (!is_array($value)){
            return null;
        }
        return $value;
    }

    /**
     * @param $value
     * @param FileField $field
     * @return File|null
     */
    public static function fileDatumToInstance($value, FileField $field):? File
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $directory = new Directory(FileSystemConnection::getDiskDriver(), $field->getStorePath());
        return new File(FileSystemConnection::getDiskDriver(), $directory, $value);
    }
}