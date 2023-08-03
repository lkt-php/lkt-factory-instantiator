<?php

namespace Lkt\Factory\Instantiator\Relations;

use Lkt\Connectors\DatabaseConnections;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Schemas\Schema;
use Lkt\Factory\Schemas\Fields\RelatedKeysMergeField;
use Lkt\QueryBuilding\Query;
use Lkt\QueryBuilding\QueryUnion;

class RelatedKeysMergeHelper
{
    public static function getQueryUnion(string $masterComponent, string $masterComponentField, int $masterId): QueryUnion
    {
        $masterSchema = Schema::get($masterComponent);

        /** @var RelatedKeysMergeField $masterField */
        $masterField = $masterSchema->getField($masterComponentField);

        $relations = $masterField->getRelations();
        $queryUnion = QueryUnion::getEmpty();

        if (count($relations) === 0) {
            return $queryUnion;
        }

        $masterAdditionalColumns = $masterField->getAdditionalColumns();

        foreach ($relations as $relation) {
            $component = $relation->getComponent();
            $schema = Schema::get($component);

            $field = $schema->getForeignKeysFieldPointingToComponent($masterComponent);

            $where = Instantiator::getCustomWhere($component);
            $where->andForeignKeysContains($field->getColumn(), $masterId);

            $idColumn = $schema->getIdInTableString();
            if ($idColumn !== 'id') {
                $idColumn = "{$idColumn} as id";
            }

            $additionalColumns = $relation->getAdditionalColumns();
            $additionalColumnsParsed = [];

            foreach ($masterAdditionalColumns as $column) {
                if (!isset($additionalColumns[$column])) {
                    throw new \Exception("Invalid configuration for additionalColumns at '{$masterComponent}' schema, '{$masterComponentField}' field, {$component}' component");
                }

                $relationAdditionalField = $schema->getField($additionalColumns[$column]);
                $additionalColumnsParsed[] = "{$relationAdditionalField->getColumn()} AS {$column}";
            }

            /**
             * @var Query $builder
             */
            list($builder) = Instantiator::getCustomQueryCaller($component);
            $builder->setColumns([$idColumn, "'{$component}' as component", ...$additionalColumnsParsed]);
            $builder->andWhere($where);

            $relation->applyQueryConfigurator($builder);
            $queryUnion->addQuery($builder);
        }

        return $queryUnion;
    }

    public static function getRawResultsFromQueryUnion(string $masterComponent, string $masterComponentField, QueryUnion $queryUnion): array
    {
        $masterSchema = Schema::get($masterComponent);

        /** @var RelatedKeysMergeField $masterField */
        $masterField = $masterSchema->getField($masterComponentField);

        $order = $masterField->getOrder();
        if (!is_array($order)) $order = [];

        $order = implode(',', $order);

        if ($order !== '') $queryUnion->orderBy($order);

        $query = $queryUnion->toString();

        $connector = $masterSchema->getDatabaseConnector();
        if ($connector === '') $connector = DatabaseConnections::$defaultConnector;
        $connection = DatabaseConnections::get($connector);
        return $connection->query($query);
    }

    public static function getCountFromQueryUnion(string $masterComponent, QueryUnion $queryUnion): int
    {
        $masterSchema = Schema::get($masterComponent);

        $queryUnion->countMode();

        $query = $queryUnion->toString();

        $connector = $masterSchema->getDatabaseConnector();
        if ($connector === '') $connector = DatabaseConnections::$defaultConnector;
        $connection = DatabaseConnections::get($connector);
        $response = $connection->query($query);
        return (int)$response[0]['Total'];
    }

    public static function convertRawResults(array $results): array
    {
        $r = [];
        foreach ($results as $result) $r[] = Instantiator::make($result['component'], $result['id']);
        return $r;
    }
}