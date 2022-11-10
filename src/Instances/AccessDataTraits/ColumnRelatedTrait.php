<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Drivers\MySql;
use Lkt\Factory\FactorySettings;
use Lkt\Factory\Schemas\Fields\RelatedField;
use Lkt\Factory\Schemas\Schema;
use function Lkt\Factory\factory;

trait ColumnRelatedTrait
{
    /**
     * @param string $type
     * @param string $column
     * @param bool $forceRefresh
     * @return array
     */
    protected function _getRelatedVal($type = '', $column = '', $forceRefresh = false) :array
    {
        if (!$forceRefresh && isset($this->UPDATED_RELATED_DATA[$column])) {
            return $this->UPDATED_RELATED_DATA[$column];
        }

        if (!$forceRefresh && isset($this->RELATED_DATA[$column])) {
            return $this->RELATED_DATA[$column];
        }

        $schema = Schema::get(static::GENERATED_TYPE);

        $idColumn = $schema->getIdString();
        if (!$this->DATA[$idColumn]) {
            return [];
        }

        $r = $this->_getRelatedInstanceFactory($type, $column, $forceRefresh);

        if ($r) {
            $r = $r->query();
        }

        if (!is_array($r)) {
            $r = [];
        }

        $this->RELATED_DATA[$column] = $r;
        return $this->RELATED_DATA[$column];
    }

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
        if ($this->DATA[$idColumn]) {
            $where[] = MySql::makeUpdateParams([$field->getColumn() => $this->DATA[$idColumn]]);
        }

        // @todo
//        $order = $field['order'];
//        if (!is_array($order)){
//            $order = [];
//        }
        $order = [];

        return factory($type)
            ->where(implode(' AND ', $where))
            ->orderBy(implode(',', $order))
            ->forceRefresh($forceRefresh)
            ;
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

    protected function _setRelatedValWithData($type = '', $column = '', $data = [])
    {
        $this->PENDING_UPDATE_RELATED_DATA[$column] = $data;

        $relatedIdColumn = FactorySettings::getComponentIdColumn($type);
        $relatedClass = FactorySettings::getComponentClassName($type);

        $r = [];

        foreach ($data as $datum){
            $instance = $relatedClass::getInstance($datum[$relatedIdColumn]);
            $instance->hydrate($datum);
            $r[] = $instance;
        }

        $this->UPDATED_RELATED_DATA[$column] = $r;
        return $this;
    }
}