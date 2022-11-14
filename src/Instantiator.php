<?php

namespace Lkt\Factory\Instantiator;

use Lkt\Factory\Instantiator\Cache\InstanceCache;
use Lkt\Factory\Instantiator\Instances\AbstractInstance;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
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


    /**
     * @param string $component
     * @param $id
     * @param array $data
     * @return AbstractInstance|null
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    public static function make(string $component, $id, array $data = []): ?AbstractInstance
    {
        $code = static::getInstanceCode($component, $id);

        if (InstanceCache::inCache($code)) {
            return InstanceCache::load($code);
        }

        $schema = Schema::get($component);

        $callable = [$schema->getInstanceSettings()->getAppClass(), 'getInstance'];

        /** @var AbstractInstance $r */
        $r = call_user_func_array($callable, ['id' => $id, 'component' => $component, 'initialData' => $data]);

        return $r;
    }
}