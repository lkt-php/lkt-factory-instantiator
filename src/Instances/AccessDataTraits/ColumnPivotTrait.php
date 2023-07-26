<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Exception;
use Lkt\DatabaseConnectors\DatabaseConnections;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\AbstractField;
use Lkt\Factory\Schemas\Fields\ForeignKeyField;
use Lkt\Factory\Schemas\Fields\PivotField;
use Lkt\Factory\Schemas\Fields\PivotLeftIdField;
use Lkt\Factory\Schemas\Fields\PivotRightIdField;
use Lkt\Factory\Schemas\Schema;
use Lkt\QueryBuilding\Query;
use Lkt\QueryBuilding\Where;
use function Lkt\Tools\Arrays\arrayPushUnique;
use function Lkt\Tools\Arrays\getArrayFirstPosition;

trait ColumnPivotTrait
{
    /**
     * @param string $column
     * @return void
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     * @throws InvalidSchemaAppClassException
     * @throws Exception
     */
    private function _loadPivots(string $column)
    {
        $schema = Schema::get(static::GENERATED_TYPE);

        /** @var PivotField $field */
        $field = $schema->getField($column);
        $idColumn = $schema->getIdString();

        $pivotedSchema = Schema::get($field->getPivotComponent());

        /** @var AbstractField $pivotedField */
        $pivotedField = $pivotedSchema->getOneFieldPointingToComponent(static::GENERATED_TYPE);

        $pivotedFieldColumn = trim($pivotedField->getColumn());

        $where = $field->getWhere();

        $order = $field->getOrder();
        $builder = Query::table($pivotedSchema->getTable());

        $connector = $schema->getDatabaseConnector();
        if ($connector === '') {
            $connector = DatabaseConnections::$defaultConnector;
        }
        $connection = DatabaseConnections::get($connector);
        $where[] = $connection->makeUpdateParams([$pivotedFieldColumn => $this->DATA[$idColumn]]);
        $builder->setColumns($connection->extractSchemaColumns($pivotedSchema));

        $builder->where(Where::raw(implode(' AND ', $where)));
        $builder->orderBy(implode(',', $order));

        $results = $builder->select();
        $pivots = Instantiator::makeResults($pivotedSchema->getComponent(), $results);

        $this->PIVOT[$column] = $pivots;
    }


    /**
     * @param string $column
     * @return array
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
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

        if ($fieldPivotColumn instanceof ForeignKeyField) {
            $getter .= 'Id';
        }

        $ids = array_map(function($item) use ($getter) {
            return $item->{$getter}();
        }, $this->PIVOT[$column]);

        if (count($ids) === 0) {
            $this->PIVOT_DATA[$column] = [];
            return [];
        }

        /** @var ForeignKeyField[] $pivotIdentifiers */
        $pivotIdentifiers = $pivotSchema->getIdentifiers();
        $pivotForeignColumn = null;
        foreach ($pivotIdentifiers as $identifier) {
            if ($identifier->getComponent() === $fromField->getComponent()) {
                $pivotForeignColumn = $identifier;
                break;
            }
        }

        /** @var PivotRightIdField|PivotLeftIdField $pivotForeignColumn */
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

        /**
         * @var Query $builder
         */
        list($builder) = Instantiator::getQueryCaller($toSchema->getComponent());

//        $caller->andIntegerIn($toColumnString, $ids);
        $builder->where(Where::raw($where));
        $builder->orderBy($order);
        $results = Instantiator::makeResults($toSchema->getComponent(), $builder->select());

        $this->PIVOT_DATA[$column] = $results;
        return $this->PIVOT_DATA[$column];
    }

    /**
     * @param string $column
     * @return bool
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    protected function _hasPivotVal(string $column = '') :bool
    {
        return count($this->_getPivotVal($column)) > 0;
    }
}