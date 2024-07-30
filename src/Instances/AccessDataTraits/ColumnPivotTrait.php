<?php

namespace Lkt\Factory\Instantiator\Instances\AccessDataTraits;

use Exception;
use Lkt\Connectors\DatabaseConnections;
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

    protected array $PIVOT_SORT = [];

    public function _getPivotQueryBuilder(string $column): Query
    {
        // Own fields
        $ownSchema = Schema::get(static::COMPONENT);
        $ownField = $ownSchema->getPivotField($column);

        // Pivot table fields (intermediate table)
        $pivotSchema = $ownField->getPivotSchema();
        $pivotField = $pivotSchema->getOneFieldPointingToComponent($ownField->getComponent());
        $pivotOwnField = $pivotSchema->getOneFieldPointingToComponent(static::COMPONENT);
        $pivotOrderField = $pivotSchema->getOnePositionField();

        // Referenced table
        $referencedSchema = Schema::get($ownField->getComponent());
        $referencedField = $referencedSchema->getField($referencedSchema->getIdColumn()[0]);

        // Prepare query builder

        /** @var Query $queryBuilder */
        list($referencedQueryBuilder) = Instantiator::getQueryCaller($ownField->getComponent());

        /** @var Query $pivotQueryBuilder */
        list($pivotQueryBuilder) = Instantiator::getQueryCaller($pivotSchema->getComponent());

        $pivotQueryBuilder
            ->andIntegerEqual($pivotOwnField->getColumn(), $this->getIdColumnValue());

        $referencedQueryBuilder
            ->leftJoin($pivotQueryBuilder, $pivotField->getColumn(), $referencedField->getColumn())
            ->orderBy($pivotOrderField->getColumn() . ' ASC')
        ;

        return $referencedQueryBuilder;
    }

    public function _getAvailablePivotQueryBuilder(string $column): Query
    {
        // Own fields
        $ownSchema = Schema::get(static::COMPONENT);
        $ownField = $ownSchema->getPivotField($column);

        // Pivot table fields (intermediate table)
        $pivotSchema = $ownField->getPivotSchema();
        $pivotField = $pivotSchema->getOneFieldPointingToComponent($ownField->getComponent());
        $pivotOwnField = $pivotSchema->getOneFieldPointingToComponent(static::COMPONENT);
        $pivotOrderField = $pivotSchema->getOnePositionField();

        // Referenced table
        $referencedSchema = Schema::get($ownField->getComponent());
        $referencedField = $referencedSchema->getField($referencedSchema->getIdColumn()[0]);

        // Prepare query builder

        /** @var Query $queryBuilder */
        list($referencedQueryBuilder) = Instantiator::getQueryCaller($ownField->getComponent());

        /** @var Query $pivotQueryBuilder */
        list($pivotQueryBuilder) = Instantiator::getQueryCaller($pivotSchema->getComponent());

        $pivotQueryBuilder
            ->setColumns([$pivotOwnField->getColumn()])
            ->andIntegerEqual($pivotOwnField->getColumn(), $this->getIdColumnValue());

        $referencedQueryBuilder
            ->andFieldNotInSubQuery($pivotOwnField->getColumn(), $pivotQueryBuilder)
        ;

        return $referencedQueryBuilder;
    }

    public function _setPivotSort(string $column, array $data)
    {
        // Own fields
        $ownSchema = Schema::get(static::COMPONENT);
        $ownField = $ownSchema->getPivotField($column);

        // Referenced table
        $referencedSchema = Schema::get($ownField->getComponent());

        $idColumn = $referencedSchema->getIdColumn()[0];

        $items = array_map(function ($datum) use ($idColumn) {
            return $datum[$idColumn];
        }, $data);

        $this->PIVOT_SORT[$column] = $items;
        return $this;
    }


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
        $schema = Schema::get(static::COMPONENT);

        /** @var PivotField $field */
        $field = $schema->getField($column);
        $idColumn = $schema->getIdString();

        /** @var Schema $pivotedSchema */
        $pivotedSchema = $field->getPivotSchema();

        /** @var AbstractField $pivotedField */
        $pivotedField = $pivotedSchema->getOneFieldPointingToComponent(static::COMPONENT);

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

    public function _getPivots(string $column)
    {
        if (!isset($this->PIVOT[$column])) {
            $this->_loadPivots($column);
        }
        return $this->PIVOT[$column];
    }


    /**
     * @param string $column
     * @return array
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    protected function _getPivotVal(string $column): array
    {
//        if (!isset($this->PIVOT[$column])) {
//            $this->_loadPivots($column);
//        }

        if (isset($this->UPDATED_PIVOT_DATA[$column])) {
            return $this->UPDATED_PIVOT_DATA[$column];
        }

        if (isset($this->PIVOT_DATA[$column])) {
            return $this->PIVOT_DATA[$column];
        }

        /** @var Schema $fromSchema */
        $fromSchema = Schema::get(static::COMPONENT);

        /** @var PivotField $fromField */
        $fromField = $fromSchema->getField($column);

        /** @var Schema $pivotSchema */
        $pivotSchema = $fromField->getPivotSchema();


        $pivotIdentifiers = $pivotSchema->getIdentifiers();
        $pivotForeignColumn = null;
        foreach ($pivotIdentifiers as $identifier) {
            if ($identifier->getComponent() === $fromField->getComponent()) {
                $pivotForeignColumn = $identifier;
                break;
            }
        }

        $toSchema = Schema::get($pivotForeignColumn->getComponent());

        $queryBuilder = $this->_getPivotQueryBuilder($column);
        $results = Instantiator::makeResults($toSchema->getComponent(), $queryBuilder->select());

        $this->PIVOT_DATA[$column] = $results;
        return $this->PIVOT_DATA[$column];


        /** @var Schema $fromSchema */
        $fromSchema = Schema::get(static::COMPONENT);

        /** @var PivotField $fromField */
        $fromField = $fromSchema->getField($column);

        /** @var Schema $pivotSchema */
        $pivotSchema = $fromField->getPivotSchema();

        /** @var AbstractField $pivotedField */
        $fieldPivotColumn = $pivotSchema->getOneFieldPointingToComponent($fromField->getComponent());

        /**
         * Build getter
         */
        $auxColumn = $fieldPivotColumn->getColumn();
        $key = getArrayFirstPosition(array_keys(array_filter($pivotSchema->getAllFields(), function (AbstractField $field) use ($auxColumn) {
            return $field->getColumn() === $auxColumn;
        })));
        $getter = 'get' . ucfirst($key);

        if ($fieldPivotColumn instanceof ForeignKeyField) {
            $getter .= 'Id';
        }

        $ids = array_map(function ($item) use ($getter) {
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
        foreach ($ids as $id) {
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
    protected function _hasPivotVal(string $column = ''): bool
    {
        return count($this->_getPivotVal($column)) > 0;
    }
}