<?php

namespace Lkt\Factory\Instantiator;

use Lkt\Connectors\DatabaseConnections;
use Lkt\Factory\Instantiator\Cache\InstanceCache;
use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Instantiator\Instances\AbstractInstance;
use Lkt\Factory\Instantiator\Process\ProcessQueryCallerData;
use Lkt\Factory\Instantiator\ValueObjects\ComponentDatabaseIntegration;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Schema;
use Lkt\QueryBuilding\Query;
use Lkt\QueryBuilding\Where;
use function Lkt\Tools\Arrays\compareArrays;

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

                $r = new $appClass($component, $itemData);
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
        $caller = Query::table($schema->getTable());

        $connector = $schema->getDatabaseConnector();
        if ($connector === '') $connector = DatabaseConnections::$defaultConnector;
        $connection = DatabaseConnections::get($connector);
        $caller->setColumns($connection->extractSchemaColumns($schema));

        return [$caller, $connection, $schema, $connector];
    }

    public static function getCustomQueryCaller(string $component, array $data = null, array $processRules = null, array $filterRules = null): array
    {
        $schema = Schema::get($component);
        if ($schema->getInstanceSettings()->getQueryCallerClassName() !== '') {
            $fqdn = $schema->getInstanceSettings()->getQueryCallerFQDN();
            $builder = call_user_func_array([$fqdn, 'getCaller'], []);

        } else {
            $builder = Query::table($schema->getTable());
        }

        static::filterQueryCaller($component, $builder, $data, $processRules, $filterRules);

        $connector = $schema->getDatabaseConnector();
        if ($connector === '') {
            $connector = DatabaseConnections::$defaultConnector;
        }
        $connection = DatabaseConnections::get($connector);
        $builder->setColumns($connection->extractSchemaColumns($schema));

        return [$builder, $connection, $schema, $connector];
    }

    public static function getCustomWhere(string $component): Where
    {
        $schema = Schema::get($component);
        if ($schema->getInstanceSettings()->getWhereClassName() !== '') {
            $fqdn = $schema->getInstanceSettings()->getWhereFQDN();
            $where = call_user_func_array([$fqdn, 'getEmpty'], []);

        } else {
            $where = Where::getEmpty();
        }
        $where->setTable($schema->getTable());
        return $where;
    }

    public static function prepareQueryCaller(string $component, Query &$caller): void
    {
        $schema = Schema::get($component);
        $connector = $schema->getDatabaseConnector();
        if ($connector === '') $connector = DatabaseConnections::$defaultConnector;
        $connection = DatabaseConnections::get($connector);
        $caller->setColumns($connection->extractSchemaColumns($schema));
    }

    public static function filterQueryCaller(string $component, Query &$caller, array $data = null, array $processRules = null, array $filterRules = null)
    {
        $processor = new ProcessQueryCallerData($component, $caller, $data, $processRules, $filterRules);
        $processor->process();
    }

    public static function updateRelatedIds(string $component, array $currentIds, array $updatedIds, array $updatedInstancesData): void
    {
        $diff = compareArrays($currentIds, $updatedIds);

        foreach ($diff['deleted'] as $id) {
            $instance = static::make($component, $id);
            if (method_exists($instance, 'doDelete')) {
                $instance->doDelete();
            } else {
                $instance->delete();
            }
        }

        foreach ($updatedInstancesData as $id => $item) {
            $instance = Instantiator::make($component, $id);
            $instance::feedInstance($instance, $item);
            $instance->save();
        }
    }
}