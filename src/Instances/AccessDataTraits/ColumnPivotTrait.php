<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Lkt\Drivers\MySql;
use Lkt\Factory\Schemas\Fields\AbstractField;
use Lkt\Factory\Schemas\Fields\PivotField;
use Lkt\Factory\Schemas\Schema;
use function Lkt\Factory\factory;
use function Lkt\Tools\Arrays\arrayPushUnique;
use function Lkt\Tools\Arrays\getArrayFirstPosition;
use const Lkt\Factory\COLUMN_FOREIGN_KEY;


trait ColumnPivotTrait
{
    /**
     * @param string $column
     * @return void
     */
    private function _loadPivots(string $column)
    {
        /** @var Schema $schema */
        $schema = Schema::get(static::GENERATED_TYPE);

        /** @var PivotField $field */
        $field = $schema->getField($column);
        $idColumn = $schema->getIdString();

        /** @var Schema $pivotedSchema */
        $pivotedSchema = Schema::get($field->getPivotComponent());

        /** @var AbstractField $pivotedField */
        $pivotedField = $pivotedSchema->getOneFieldPointingToComponent(static::GENERATED_TYPE);

        $pivotedFieldColumn = trim($pivotedField->getColumn());

        $where = $field->getWhere();
        if (!is_array($where)){
            $where = [];
        }
        $where[] = MySql::makeUpdateParams([$pivotedFieldColumn => $this->DATA[$idColumn]]);

        $order = $field->getOrder();
        if (!is_array($order)){
            $order = [];
        }
        $pivots = factory($field->getPivotComponent())
            ->where(implode(' AND ', $where))
            ->orderBy(implode(',', $order))
            ->query();

        $this->PIVOT[$column] = $pivots;
    }


    /**
     * @param string $column
     * @return array
     */
    protected function _getPivotVal(string $column) :array
    {
        if (!isset($this->PIVOT[$column])) {
            $this->_loadPivots($column);
        }

        if (isset($this->UPDATED_PIVOT_DATA[$column])) {
            return $this->UPDATED_PIVOT_DATA[$column];
        }

        if (isset($this->PIVOT_DATA[$column])) {
            return $this->PIVOT_DATA[$column];
        }

        /** @var Schema $fromSchema */
        $fromSchema = Schema::get(static::GENERATED_TYPE);

        /** @var PivotField $fromField */
        $fromField = $fromSchema->getField($column);

        /** @var Schema $pivotSchema */
        $pivotSchema = Schema::get($fromField->getPivotComponent());

        /** @var AbstractField $pivotedField */
        $fieldPivotColumn = $pivotSchema->getOneFieldPointingToComponent($fromField->getComponent());

        /**
         * Build getter
         */
        $auxColumn = $fieldPivotColumn->getColumn();
        $key = getArrayFirstPosition(array_keys(array_filter($pivotSchema->getAllFields(), function (AbstractField $field) use ($auxColumn) {
            return $field->getColumn() === $auxColumn;
        })));
        $getter = 'get'.ucfirst($key);

        if ($fieldPivotColumn->getType() === COLUMN_FOREIGN_KEY) {
            $getter .= 'Id';
        }

        $ids = array_map(function($item) use ($getter) {
            return $item->{$getter}();
        }, $this->PIVOT[$column]);

        if (count($ids) === 0) {
            $this->PIVOT_DATA[$column] = [];
            return [];
        }

        $pivotIdentifiers = $pivotSchema->getIdentifiers();
        $pivotForeignColumn = null;
        foreach ($pivotIdentifiers as $identifier) {
            if ($identifier->getComponent() === $fromField->getComponent()) {
                $pivotForeignColumn = $identifier;
                break;
            }
        }

        /** @var Schema $toSchema */
        $toSchema = Schema::get($pivotForeignColumn->getComponent());

        $toIdentifiers = $toSchema->getIdentifiers();
        $toForeignColumn = $toIdentifiers[0];

        if ($toForeignColumn === null) {
            //@todo throw exception
            $this->PIVOT_DATA[$column] = [];
            return [];
        }

        $toColumnString = $toForeignColumn->getColumn();

        $where = $toColumnString . ' IN (' . implode(',', $ids) . ')';
        $order = [];
        foreach ($ids as $id){
            arrayPushUnique($order, "{$toColumnString} = '{$id}' DESC");
        }

        $order = trim(implode(', ', $order));

        $r = factory($toSchema->getComponent())
            ->where($where)
            ->orderBy($order)
            ->query();

        if (!is_array($r)) {
            $r = [];
        }

        $this->PIVOT_DATA[$column] = $r;
        return $this->PIVOT_DATA[$column];
    }

    /**
     * @param string $type
     * @param string $column
     * @return bool
     */
    protected function _hasPivotVal($type = '', $column = '') :bool
    {
        return count($this->_getRelatedVal($type)) > 0;
    }

//    protected function _setPivotVal($type = '', $column = '', $items = [], $deleteUnlinked = false)
//    {
//        $this->UPDATED[$column] = $items;
//    }
}