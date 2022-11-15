<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\DatabaseConnectors\DatabaseConnections;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Schemas\Fields\RelatedKeysField;
use Lkt\Factory\Schemas\Schema;
use Lkt\QueryBuilding\Where;
use Lkt\QueryCaller\QueryCaller;

trait ColumnRelatedKeysTrait
{
    /**
     * @param string $type
     * @param string $column
     * @param bool $forceRefresh
     * @return array
     */
    protected function _getRelatedKeysVal($type = '', $column = '', $forceRefresh = false): array
    {
        if (isset($this->UPDATED_RELATED_DATA[$column])) {
            return $this->UPDATED_RELATED_DATA[$column];
        }

        if (isset($this->RELATED_DATA[$column])) {
            return $this->RELATED_DATA[$column];
        }

        $schema = Schema::get(static::GENERATED_TYPE);
        /** @var RelatedKeysField $field */
        $field = $schema->getField($column);
        $caller = $this->_getRelatedKeysInstanceFactory($type, $column, $forceRefresh);

        $data = $caller->select();
        $relatedSchema = Schema::get($field->getComponent());

        $results = Instantiator::makeResults($relatedSchema->getComponent(), $data);

        $this->RELATED_DATA[$column] = $results;
        return $this->RELATED_DATA[$column];
    }

    protected function _getRelatedKeysInstanceFactory($type = '', $column = '', $forceRefresh = false)
    {
        if (!$type) {
            return null;
        }

        $schema = Schema::get(static::GENERATED_TYPE);
        $idColumn = $schema->getIdentifiers()[0]->getColumn();

        /** @var RelatedKeysField $field */
        $field = $schema->getField($column);
        $column = $field->getColumn();
        $where = $field->getWhere();
        if (!is_array($where)) {
            $where = [];
        }

        $constraints = [];
        $constraints[] = "{$column} LIKE '%;{$this->DATA[$idColumn]};%'";
        $constraints[] = "{$column} LIKE '{$this->DATA[$idColumn]};%'";
        $constraints[] = "{$column} LIKE '%;{$this->DATA[$idColumn]}'";
        $constraints[] = "{$column} LIKE '{$this->DATA[$idColumn]}'";

        $where[] = implode(' OR ', $constraints);
        $whereString = '';

        if (count($where) > 0){
            $whereString = '(' . implode(') AND (', $where) . ')';
        }

        $order = $field->getOrder();
        if (!is_array($order)) {
            $order = [];
        }

        $relatedSchema = Schema::get($field->getComponent());
        $caller = QueryCaller::table($relatedSchema->getTable());
        $connector = $schema->getDatabaseConnector();
        if ($connector === '') {
            $connector = DatabaseConnections::$defaultConnector;
        }
        $connection = DatabaseConnections::get($connector);
        $caller->setColumns($connection->extractSchemaColumns($relatedSchema));

        $caller->where(Where::raw($whereString));
        $caller->orderBy(implode(',', $order));
        $caller->setForceRefresh($forceRefresh);
        return $caller;

        return InstanceFactory::getInstance($type)
            ->where($whereString)
            ->orderBy(implode(',', $order))
            ->forceRefresh($forceRefresh);
    }

    /**
     * @param string $type
     * @param string $column
     * @return bool
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
            $instance->hydrate($datum);
            $r[] = $instance;
        }

        $this->UPDATED_RELATED_DATA[$column] = $r;
        return $this;
    }
}