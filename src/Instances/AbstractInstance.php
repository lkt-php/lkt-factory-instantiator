<?php

namespace Lkt\Factory\Instantiator\Instances;

use Exception;
use Lkt\DatabaseConnectors\Cache\QueryCache;
use Lkt\DatabaseConnectors\DatabaseConnector;
use Lkt\Factory\Instantiator\Cache\InstanceCache;
use Lkt\Factory\Instantiator\Conversions\InstanceToArray;
use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnBooleanTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnColorTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnDateTimeTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnEmailTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnEncryptTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnFileTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnFloatTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnForeignListTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnForeignTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnIntegerChoiceTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnIntegerTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnJsonTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnPivotTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnRelatedKeysMergeTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnRelatedKeysTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnRelatedTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnStringChoiceTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnStringTrait;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\ColorField;
use Lkt\Factory\Schemas\Fields\DateTimeField;
use Lkt\Factory\Schemas\Fields\EmailField;
use Lkt\Factory\Schemas\Fields\FloatField;
use Lkt\Factory\Schemas\Fields\HTMLField;
use Lkt\Factory\Schemas\Fields\IdField;
use Lkt\Factory\Schemas\Fields\IntegerChoiceField;
use Lkt\Factory\Schemas\Fields\IntegerField;
use Lkt\Factory\Schemas\Fields\JSONField;
use Lkt\Factory\Schemas\Fields\RelatedField;
use Lkt\Factory\Schemas\Fields\StringChoiceField;
use Lkt\Factory\Schemas\Fields\StringField;
use Lkt\Factory\Schemas\Schema;
use Lkt\QueryBuilding\Query;
use function Lkt\Tools\Arrays\compareArrays;
use function Lkt\Tools\Pagination\getTotalPages;
use function Lkt\Tools\Parse\clearInput;

abstract class AbstractInstance
{
    use ColumnStringTrait,
        ColumnIntegerTrait,
        ColumnFloatTrait,
        ColumnEmailTrait,
        ColumnBooleanTrait,
        ColumnColorTrait,
        ColumnJsonTrait,
        ColumnFileTrait,
        ColumnForeignTrait,
        ColumnForeignListTrait,
        ColumnRelatedTrait,
        ColumnRelatedKeysTrait,
        ColumnPivotTrait,
        ColumnDateTimeTrait,
        ColumnStringChoiceTrait,
        ColumnIntegerChoiceTrait,
        ColumnEncryptTrait,
        ColumnRelatedKeysMergeTrait;

    protected $TYPE;
    protected array $DATA = [];
    protected array $UPDATED = [];
    protected array $PIVOT = [];
    protected array $PIVOT_DATA = [];
    protected array $UPDATED_PIVOT_DATA = [];
    protected array $RELATED_DATA = [];
    protected array $UPDATED_RELATED_DATA = [];
    protected array $PENDING_UPDATE_RELATED_DATA = [];
    protected array $PAGES = [];
    protected array $PAGES_TOTAL = [];
    const GENERATED_TYPE = '';
    const COMPONENT = '';

    protected array $DECRYPT = [];
    protected array $DECRYPT_UPDATED = [];

    /**
     * @param string|null $component
     * @param array $initialData
     */
    public function __construct(string $component = null, array $initialData = [])
    {
        if (!$component && static::GENERATED_TYPE) {
            $component = static::GENERATED_TYPE;
        }
        $this->TYPE = $component;
        $this->DATA = $initialData;
    }

    public function setData(array $initialData): static
    {
        $this->DATA = $initialData;
        return $this;
    }

    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    public static function getInstance($id = null, string $component = self::GENERATED_TYPE, array $initialData = []): static
    {
        if (!$component) $component = static::GENERATED_TYPE;
        if (!$id || !$component) return new static();

        $code = Instantiator::getInstanceCode($component, $id);

        if (InstanceCache::inCache($code)) {
            $cached = InstanceCache::load($code);
            $cached->hydrate([]);
            return $cached;
        }

        if (count($initialData) > 0) {
            $r = new static($component, $initialData);
            $r->setData($initialData);
            InstanceCache::store($code, $r);
            return InstanceCache::load($code);
        }
        /**
         * @var Schema $schema
         * @var DatabaseConnector $connection
         * @var Query $queryBuilder
         */
        list($builder, $connection, $schema) = Instantiator::getQueryCaller($component);
        $identifiers = $schema->getIdentifiers();

        foreach ($identifiers as $identifier) $builder->andIntegerEqual($identifier->getColumn(), $id);

        $data = $builder->selectDistinct();
        if (count($data) > 0) {
            $converter = new RawResultsToInstanceConverter($component, $data[0]);
            $itemData = $converter->parse();

            $r = new static($component, $itemData);
            $r->setData($itemData);
            InstanceCache::store($code, $r);
            return InstanceCache::load($code);
        }

        return new static();
    }

    public function isAnonymous(): bool
    {
        return count($this->DATA) === 0;
    }


