<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Connectors\DatabaseConnections;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\RelatedKeysField;
use Lkt\Factory\Schemas\Schema;
use Lkt\QueryBuilding\Query;
use Lkt\QueryBuilding\Where;

trait ColumnRelatedKeysTrait
{
    /**
     * @param $type
     * @param $column
     * @param $forceRefresh
     * @return array
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedKeysVal($type = '', $column = '', $forceRefresh = false): array
    {
        if (isset($this->UPDATED_RELATED_DATA[$column])) {
            return $this->UPDATED_RELATED_DATA[$column];
        }

        if (isset($this->RELATED_DATA[$column])) {
            return $this->RELATED_DATA[$column];
        }

        $schema = Schema::get(static::COMPONENT);
        /** @var RelatedKeysField $field */
        $field = $schema->getField($column);
        $caller = $this->_getRelatedKeysQueryCaller($type, $column, $forceRefresh);

        $data = $caller->select();
        $relatedSchema = Schema::get($field->getComponent());

        $results = Instantiator::makeResults($relatedSchema->getComponent(), $data);

        $this->RELATED_DATA[$column] = $results;
        return $this->RELATED_DATA[$column];
    }

    /**
     * @param $type
     * @param $column
     * @param $forceRefresh
     * @return Query|null
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedKeysQueryBuilder($type = '', $column = '', $forceRefresh = false)
    {
        if (!$type) {
            return null;
        }

        $schema = Schema::get(static::COMPONENT);
        $idColumn = $schema->getIdentifiers()[0]->getColumn();

        /** @var RelatedKeysField $field */
        $field = $schema->getField($column);
        $column = $field->getColumn();
        $where = $field->getWhere();

        $constraints = [];
        $constraints[] = "{$column} LIKE '%;{$this->DATA[$idColumn]};%'";
        $constraints[] = "{$column} LIKE '{$this->DATA[$idColumn]};%'";
        $constraints[] = "{$column} LIKE '%;{$this->DATA[$idColumn]}'";
        $constraints[] = "{$column} LIKE '{$this->DATA[$idColumn]}'";

        $where[] = implode(' OR ', $constraints);
        $whereString = '';

        if (count($where) > 0) {
            $whereString = '(' . implode(') AND (', $where) . ')';
        }

        $order = $field->getOrder();

        $relatedSchema = Schema::get($field->getComponent());
        $builder = Query::table($relatedSchema->getTable());
        $connector = $schema->getDatabaseConnector();
        if ($connector === '') {
            $connector = DatabaseConnections::$defaultConnector;
        }
        $connection = DatabaseConnections::get($connector);
        $builder->setColumns($connection->extractSchemaColumns($relatedSchema));

        $builder->where(Where::raw($whereString));
        $builder->orderBy(implode(',', $order));
        $builder->setForceRefresh($forceRefresh);
        return $builder;
    }

    /**
     * @param $type
     * @param $column
     * @param $forceRefresh
     * @return Query|null
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedKeysQueryCaller($type = '', $column = '', $forceRefresh = false)
    {
        return $this->_getRelatedKeysQueryBuilder($type, $column, $forceRefresh);
    }

    /**
     * @param $type
     * @param $column
     * @return bool
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    protected function _hasRelatedKeysVal($type = '', $column = ''): bool
    {
        return count($this->_getRelatedKeysVal($type)) > 0;
    }

    protected function _setRelatedKeysValWithData($type = '', $column = '', $data = [])
    {
        $this->PENDING_UPDATE_RELATED_DATA[$column] = $data;

        $schema = Schema::get($type);
        /** @var RelatedKeysField $field */
        $field = $schema->getField($column);

        $relatedSchema = Schema::get($field->getComponent());

        $relatedIdColumn = $relatedSchema->getIdColumn()[0];
        $relatedClass = $relatedSchema->getInstanceSettings()->getAppClass();

        $r = [];

        foreach ($data as $datum) {
            $instance = $relatedClass::getInstance($datum[$relatedIdColumn]);
            $instance::feedInstance($instance, $datum);
            $r[] = $instance;
        }

        $this->UPDATED_RELATED_DATA[$column] = $r;
        return $this;
    }
}