<?php

namespace Lkt\Factory\Instantiator\SystemConnections;

use Lkt\Locale\Locale;

class NumberFormatter
{
    protected static \NumberFormatter|null $formatter = null;

    public static function getDecimalNumberFormatter(): \NumberFormatter
    {
        if (!is_object(static::$formatter)) {
            static::$formatter = new \NumberFormatter(Locale::getLangCode(), \NumberFormatter::DECIMAL);
        }
        return static::$formatter;
    }
}