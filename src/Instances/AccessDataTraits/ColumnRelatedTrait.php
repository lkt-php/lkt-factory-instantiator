<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Connectors\DatabaseConnector;
use Lkt\Factory\Instantiator\Helpers\QueryBuilderHelper;
use Lkt\Factory\Instantiator\Helpers\UpdatedRelatedDataProcessor;
use Lkt\Factory\Instantiator\Instances\AbstractInstance;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\ForeignKeyField;
use Lkt\Factory\Schemas\Fields\IntegerField;
use Lkt\Factory\Schemas\Fields\RelatedField;
use Lkt\Factory\Schemas\Fields\StringField;
use Lkt\Factory\Schemas\Schema;
use Lkt\QueryBuilding\Query;
use Lkt\QueryBuilding\Where;
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
    protected function _getRelatedVal(string $type = '', $column = '', $forceRefresh = false, array $additionalData = []): array
    {
        if (!$forceRefresh && isset($this->UPDATED_RELATED_DATA[$column])) {
            return $this->UPDATED_RELATED_DATA[$column];
        }

        if (!$forceRefresh && isset($this->RELATED_DATA[$column])) {
            return $this->RELATED_DATA[$column];
        }

        $schema = Schema::get(static::COMPONENT);
        /** @var RelatedField $field */
        $field = $schema->getField($column);

        $idColumn = $schema->getIdString();
        if (!$this->DATA[$idColumn]) {
            return [];
        }

        $caller = $this->_getRelatedQueryCaller($type, $column, $forceRefresh, $additionalData);

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
    protected function _getRelatedValSingle(string $type = '', $column = '', $forceRefresh = false, array $additionalData = [])
    {
        if (!$forceRefresh && isset($this->UPDATED_RELATED_DATA[$column])) {
            return $this->UPDATED_RELATED_DATA[$column];
        }

        if (!$forceRefresh && isset($this->RELATED_DATA[$column])) {
            if (is_array($this->RELATED_DATA[$column])) return $this->RELATED_DATA[$column][0];
            return $this->RELATED_DATA[$column];
        }

        $schema = Schema::get(static::COMPONENT);
        /** @var RelatedField $field */
        $field = $schema->getField($column);

        $idColumn = $schema->getIdString();
        if (!$this->DATA[$idColumn]) {
            return null;
        }

        $caller = $this->_getRelatedQueryCaller($type, $column, $forceRefresh, $additionalData);

        $data = $caller->select();
        $relatedSchema = Schema::get($field->getComponent());

        $results = Instantiator::makeResults($relatedSchema->getComponent(), $data);

        $this->RELATED_DATA[$column] = $results[0];
        return $this->RELATED_DATA[$column];
    }

    /**
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedQueryBuilder($type = '', $column = '', $forceRefresh = false, array $additionalData = [])
    {
        if (!$type) return null;

        $schema = Schema::get(static::COMPONENT);
        $field = $schema->getRelatedField($column);

        $builder = QueryBuilderHelper::getComponentQuery($field->getComponent());

        return $this->_prepareQuery($builder, $schema, $field, $forceRefresh, $additionalData);
    }

    /**
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedQueryCaller($type = '', $column = '', $forceRefresh = false, array $additionalData = [])
    {
        return $this->_getRelatedQueryBuilder($type, $column, $forceRefresh, $additionalData);
    }

    /**
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedCustomQueryBuilder($type = '', $column = '', $forceRefresh = false, array $additionalData = [])
    {
        $schema = Schema::get(static::COMPONENT);
        $field = $schema->getRelatedField($column);

        /**
         * @var Query $builder
         * @var DatabaseConnector $connection
         */
        list($builder) = Instantiator::getCustomQueryCaller($field->getComponent());

        return $this->_prepareQuery($builder, $schema, $field, $forceRefresh);
    }

    protected function _prepareQuery(Query $query, Schema $schema, RelatedField $field, $forceRefresh = false, array $additionalData = [])
    {
        $idColumn = $schema->getIdString();
        $relatedSchema = Schema::get($field->getComponent());

        $where = (array)$field?->getWhere();

        if ($relatedSchema->hasComplexPrimaryKey()) {
            $identifiers = $relatedSchema->getIdentifiers();
            $relatedField = $relatedSchema->getField($field->getColumn());
            foreach ($identifiers as $identifier) {
                $identifierName = $identifier->getName();

                if ($identifier instanceof ForeignKeyField && $additionalData[$identifierName] instanceof AbstractInstance) {

                    if ($relatedField->getColumn() === $identifier->getColumn()) {
                        $query->andIntegerEqual($relatedField->getColumn(), $this->DATA[$idColumn]);
                    } else {
                        $query->andIntegerEqual($identifier->getColumn(), (int)$additionalData[$identifierName]?->getIdColumnValue());
                    }


                }elseif ($identifier instanceof IntegerField) {

                    if ($relatedField->getColumn() === $identifier->getColumn()) {
                        $query->andIntegerEqual($relatedField->getColumn(), $this->DATA[$idColumn]);
                    } else {
                        $query->andIntegerEqual($identifier->getColumn(), $additionalData[$identifierName]);
                    }

                } elseif ($identifier instanceof StringField) {

                    if ($relatedField->getColumn() === $identifier->getColumn()) {
                        $query->andStringEqual($relatedField->getColumn(), $this->DATA[$idColumn]);
                    } else {
                        $query->andStringEqual($identifier->getColumn(), $additionalData[$identifierName]);
                    }
                }
            }

        } else {
            if ($field->hasMultipleReferences()) {
                foreach ($field->getMultipleReferences() as $reference) {
                    $relatedField = $relatedSchema->getField($reference);
                    if ($relatedField instanceof IntegerField) {
                        $query->andIntegerEqual($relatedField->getColumn(), $this->DATA[$idColumn]);

                    } elseif ($relatedField instanceof StringField) {
                        $query->andStringEqual($relatedField->getColumn(), $this->DATA[$idColumn]);
                    }
                }

            } else {
                if ($this->DATA[$idColumn]) {
                    $relatedField = $relatedSchema->getField($field->getColumn());
                    if ($relatedField instanceof IntegerField) {
                        $query->andIntegerEqual($relatedField->getColumn(), $this->DATA[$idColumn]);

                    } elseif ($relatedField instanceof StringField) {
                        $query->andStringEqual($relatedField->getColumn(), $this->DATA[$idColumn]);
                    }
                }
            }
        }

        $order = $field->getOrder();
        if (!is_array($order)) $order = [];

        if (count($where) > 0){
            $query->andRaw(implode(' AND ', $where));
        }

        $query->orderBy(implode(',', $order));
        $query->setForceRefresh($forceRefresh);

        if ($field->isSingleMode()) $query->pagination(1, 1);

        return $query;
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
    protected function _hasRelatedVal($type = '', $column = ''): bool
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
        $dataProcessor = new UpdatedRelatedDataProcessor(
            Schema::get(static::COMPONENT),
            $column,
            $data,
            $this
        );
        $dataProcessor->processRelatedField();

        $this->PENDING_UPDATE_RELATED_DATA[$column] = $dataProcessor->pendingUpdateData;
        $this->UPDATED_RELATED_DATA[$column] = $dataProcessor->updatedData;
        return $this;
    }

    protected function _getRelatedPage(string $type, string $fieldName, int $page = 1, Where $where = null)
    {
        if ($this->hasPageLoaded($fieldName, $page)) {
            return $this->PAGES[$fieldName][$page];
        }

        $schema = Schema::get(static::COMPONENT);

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

        $schema = Schema::get(static::COMPONENT);

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
        $schema = Schema::get(static::COMPONENT);

        /** @var RelatedField $field */
        $field = $schema->getField($fieldName);

        return getTotalPages($this->_getRelatedCount($type, $fieldName, $countableField, $where), $field->getItemsPerPage());
    }
}