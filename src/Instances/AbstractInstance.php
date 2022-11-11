<?php

namespace Lkt\Factory\Instantiator\Instances;

use Lkt\DatabaseConnectors\DatabaseConnections;
use Lkt\Factory\Instantiator\Cache\InstanceCache;
use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Schemas\Schema;
use Lkt\QueryCaller\QueryCaller;

abstract class AbstractInstance
{
    protected $TYPE;
    protected $DATA = [];
    protected $UPDATED = [];
    protected $PIVOT = [];
    protected $PIVOT_DATA = [];
    protected $UPDATED_PIVOT_DATA = [];
    protected $RELATED_DATA = [];
    protected $UPDATED_RELATED_DATA = [];
    protected $PENDING_UPDATE_RELATED_DATA = [];
    const GENERATED_TYPE = '';

    /**
     * @param $id
     * @param string|null $component
     * @param array $initialData
     */
    public function __construct($id = 0, string $component = null, array $initialData = [])
    {
        if (!$component && static::GENERATED_TYPE) {
            $component = static::GENERATED_TYPE;
        }
        $this->TYPE = $component;
        $this->DATA = $initialData;
    }

    /**
     * @todo: remove this after update code generation for constructor
     * @param array $initialData
     * @return $this
     */
    public function setData(array $initialData): self
    {
        $this->DATA = $initialData;
        return $this;
    }

    /**
     * @param $id
     * @param string $component
     * @param array $initialData
     * @return static
     * @throws \Lkt\Factory\Schemas\Exceptions\InvalidComponentException
     * @throws \Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException
     */
    public static function getInstance($id = null, string $component = self::GENERATED_TYPE, array $initialData = []): self
    {
        if (!$component) {
            $component = static::GENERATED_TYPE;
        }
        if (!$id || !$component) {
            return new static();
        }
        $code = Instantiator::getInstanceCode($component, $id);

        if (InstanceCache::inCache($code)) {
            return InstanceCache::load($code);
        }

        if (count($initialData) > 0) {
            $r = new static($id, $component, $initialData);
            $r->setData($initialData);
            InstanceCache::store($code, $r);
            return InstanceCache::load($code);
        }

        $schema = Schema::get($component);
        $identifiers = $schema->getIdentifiers();

        $caller = QueryCaller::table($schema->getTable());
        $connector = $schema->getDatabaseConnector();
        if (!$connector) {
            $connector = DatabaseConnections::$defaultConnector;
        }
        $caller->setDatabaseConnector($connector);
        $caller->extractSchemaColumns($schema);

        foreach ($identifiers as $identifier) {
            $caller->andIntegerEqual($identifier->getColumn(), $id);
        }

        $data = $caller->select();
        if (count($data) > 0) {
            $converter = new RawResultsToInstanceConverter($component, $data[0]);
            $itemData = $converter->parse();

            $r = new static($id, $component, $itemData);
            $r->setData($itemData);
            InstanceCache::store($code, $r);
            return InstanceCache::load($code);
        }

        return new static();
    }
}