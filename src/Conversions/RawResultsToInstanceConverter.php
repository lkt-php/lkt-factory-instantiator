<?php

namespace Lkt\Factory\Instantiator\Conversions;

use Lkt\Factory\Instantiator\Validations\ParseFieldValue;
use Lkt\Factory\Instantiator\Validations\ValidateFieldValue;
use Lkt\Factory\Schemas\Fields\AbstractField;
use Lkt\Factory\Schemas\Fields\ForeignKeyField;
use Lkt\Factory\Schemas\Schema;
use Lkt\Factory\Schemas\Values\ComponentValue;

final class RawResultsToInstanceConverter
{
    protected $component;
    protected $data;
    protected $schema;

    /**
     * @param string $component
     * @param array $data
     * @throws \Lkt\Factory\Schemas\Exceptions\InvalidComponentException
     * @throws \Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException
     */
    public function __construct(string $component, array $data)
    {
        $this->component = new ComponentValue($component);
        $this->schema = Schema::get($this->component->getValue());
        $this->data = $data;
    }

    /**
     * @return array
     * @throws \Lkt\Factory\Schemas\Exceptions\InvalidComponentException
     */
    final public function parse(): array
    {
        $r = $this->parseData();
        return array_merge($r, $this->checkData($r));
    }

    /**
     * @return array
     * @throws \Lkt\Factory\Schemas\Exceptions\InvalidComponentException
     */
    private function parseData(): array
    {
        $fields = $this->schema->getAllFields();
        $data = $this->data;

        return array_reduce($fields, function (&$result, AbstractField $field) use ($data) {
            $value = isset($data[$field->getName()]) ? $data[$field->getName()] : null;
            $key = $field->getName();

            if ($field instanceof ForeignKeyField) {
                $key .= 'Id';
            }

            $value = ParseFieldValue::parse($field, $value);

            $result[$key] = $value;
            return $result;
        });
    }

    /**
     * @param array $data
     * @return array
     * @throws \Lkt\Factory\Schemas\Exceptions\InvalidComponentException
     */
    private function checkData(array $data = []): array
    {
        $fields = $this->schema->getAllFields();

        return array_reduce($fields, function (&$result, $field) use ($data) {

            $value = isset($data[$field->getName()]) ? $data[$field->getName()] : null;
            $status = ValidateFieldValue::validate($field, $value);
            $key = trim('has' . ucfirst($field->getName()));
            $result[$key] = $status;
            return $result;
        }
        );
    }
}