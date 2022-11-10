<?php

namespace Lkt\Factory\Instantiator\Cache;

use Lkt\Factory\Instantiator\Instances\AbstractInstance;

final class InstanceCache
{
    /** @var AbstractInstance[] */
    protected static $cache = [];

    /**
     * @param string $code
     * @param $data
     * @return int
     */
    public static function store(string $code, AbstractInstance $data): int
    {
        self::$cache[$code] = $data;
        return 1;
    }

    /**
     * @param string $code
     * @return AbstractInstance|null
     */
    public static function load(string $code):? AbstractInstance
    {
        if (self::inCache($code)) {
            return self::$cache[$code];
        }
        return null;
    }

    /**
     * @param string $code
     * @return bool
     */
    public static function inCache(string $code): bool
    {
        return isset(self::$cache[$code]);
    }

    /**
     * @return int
     */
    public static function clear(): int
    {
        self::$cache = [];
        return 1;
    }

    /**
     * @param string $code
     * @return int
     */
    public static function clearCode(string $code): int
    {
        if (self::inCache($code)) {
            unset(self::$cache[$code]);
            return 1;
        }
        return 2;
    }

    /**
     * @return array
     */
    public static function getCache(): array
    {
        return self::$cache;
    }
}