    /**
     * @return mixed
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    public function getIdColumnValue()
    {
        $schema = Schema::get(static::GENERATED_TYPE);
        $idColumn = $schema->getIdString();
        return $this->DATA[$idColumn];
    }

    /**
     * @deprecated
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    public function convertToComponent(string $component = ''): ?static
    {
        return Instantiator::make($component, $this->getIdColumnValue());
    }


    public function hydrate(array $data): static
    {
        if (count($data) === 0) {
            $this->UPDATED = [];
            return $this;
        }
        foreach ($data as $column => $datum) $this->UPDATED[$column] = $datum;
        return $this;
    }

    public function save(): static
    {
        $isUpdate = !$this->isAnonymous();

        /**
         * @var Schema $schema
         * @var DatabaseConnector $connection
         * @var Query $queryBuilder
         */
        list($queryBuilder, $connection, $schema) = Instantiator::getQueryCaller(static::GENERATED_TYPE);
        $parsed = $connection->prepareDataToStore($schema, $this->UPDATED);

        $queryBuilder->updateData($parsed);

        $origIdColumn = $schema->getIdColumn();
        $origIdColumn = $origIdColumn[0];

        if ($isUpdate) {
            $idColumn = $schema->getField($origIdColumn);
            $idColumn = $idColumn->getColumn();
            $queryBuilder->andIntegerEqual($idColumn, $this->DATA[$origIdColumn]);
            $query = $connection->getUpdateQuery($queryBuilder);
        } else {
            $query = $connection->getInsertQuery($queryBuilder);
        }

        $queryResponse = $connection->query($query);

        $id = (int)$connection->getLastInsertedId();
        $reload = true;

        if ($id > 0 && (!isset($this->DATA[$origIdColumn]) || !$this->DATA[$origIdColumn])) {
            $this->DATA[$origIdColumn] = $id;

        } elseif ($this->DATA[$origIdColumn] > 0) {
            $id = $this->DATA[$origIdColumn];
        }

        if ($queryResponse !== false) {
            foreach ($this->UPDATED as $k => $v) {
                $this->DATA[$k] = $v;
                unset($this->UPDATED[$k]);
            }
        }

        if (count($this->PENDING_UPDATE_RELATED_DATA) > 0) {
            foreach ($this->PENDING_UPDATE_RELATED_DATA as $column => $data) {

                /** @var RelatedField $field */
                $field = $schema->getField($column);
                $relatedComponent = $field->getComponent();

                $relatedSchema = Schema::get($field->getComponent());

                $relatedIdColumn = $relatedSchema->getIdColumn()[0];
                $relatedIdColumnGetter = 'get' . ucfirst($relatedIdColumn);
                $relatedClass = $relatedSchema->getInstanceSettings()->getAppClass();

                $create = $relatedSchema->getCreateHandler();
                $update = $relatedSchema->getUpdateHandler();
                $delete = $relatedSchema->getDeleteHandler();

                // Check which items must be deleted
                $currentItems = $this->_getRelatedVal($relatedComponent, $column, true);
                $currentIds = [];
                foreach ($currentItems as $currentItem) {
                    $idAux = (int)$currentItem->{$relatedIdColumnGetter}();
                    if ($idAux > 0 && !in_array($idAux, $currentIds, true)) {
                        $currentIds[] = $idAux;
                    }
                }

                $updatedIds = [];
                foreach ($data as $datum) {
                    if ($datum[$relatedIdColumn] > 0) {
                        $updatedIds[] = (int)$datum[$relatedIdColumn];
                    }
                }

                $diff = compareArrays($currentIds, $updatedIds);

                // Delete
                foreach ($diff['deleted'] as $deletedId) {
                    $delete::getInstance($relatedClass::getInstance($deletedId));
                }


                // Update or create
                foreach ($data as $datum) {
                    if ($datum[$relatedIdColumn] > 0) {
                        $instance = $relatedClass::getInstance($datum[$relatedIdColumn]);
                        $update::getInstance($instance, $datum);

                    } else {
                        $create::getInstance($datum);
                    }
                }
            }
            $this->PENDING_UPDATE_RELATED_DATA = [];
        }

        if ($reload) {
            $cacheCode = Instantiator::getInstanceCode(static::GENERATED_TYPE, $id);
            InstanceCache::clearCode($cacheCode);
            return Instantiator::make(static::GENERATED_TYPE, $id);
        }

