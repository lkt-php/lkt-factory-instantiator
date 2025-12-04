<?php

namespace Lkt\Factory\Instantiator\Helpers;

use Lkt\Factory\Instantiator\ValueObjects\ComponentDatabaseIntegration;
use Lkt\QueryBuilding\Query;

class QueryBuilderHelper
{
    public static function getComponentQuery(string $component): Query
    {
        return (ComponentDatabaseIntegration::from($component))->query;
    }
}