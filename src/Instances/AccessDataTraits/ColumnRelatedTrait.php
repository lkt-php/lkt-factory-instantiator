<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Connectors\DatabaseConnector;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\RelatedField;
use Lkt\Factory\Schemas\Schema;
use Lkt\QueryBuilding\Query;
use Lkt\QueryBuilding\Where;
use function Lkt\Tools\Arrays\implodeWithOR;
use function Lkt\Tools\Pagination\getTotalPages;

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

        $caller = $this->_getRelatedQueryCaller($type, $column, $forceRefresh);

        $data = $caller->select();
        $relatedSchema = Schema::get($field->getComponent());

        $results = Instantiator::makeResults($relatedSchema->getComponent(), $data);

        $this->RELATED_DATA[$column] = $results;
        return $this->RELATED_DATA[$column];
    }

    /**
     * @param string $type
     * @param $column
     * @param $forceRefresh
     * @return null|\Lkt\Factory\Instantiator\Instances\AbstractInstance
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedValSingle(string $type = '', $column = '', $forceRefresh = false)
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
            return null;
        }

        $caller = $this->_getRelatedQueryCaller($type, $column, $forceRefresh);

        $data = $caller->select();
        $relatedSchema = Schema::get($field->getComponent());

        $results = Instantiator::makeResults($relatedSchema->getComponent(), $data);

        $this->RELATED_DATA[$column] = $results[0];
        return $this->RELATED_DATA[$column];
    }

    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedQueryBuilder($type = '', $column = '', $forceRefresh = false)
    {
        if (!$type) {
            return null;
        }

        $schema = Schema::get(static::GENERATED_TYPE);

        $idColumn = $schema->getIdString();
        /** @var RelatedField $field */
        $field = $schema->getField($column);

        $where = (array)$field?->getWhere();

        /**
         * @var Query $builder
         * @var DatabaseConnector $connection
         */
        list($builder, $connection) = Instantiator::getQueryCaller($field->getComponent());

        if ($field->hasMultipleReferences()){
            $temp = [];
            foreach ($field->getMultipleReferences() as $reference)  {
                $temp[] = $connection->makeUpdateParams([$reference => $this->DATA[$idColumn]]);
            }

            $where[] = '(' . implodeWithOR($temp) . ')';

        } else {
            if ($this->DATA[$idColumn]) {
                $where[] = $connection->makeUpdateParams([$field->getColumn() => $this->DATA[$idColumn]]);
            }
        }
        $order = $field->getOrder();
        if (!is_array($order)){
            $order = [];
        }

        $builder->andRaw(implode(' AND ', $where));
        $builder->orderBy(implode(',', $order));
        $builder->setForceRefresh($forceRefresh);

        if ($field->isSingleMode()) {
            $builder->pagination(1, 1);
        }

        return $builder;
    }

    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedQueryCaller($type = '', $column = '', $forceRefresh = false)
    {
        return $this->_getRelatedQueryBuilder($type, $column, $forceRefresh);
    }

    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedCustomQueryBuilder($type = '', $column = '', $forceRefresh = false)
    {
        if (!$type) {
            return null;
        }

        $schema = Schema::get(static::GENERATED_TYPE);

        $idColumn = $schema->getIdString();
        /** @var RelatedField $field */
        $field = $schema->getField($column);

        $where = $field->getWhere();

        /**
         * @var Query $builder
         * @var DatabaseConnector $connection
         */
        list($builder, $connection) = Instantiator::getCustomQueryCaller($field->getComponent());

        if ($field->hasMultipleReferences()){
            $temp = [];
            foreach ($field->getMultipleReferences() as $reference)  {
                $temp[] = $connection->makeUpdateParams([$reference => $this->DATA[$idColumn]]);
            }

            $where[] = '(' . implodeWithOR($temp) . ')';

        } else {
            if ($this->DATA[$idColumn]) {
                $where[] = $connection->makeUpdateParams([$field->getColumn() => $this->DATA[$idColumn]]);
            }
        }
        $order = $field->getOrder();
        if (!is_array($order)){
            $order = [];
        }

        $builder->andRaw(implode(' AND ', $where));
        $builder->orderBy(implode(',', $order));
        $builder->setForceRefresh($forceRefresh);

        if ($field->isSingleMode()) {
            $builder->pagination(1, 1);
        }

        return $builder;
    }

    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedCustomQueryCaller($type = '', $column = '', $forceRefresh = false)
    {
        return $this->_getRelatedCustomQueryBuilder($type, $column, $forceRefresh);
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

    protected function _getRelatedPage(string $type, string $fieldName, int $page = 1, Where $where = null)
    {
        if ($this->hasPageLoaded($fieldName, $page)) {
            return $this->PAGES[$fieldName][$page];
        }

        $schema = Schema::get(static::GENERATED_TYPE);

        /** @var RelatedField $field */
        $field = $schema->getField($fieldName);

        $caller = $this->_getRelatedQueryCaller($type, $fieldName);
        $caller->pagination($page, $field->getItemsPerPage());

        if ($where instanceof Where) {
            $caller->andWhere($where);
        }

        $data = $caller->select();
        $relatedSchema = Schema::get($field->getComponent());

        $results = Instantiator::makeResults($relatedSchema->getComponent(), $data);

        $this->PAGES[$fieldName][$page] = $results;
        return $this->PAGES[$fieldName][$page];
    }

    protected function _getRelatedCount(string $type, string $fieldName, string $countableField = '', Where $where = null)
    {
        if ($this->hasPageTotal($fieldName)) {
            return $this->PAGES_TOTAL[$fieldName];
        }

        $schema = Schema::get(static::GENERATED_TYPE);

        /** @var RelatedField $field */
        $field = $schema->getField($fieldName);

        if (!$countableField) {
            $countableField = $field->getCountableField();
        }

        if (!$countableField) {
            $relatedSchema = Schema::get($type);
            $countableField = $relatedSchema->getIdString();
        }

        $caller = $this->_getRelatedQueryCaller($type, $fieldName);

        if ($where instanceof Where) {
            $caller->andWhere($where);
        }

        $this->PAGES_TOTAL[$fieldName] = $caller->count($countableField);
        return $this->PAGES_TOTAL[$fieldName];
    }

    protected function _getRelatedAmountOfPages(string $type, string $fieldName, string $countableField = '', Where $where = null)
    {
        $schema = Schema::get(static::GENERATED_TYPE);

        /** @var RelatedField $field */
        $field = $schema->getField($fieldName);

        return getTotalPages($this->_getRelatedCount($type, $fieldName, $countableField, $where), $field->getItemsPerPage());
    }
}