        return $this;
    }

    public function delete(): static
    {
        if ($this->isAnonymous()) return $this;

        /**
         * @var Schema $schema
         * @var DatabaseConnector $connection
         * @var Query $caller
         */
        list($caller, $connection, $schema, $connector) = Instantiator::getQueryCaller(static::GENERATED_TYPE);

        $origIdColumn = $schema->getIdColumn();
        $origIdColumn = $origIdColumn[0];
        $idColumn = $schema->getField($origIdColumn);
        $idColumn = $idColumn->getColumn();
        $id = (int)$this->DATA[$origIdColumn];
        $caller->andIntegerEqual($idColumn, $id);

        $connection->query($connection->getDeleteQuery($caller));
        $cacheCode = Instantiator::getInstanceCode(static::GENERATED_TYPE, $id);
        InstanceCache::clearCode($cacheCode);
        $query = $connection->getSelectQuery($caller);
        QueryCache::set($connector, $query, []);
        $this->setData([]);
        $this->hydrate([]);
        $this->RELATED_DATA = [];
        $this->PIVOT = [];
        $this->PIVOT_DATA = [];
        $this->UPDATED_RELATED_DATA = [];
        $this->PENDING_UPDATE_RELATED_DATA = [];
        return $this;
    }

    /**
     * @return Query
     * @throws SchemaNotDefinedException
     */
    public static function getQueryCaller()
    {
        /**
         * @var Query $caller
         */
        list($caller) = Instantiator::getQueryCaller(static::GENERATED_TYPE);
        return $caller;
    }

    /**
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     * @throws Exception
     */
    public static function getMany(Query $queryCaller = null): array
    {
        if (!$queryCaller) {
            $queryCaller = static::getQueryCaller();
        }
        return Instantiator::makeResults(static::GENERATED_TYPE, $queryCaller->selectDistinct());
    }

    /**
     * @return AbstractInstance|null
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    public static function getOne(Query $queryCaller = null)
    {
        if (!$queryCaller) $queryCaller = static::getQueryCaller();
        $queryCaller->pagination(1, 1);
        $r = Instantiator::makeResults(static::GENERATED_TYPE, $queryCaller->selectDistinct());
        if (count($r) > 0) {
            return $r[0];
        }
        return null;
    }

    /**
     * @throws SchemaNotDefinedException
     */
    public static function getCount(Query $queryCaller = null, string $countableField = null): int
    {
        if (!$queryCaller) $queryCaller = static::getQueryCaller();

        if (!$countableField) {
            $schema = Schema::get(static::GENERATED_TYPE);
            $countableField = $schema->getCountableField();
        }

        if (!$countableField) return 0;

        return $queryCaller->count($countableField);
    }

    /**
     * @throws SchemaNotDefinedException
     */
    public static function getAmountOfPages(Query $queryCaller = null, string $countableField = null): int
    {
        $total = static::getCount($queryCaller, $countableField);
        if ($total === 0) return 0;
        $schema = Schema::get(static::GENERATED_TYPE);
        $itemsPerPage = $schema->getItemsPerPage();
        if ($itemsPerPage <= 0) return 0;
        return getTotalPages($total, $itemsPerPage);
    }

    /**
     * @param int $page
     * @param Query|null $queryCaller
     * @return array
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    public static function getPage(int $page, Query $queryCaller = null): array
    {
        if (!$queryCaller) $queryCaller = static::getQueryCaller();
        $schema = Schema::get(static::GENERATED_TYPE);
        $limit = $schema->getItemsPerPage();
        if ($limit >= 0) $queryCaller->pagination($page, $limit);
        return Instantiator::makeResults(static::GENERATED_TYPE, $queryCaller->selectDistinct());
    }

    public function getComponent(): string
    {
        return static::GENERATED_TYPE;
    }

    public function toArray(): array
    {
        return InstanceToArray::convert($this);
    }

    protected function hasPageLoaded(string $fieldName, int $page): bool
    {
        return isset($this->PAGES[$fieldName][$page])
            && is_array($this->PAGES[$fieldName][$page]);
    }

    protected function hasPageTotal(string $fieldName): bool
    {
        return isset($this->PAGES_TOTAL[$fieldName]);
    }

    public static function create(array $params): static
    {
        $instance = new static();
        static::feedInstance($instance, $params);
        return $instance->save();
    }

    public static function update(AbstractInstance $instance, array $params): static
    {
        static::feedInstance($instance, $params);
        return $instance->save();
    }

    public static function feedInstance(AbstractInstance $instance, array $params): static
    {
        $schema = Schema::get(static::GENERATED_TYPE);

        foreach ($params as $param => $value) {

            $field = $schema->getField($param);

            if ($field instanceof StringChoiceField) {
                $instance->_setStringChoiceVal($param, clearInput($value));

            } elseif ($field instanceof StringField || $field instanceof EmailField || $field instanceof HTMLField) {
                $instance->_setStringVal($param, clearInput($value));

            } elseif ($field instanceof DateTimeField) {
                $instance->_setDateTimeVal($param, $value);

            } elseif ($field instanceof IntegerChoiceField) {
                $instance->_setIntegerChoiceVal($param, (int)$value);

            } elseif ($field instanceof IntegerField && !($field instanceof IdField)) {
                $instance->_setIntegerVal($param, (int)$value);

            } elseif ($field instanceof FloatField) {
                $instance->_setFloatVal($param, (float)$value);

            } elseif ($field instanceof JSONField) {
                $instance->_setJsonVal($param, $value);

            } elseif ($field instanceof ColorField) {
                $instance->_setColorVal($param, $value);
            }
        }

        return $instance;
    }
}