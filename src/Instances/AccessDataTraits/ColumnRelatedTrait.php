<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\DatabaseConnectors\DatabaseConnections;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\RelatedField;
use Lkt\Factory\Schemas\Schema;
use Lkt\QueryBuilding\Where;
use Lkt\QueryCaller\QueryCaller;

trait ColumnRelatedTrait
{
    /**
     * @param string $type
     * @param $column
     * @param $forceRefresh
     * @return array
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedVal(string $type = '', $column = '', $forceRefresh = false) :array
    {
        if (!$forceRefresh && isset($this->UPDATED_RELATED_DATA[$column])) {
            return $this->UPDATED_RELATED_DATA[$column];
        }

        if (!$forceRefresh && isset($this->RELATED_DATA[$column])) {
            return $this->RELATED_DATA[$column];
        }

        $schema = Schema::get(static::GENERATED_TYPE);
        /** @var RelatedField $field */
        $field = $schema->getField($column);

        $idColumn = $schema->getIdString();
        if (!$this->DATA[$idColumn]) {
            return [];
        }

        $caller = $this->_getRelatedInstanceFactory($type, $column, $forceRefresh);

        $data = $caller->select();
        $relatedSchema = Schema::get($field->getComponent());

        $results = Instantiator::makeResults($relatedSchema->getComponent(), $data);

        $this->RELATED_DATA[$column] = $results;
        return $this->RELATED_DATA[$column];
    }

    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedInstanceFactory($type = '', $column = '', $forceRefresh = false)
    {
        if (!$type) {
            return null;
        }

        $schema = Schema::get(static::GENERATED_TYPE);

        $idColumn = $schema->getIdString();
        /** @var RelatedField $field */
        $field = $schema->getField($column);

        $where = $field->getWhere();
        if (!is_array($where)){
            $where = [];
        }

        $relatedSchema = Schema::get($field->getComponent());
        $caller = QueryCaller::table($relatedSchema->getTable());

        if ($this->DATA[$idColumn]) {
            $connector = $schema->getDatabaseConnector();
            if ($connector === '') {
                $connector = DatabaseConnections::$defaultConnector;
            }
            $connection = DatabaseConnections::get($connector);
            $where[] = $connection->makeUpdateParams([$field->getColumn() => $this->DATA[$idColumn]]);
            $caller->setColumns($connection->extractSchemaColumns($relatedSchema));
        }

        $order = $field->getOrder();
        if (!is_array($order)){
            $order = [];
        }

        $caller->where(Where::raw(implode(' AND ', $where)));
        $caller->orderBy(implode(',', $order));
        $caller->setForceRefresh($forceRefresh);

        return $caller;


    }

    /**
     * @param string $type
     * @param string $column
     * @return bool
     */
    protected function _hasRelatedVal($type = '', $column = '') :bool
    {
        return count($this->_getRelatedVal($type)) > 0;
    }

    /**
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    protected function _setRelatedValWithData($type = '', $column = '', $data = [])
    {
        $this->PENDING_UPDATE_RELATED_DATA[$column] = $data;

        $schema = Schema::get($type);
        $relatedIdColumn = $schema->getIdColumn();

        $relatedClass = $schema->getInstanceSettings()->getAppClass();

        $r = [];

        foreach ($data as $datum){
            $instance = call_user_func_array([$relatedClass, 'getInstance'], [$datum[$relatedIdColumn]]);
            $instance->hydrate($datum);
            $r[] = $instance;
        }

        $this->UPDATED_RELATED_DATA[$column] = $r;
        return $this;
    }
}