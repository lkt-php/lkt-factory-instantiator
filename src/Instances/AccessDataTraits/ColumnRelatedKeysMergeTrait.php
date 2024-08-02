<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\Instantiator\Relations\RelatedKeysMergeHelper;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\RelatedKeysMergeField;
use Lkt\Factory\Schemas\Schema;
use Lkt\QueryBuilding\Where;
use function Lkt\Tools\Pagination\getTotalPages;

trait ColumnRelatedKeysMergeTrait
{
    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedKeysMergeVal(string $column = '', bool $forceRefresh = false): array
    {
        if (!$forceRefresh && isset($this->UPDATED_RELATED_DATA[$column])) {
            return $this->UPDATED_RELATED_DATA[$column];
        }

        if (!$forceRefresh && isset($this->RELATED_DATA[$column])) {
            return $this->RELATED_DATA[$column];
        }

        $queryUnion = RelatedKeysMergeHelper::getQueryUnion(static::COMPONENT, $column, $this->getIdColumnValue());
        $results = RelatedKeysMergeHelper::getRawResultsFromQueryUnion(static::COMPONENT, $column, $queryUnion);
        $instances = RelatedKeysMergeHelper::convertRawResults($results);

        $this->RELATED_DATA[$column] = $instances;
        return $this->RELATED_DATA[$column];
    }

    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedKeysMergeRaw(string $column = '', bool $forceRefresh = false): array
    {
        $key = $column . 'Raw';

        if (!$forceRefresh && isset($this->RELATED_DATA[$key])) {
            return $this->RELATED_DATA[$key];
        }

        $queries = RelatedKeysMergeHelper::getQueryUnion(static::COMPONENT, $column, $this->getIdColumnValue());
        $results = RelatedKeysMergeHelper::getRawResultsFromQueryUnion(static::COMPONENT, $column, $queries);

        $this->RELATED_DATA[$key] = $results;
        return $this->RELATED_DATA[$key];
    }

    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _hasRelatedKeysMergeVal(string $column = ''): bool
    {
        return count($this->_getRelatedKeysMergeVal($column)) > 0;
    }

    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _hasRelatedKeysMergeRaw(string $column = ''): bool
    {
        return count($this->_getRelatedKeysMergeRaw($column)) > 0;
    }

    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedKeysMergePage(string $column, int $page = 1, Where $where = null)
    {
        if ($this->hasPageLoaded($column, $page)) {
            return $this->PAGES[$column][$page];
        }

        $schema = Schema::get(static::COMPONENT);

        $field = $schema->getField($column);

        $queryUnion = RelatedKeysMergeHelper::getQueryUnion(static::COMPONENT, $column, $this->getIdColumnValue());
        $queryUnion->pagination($page, $field->getItemsPerPage());
        $results = RelatedKeysMergeHelper::getRawResultsFromQueryUnion(static::COMPONENT, $column, $queryUnion);
        $instances = RelatedKeysMergeHelper::convertRawResults($results);

        $this->PAGES[$column][$page] = $instances;
        return $this->PAGES[$column][$page];
    }

    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedKeysMergeRawPage(string $column, int $page = 1, Where $where = null)
    {
        $key = $column . 'Raw';
        if ($this->hasPageLoaded($key, $page)) {
            return $this->PAGES[$key][$page];
        }

        $schema = Schema::get(static::COMPONENT);

        $field = $schema->getField($column);

        $queryUnion = RelatedKeysMergeHelper::getQueryUnion(static::COMPONENT, $column, $this->getIdColumnValue());
        $queryUnion->pagination($page, $field->getItemsPerPage());
        $results = RelatedKeysMergeHelper::getRawResultsFromQueryUnion(static::COMPONENT, $column, $queryUnion);

        $this->PAGES[$key][$page] = $results;
        return $this->PAGES[$key][$page];
    }

    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedKeysMergeCount(string $column, Where $where = null)
    {
        if ($this->hasPageTotal($column)) {
            return $this->PAGES_TOTAL[$column];
        }

        $queryUnion = RelatedKeysMergeHelper::getQueryUnion(static::COMPONENT, $column, $this->getIdColumnValue());
        $results = RelatedKeysMergeHelper::getCountFromQueryUnion(static::COMPONENT, $queryUnion);

        $this->PAGES_TOTAL[$column] = $results;
        return $this->PAGES_TOTAL[$column];
    }

    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    protected function _getRelatedKeysMergeAmountOfPages(string $column, Where $where = null)
    {
        $schema = Schema::get(static::COMPONENT);

        /** @var RelatedKeysMergeField $field */
        $field = $schema->getField($column);

        return getTotalPages($this->_getRelatedKeysMergeCount($column, $where), $field->getItemsPerPage());
    }
}