<?php

namespace Lkt\Factory\Instantiator\Conversions;

use Lkt\Factory\Instantiator\Instances\AbstractInstance;
use Lkt\Factory\Schemas\Fields\AbstractField;
use Lkt\Factory\Schemas\Fields\BooleanField;
use Lkt\Factory\Schemas\Fields\DateTimeField;
use Lkt\Factory\Schemas\Fields\ForeignKeyField;
use Lkt\Factory\Schemas\Fields\ForeignKeysField;
use Lkt\Factory\Schemas\Fields\RelatedField;
use Lkt\Factory\Schemas\Fields\UnixTimeStampField;
use Lkt\Factory\Schemas\Schema;

class InstanceToArray
{
    public static function convert(AbstractInstance $instance): array
    {
        $schema = Schema::get($instance->getComponent());
        $fields = $schema->getAllFields();
        $r = [];

        return array_reduce($fields, function ($r,AbstractField $field) use ($instance) {
            $name = $field->getName();
            if ($field instanceof BooleanField) {
                $r[$name] = $instance->{$name}();
                return $r;
            }
            $getter = 'get'.ucfirst($name);

            if ($field instanceof DateTimeField) {
                $getter .= 'Formatted';
                $r[$name] = $instance->{$getter}('Y-m-d H:i:s');
                return $r;
            }

            if ($field instanceof UnixTimeStampField) {
                $date = $instance->{$getter}();
                $r[$name] = 0;
                if ($date) {
                    $r[$name] = strtotime($date->format('Y-m-d H:i:s'));
                }
                return $r;
            }

            if ($field instanceof ForeignKeyField) {
//                if ($field->getComponent() !== $fromComponent) {
//                    $foreignInstance = $instance->{$getter}();
//
//                    if ($foreignInstance instanceof AbstractInstance && method_exists($foreignInstance, 'toArray')) {
//                        $r[$name] = InstanceToArray::convert($foreignInstance, $fromComponent);
//                    }
//                }
                $getter .= 'Id';
                $r[$name.'Id'] = $instance->{$getter}();
                return $r;
            }

            if ($field instanceof RelatedField) {
                $data = array_map(
                    function (AbstractInstance $ins) {
                        return $ins->toArray();
                    }, $instance->{$getter}()
                );
                $r[$name] = $data;
                return $r;
            }

            if ($field instanceof ForeignKeysField) {
                $getter .= 'Data';
                $data = array_map(
                    function (AbstractInstance $ins) {
                        return $ins->toArray();
                    }, $instance->{$getter}()
                );
                $r[$name] = $data;
                return $r;
            }

            $getter = 'get'.ucfirst($name);
            $r[$name] = $instance->{$getter}();

            return $r;
        }, $r);
    }
}