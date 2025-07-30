<?php

namespace Lkt\Factory\Instantiator\Enums;

enum CrudOperation: string
{
    case Create = 'create';
    case Read = 'read';
    case Update = 'update';
    case Drop = 'drop';
}