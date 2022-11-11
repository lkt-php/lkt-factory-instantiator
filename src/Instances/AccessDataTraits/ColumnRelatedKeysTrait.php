<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Factory\FactorySettings;
use Lkt\Factory\InstanceFactory;
use Lkt\Factory\Schemas\Fields\RelatedKeysField;
use Lkt\Factory\Schemas\Schema;

/**
 * Trait ColumnRelatedKeysTrait
 *
 * @package Lkt\Factory\Instantiator\Instances\AccessDataTraits
 */
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

        $r = $this->_getRelatedKeysInstanceFactory($type, $column, $forceRefresh);

        if ($r) {
            $r = $r->query();
        }

        if (!is_array($r)) {
            $r = [];
        }

        $this->RELATED_DATA[$column] = $r;
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

        $relatedIdColumn = FactorySettings::getComponentIdColumn($type);
        $relatedClass = FactorySettings::getComponentClassName($type);

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