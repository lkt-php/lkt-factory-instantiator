<?php

namespace Lkt\Factory\Instantiator\Process;

use Lkt\Factory\Schemas\Fields\AbstractField;
use Lkt\Factory\Schemas\Fields\IntegerChoiceField;
use Lkt\Factory\Schemas\Fields\IntegerField;
use Lkt\Factory\Schemas\Fields\StringChoiceField;
use Lkt\Factory\Schemas\Fields\StringField;
use Lkt\Factory\Schemas\Schema;
use Lkt\Factory\Schemas\Values\ComponentValue;
use Lkt\QueryBuilding\Enums\FilterRule;
use Lkt\QueryBuilding\Enums\ProcessRule;
use Lkt\QueryBuilding\Query;
use function Lkt\Tools\Parse\clearInput;

final class ProcessQueryCallerData
{
    protected $component;
    protected $queryCaller;
    protected $data;
    protected $processRules;
    protected $filterRules;
    protected $schema;

    public function __construct(string $component, Query $caller, array $data = null, array $processRules = null, array $filterRules = null)
    {
        $this->component = new ComponentValue($component);
        $this->schema = Schema::get($this->component->getValue());
        $this->queryCaller = $caller;
        $this->data = $data;
        $this->processRules = $processRules;
        $this->filterRules = $filterRules;

        if (!is_array($this->processRules)) $this->processRules = [];
        if (!is_array($this->filterRules)) $this->filterRules = [];
        if (!is_array($this->data)) $this->data = [];
    }

    final public function process(): void
    {
        if (count($this->data) === 0) return;
        $fields = $this->schema->getNonRelationalFields();
        $data = $this->data;
        $result = [];
        $fieldKeys = array_keys($fields);
        $data = array_filter($data, function ($key) use ($fieldKeys) {
            return in_array($key, $fieldKeys, true);
        }, ARRAY_FILTER_USE_KEY);

        if (count($data) === 0) return;

        $processRules = $this->processRules;
        $filterRules = $this->filterRules;
        $caller = $this->queryCaller;

        array_reduce($fields, function (&$result, AbstractField $field) use ($caller, $data, $processRules, $filterRules) {
            $key = $field->getName();
            $column = $field->getColumn();
            if (!array_key_exists($key, $data)) {
                return $result;
            }

            if (array_key_exists($key, $processRules) && $processRules[$key] === ProcessRule::ignore) {
                return $result;
            }

            $processRule = null;
            if (array_key_exists($key, $processRules)) {
                $processRule = $processRules[$key];
            }

            $filterRule = null;
            if (array_key_exists($key, $filterRules)) {
                $filterRule = $filterRules[$key];
            }

            if ($field instanceof StringChoiceField) {
                $value = $data[$key];
                if (is_array($value)) {
                    $value = array_map(function ($datum) { return clearInput($datum);}, $value);
                } else {
                    $value = clearInput($value);
                }
                if (!$processRule) {
                    $processRule = ProcessRule::equal;
                    if (is_array($value)) {
                        $processRule = ProcessRule::in;
                    }
                }

                if (!$filterRule) {
                    $filterRule = FilterRule::notEmpty;
                }

                if ($this->validFilterRule($value, $filterRule)) {
                    $caller->addStringProcessRule($column, $value, $processRule);
                }
            }

            elseif ($field instanceof StringField) {
                $value = $data[$key];
                if (is_array($value)) {
                    $value = array_map(function ($datum) { return clearInput($datum);}, $value);
                } else {
                    $value = clearInput($value);
                }
                if (!$processRule) {
                    $processRule = ProcessRule::like;
                    if (is_array($value)) {
                        $processRule = ProcessRule::in;
                    }
                }

                if (!$filterRule) {
                    $filterRule = FilterRule::notEmpty;
                }

                if ($this->validFilterRule($value, $filterRule)) {
                    $caller->addStringProcessRule($column, $value, $processRule);
                }
            }

            elseif ($field instanceof IntegerChoiceField) {
                $value = $data[$key];
                if (is_array($value)) {
                    $value = array_map(function ($datum) { return (int)clearInput($datum);}, $value);
                } else {
                    $value = (int)clearInput($value);
                }
                if (!$processRule) {
                    $processRule = ProcessRule::equal;
                    if (is_array($value)) {
                        $processRule = ProcessRule::in;
                    }
                }

                if (!$filterRule) {
                    $filterRule = FilterRule::greaterThanZero;
                }

                if ($this->validFilterRule($value, $filterRule)) {
                    $caller->addIntegerProcessRule($column, $value, $processRule);
                }
            }

            elseif ($field instanceof IntegerField) {
                $value = $data[$key];
                if (is_array($value)) {
                    $value = array_map(function ($datum) { return (int)clearInput($datum);}, $value);
                } else {
                    $value = (int)clearInput($value);
                }
                if (!$processRule) {
                    $processRule = ProcessRule::greaterThan;
                    if (is_array($value)) {
                        $processRule = ProcessRule::in;
                    }
                }

                if (!$filterRule) {
                    $filterRule = FilterRule::greaterThanZero;
                }

                if ($this->validFilterRule($value, $filterRule)) {
                    $caller->addIntegerProcessRule($column, $value, $processRule);
                }
            }
            return $result;
        }, $result);
    }

    private function validFilterRule($value, $rule)
    {
        if ($rule === FilterRule::isNull) {
            return $value === null;
        }

        if ($rule === FilterRule::isNotNull) {
            return $value !== null;
        }

        if ($rule === FilterRule::empty) {
            if (is_string($value)) {
                if (!$value) {
                    return true;
                }
            }
        }

        if ($rule === FilterRule::notEmpty) {
            if (is_string($value)) {
                if ($value !== '') {
                    return true;
                }
            }

            if (is_array($value)) {
                return count($value) > 0;
            }
        }

        if ($rule === FilterRule::greaterThanZero) {
            if (is_numeric($value)) {
                if ($value > 0) {
                    return true;
                }
            }
        }

        if ($rule === FilterRule::greaterOrEqualThanZero) {
            if (is_numeric($value)) {
                if ($value >= 0) {
                    return true;
                }
            }
        }

        if ($rule === FilterRule::lowerThanZero) {
            if (is_numeric($value)) {
                if ($value < 0) {
                    return true;
                }
            }
        }

        if ($rule === FilterRule::lowerOrEqualThanZero) {
            if (is_numeric($value)) {
                if ($value <= 0) {
                    return true;
                }
            }
        }

        return false;
    }
}