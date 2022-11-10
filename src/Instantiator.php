<?php

namespace Lkt\Factory\Instantiator;

use Lkt\Factory\Instantiator\Cache\InstanceCache;
use Lkt\Factory\Schemas\Schema;

class Instantiator
{
    /**
     * @param string $component
     * @param $id
     * @return string
     */
    public static function getInstanceCode(string $component, $id): string
    {
        return "{$component}_{$id}";
    }


    public static function make(string $component, $id, array $data = [])
    {
        $code = static::getInstanceCode($component, $id);

        if (InstanceCache::inCache($code)) {
            return InstanceCache::load($code);
        }

        $schema = Schema::get($component);

        $callable = [
            $schema->getInstanceSettings()->getAppClass(),
            'getInstance'
        ];

        return call_user_func_array($callable, [$id, $component, $data]);
    }
}