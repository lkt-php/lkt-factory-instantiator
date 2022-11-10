<?php

namespace Lkt\Factory\Instantiator\Instances;

use Lkt\Factory\Instantiator\Cache\InstanceCache;
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
    const GENERATED_TYPE = null;

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
     * @param $id
     * @param string|null $component
     * @param array $initialData
     * @return static
     * @throws \Lkt\Factory\Schemas\Exceptions\InvalidComponentException
     * @throws \Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException
     */
    public static function getInstance($id = null, string $component = null, array $initialData = []): self
    {
        if (!$id) {
            return new static();
        }
        $code = Instantiator::getInstanceCode($component, $id);

        if (InstanceCache::inCache($code)) {
            return InstanceCache::load($code);
        }

        if (count($initialData) > 0) {
            $r = new static($id, $component, $initialData);
            InstanceCache::store($code, $r);
            return InstanceCache::load($code);
        }

        $schema = Schema::get($component);
        $identifiers = $schema->getIdentifiers();

        $caller = QueryCaller::table($schema->getTable());
        $caller->setDatabaseConnector($schema->getDatabaseConnector());
        $caller->extractSchemaColumns($schema);

        foreach ($identifiers as $identifier) {
            $caller->andIntegerEqual($identifier->getColumn(), $id);
        }

        $data = $caller->select();
        if (count($data) > 0) {
            $r = new static($id, $component, $data[0]);
            InstanceCache::store($code, $r);
            return InstanceCache::load($code);
        }

        return new static();
    }
}