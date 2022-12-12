<?php

namespace Lkt\Factory\Instantiator;

use Lkt\DatabaseConnectors\DatabaseConnections;
use Lkt\Factory\Instantiator\Cache\InstanceCache;
use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Instantiator\Instances\AbstractInstance;
use Lkt\Factory\Instantiator\Process\ProcessQueryCallerData;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Schema;
use Lkt\QueryCaller\QueryCaller;

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

    /**
     * @param string $component
     * @param array $results
     * @return AbstractInstance[]
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     * @throws \Lkt\Factory\Schemas\Exceptions\InvalidComponentException
     */
    public static function makeResults(string $component, array $results): array
    {
        /** @var AbstractInstance[] $response */
        $response = [];
        $schema = Schema::get($component);
        $appClass = $schema->getInstanceSettings()->getAppClass();

        if (count($results) > 0) {

            $relatedIdentifiers = $schema->getIdentifiers();
            $identifier = $relatedIdentifiers[0];

            foreach ($results as $item) {
                $itemId = $item[$identifier->getName()];


                $converter = new RawResultsToInstanceConverter($component, $item);
                $itemData = $converter->parse();

                $r = new $appClass($itemId, $component, $itemData);
                $r->setData($itemData);
                $code = Instantiator::getInstanceCode($component, $itemId);
                InstanceCache::store($code, $r);
                $response[] = $r;
            }
        }

        return $response;
    }

    /**
     * @param string $component
     * @return array[$caller, $connection, $schema, $connector]
     * @throws SchemaNotDefinedException
     */
    public static function getQueryCaller(string $component): array
    {
        $schema = Schema::get($component);
        $caller = QueryCaller::table($schema->getTable());

        $connector = $schema->getDatabaseConnector();
        if ($connector === '') {
            $connector = DatabaseConnections::$defaultConnector;
        }
        $connection = DatabaseConnections::get($connector);
        $caller->setColumns($connection->extractSchemaColumns($schema));

        return [$caller, $connection, $schema, $connector];
    }

    public static function getCustomQueryCaller(string $component, array $data = null, array $processRules = null, array $filterRules = null): array
    {
        $schema = Schema::get($component);
        if ($schema->getInstanceSettings()->getQueryCallerClassName() !== '') {
            $fqdn = $schema->getInstanceSettings()->getQueryCallerFQDN();
            $caller = call_user_func_array([$fqdn, 'getCaller'], []);

        } else {
            $caller = QueryCaller::table($schema->getTable());
        }

        static::filterQueryCaller($component, $caller, $data, $processRules, $filterRules);

        $connector = $schema->getDatabaseConnector();
        if ($connector === '') {
            $connector = DatabaseConnections::$defaultConnector;
        }
        $connection = DatabaseConnections::get($connector);
        $caller->setColumns($connection->extractSchemaColumns($schema));

        return [$caller, $connection, $schema, $connector];
    }

    public static function prepareQueryCaller(string $component, QueryCaller &$caller): void
    {
        $schema = Schema::get($component);
        $connector = $schema->getDatabaseConnector();
        if ($connector === '') {
            $connector = DatabaseConnections::$defaultConnector;
        }
        $connection = DatabaseConnections::get($connector);
        $caller->setColumns($connection->extractSchemaColumns($schema));
    }

    public static function filterQueryCaller(string $component, QueryCaller &$caller, array $data = null, array $processRules = null, array $filterRules = null)
    {
        $processor = new ProcessQueryCallerData($component, $caller, $data, $processRules, $filterRules);
        $processor->process();
    }
}