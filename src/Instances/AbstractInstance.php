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
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnFileTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnFloatTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnForeignListTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnForeignTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnIntegerChoiceTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnIntegerTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnJsonTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnPivotTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnRelatedKeysTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnRelatedKeysMergeTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnRelatedTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnStringChoiceTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnStringTrait;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\RelatedField;
use Lkt\Factory\Schemas\Schema;
use Lkt\QueryCaller\QueryCaller;
use function Lkt\Tools\Arrays\compareArrays;
use function Lkt\Tools\Pagination\getTotalPages;

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
        ColumnRelatedKeysMergeTrait;

    protected $TYPE;
    protected $DATA = [];
    protected $UPDATED = [];
    protected $PIVOT = [];
    protected $PIVOT_DATA = [];
    protected $UPDATED_PIVOT_DATA = [];
    protected $RELATED_DATA = [];
    protected $UPDATED_RELATED_DATA = [];
    protected $PENDING_UPDATE_RELATED_DATA = [];
    protected $PAGES = [];
    protected $PAGES_TOTAL = [];
    const GENERATED_TYPE = '';

    /**
     * @param $id
     * @param string|null $component
     * @param array $initialData
     */
    public function __construct($id = 0, string $component = null, array $initialData = [])
    {
        if (!$component && static::GENERATED_TYPE) {
            $component = static::GENERATED_TYPE;
        }
        $this->TYPE = $component;
        $this->DATA = $initialData;
    }

    /**
     * @param array $initialData
     * @return $this
     */
    public function setData(array $initialData): self
    {
        $this->DATA = $initialData;
        return $this;
    }

    /**
     * @param $id
     * @param string $component
     * @param array $initialData
     * @return static
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    public static function getInstance($id = null, string $component = self::GENERATED_TYPE, array $initialData = [])
    {
        if (!$component) {
            $component = static::GENERATED_TYPE;
        }
        if (!$id || !$component) {
            return new static();
        }
        $code = Instantiator::getInstanceCode($component, $id);

        if (InstanceCache::inCache($code)) {
            $cached = InstanceCache::load($code);
            $cached->hydrate([]);
            return $cached;
        }

        if (count($initialData) > 0) {
            $r = new static($id, $component, $initialData);
            $r->setData($initialData);
            InstanceCache::store($code, $r);
            return InstanceCache::load($code);
        }
        /**
         * @var Schema $schema
         * @var DatabaseConnector $connection
         * @var QueryCaller $queryBuilder
         */
        list($caller, $connection, $schema) = Instantiator::getQueryCaller($component);
        $identifiers = $schema->getIdentifiers();

        foreach ($identifiers as $identifier) {
            $caller->andIntegerEqual($identifier->getColumn(), $id);
        }

        $data = $caller->selectDistinct();
        if (count($data) > 0) {
            $converter = new RawResultsToInstanceConverter($component, $data[0]);
            $itemData = $converter->parse();

            $r = new static($id, $component, $itemData);
            $r->setData($itemData);
            InstanceCache::store($code, $r);
            return InstanceCache::load($code);
        }

        return new static();
    }

    /**
     * @return bool
     */
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
     * @param string $component
     * @return AbstractInstance|null
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    public function convertToComponent(string $component = ''): ?AbstractInstance
    {
        return Instantiator::make($component, $this->getIdColumnValue());
    }


    public function hydrate(array $data)
    {
        if (count($data) === 0) {
            $this->UPDATED = [];
            return;
        }
        foreach ($data as $column => $datum) {
            $this->UPDATED[$column] = $datum;
        }
    }

    public function save(): self
    {
//        $isValid = true;
        $isUpdate = !$this->isAnonymous();
//        if($this->isAnonymous()){
//            $validationClassName = FactorySettings::getComponentCreateValidationClassName(static::GENERATED_TYPE);
//            if ($validationClassName) {
//                $isValid = $validationClassName::getInstance($this);
//            }
//            $isUpdate = false;
//        } else {
//            $validationClassName = FactorySettings::getComponentUpdateValidationClassName(static::GENERATED_TYPE);
//            if ($validationClassName) {
//                $isValid = $validationClassName::getInstance($this);
//            }
//        }
//
//        if (!$isValid) {
//            return -1;
//        }

        /**
         * @var Schema $schema
         * @var DatabaseConnector $connection
         * @var QueryCaller $queryBuilder
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

        if ($id > 0 && !$this->DATA[$origIdColumn]) {
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

    public function delete()
    {
        if ($this->isAnonymous()) {
            return $this;
        }

        /**
         * @var Schema $schema
         * @var DatabaseConnector $connection
         * @var QueryCaller $caller
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
     * @return QueryCaller
     * @throws SchemaNotDefinedException
     */
    public static function getQueryCaller()
    {
        /**
         * @var QueryCaller $caller
         */
        list($caller) = Instantiator::getQueryCaller(static::GENERATED_TYPE);
        return $caller;
    }

    /**
     * @param QueryCaller $queryCaller
     * @return array
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     * @throws Exception
     */
    public static function getMany(QueryCaller $queryCaller = null): array
    {
        if (!$queryCaller) {
            $queryCaller = static::getQueryCaller();
        }
        return Instantiator::makeResults(static::GENERATED_TYPE, $queryCaller->selectDistinct());
    }

    /**
     * @param QueryCaller $queryCaller
     * @return AbstractInstance|null
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    public static function getOne(QueryCaller $queryCaller = null)
    {
        if (!$queryCaller) {
            $queryCaller = static::getQueryCaller();
        }
        $queryCaller->pagination(1, 1);
        $r = Instantiator::makeResults(static::GENERATED_TYPE, $queryCaller->selectDistinct());
        if (count($r) > 0) {
            return $r[0];
        }
        return null;
    }

    /**
     * @param QueryCaller $queryCaller
     * @param string|null $countableField
     * @return int
     * @throws SchemaNotDefinedException
     */
    public static function getCount(QueryCaller $queryCaller = null, string $countableField = null): int
    {
        if (!$queryCaller) {
            $queryCaller = static::getQueryCaller();
        }

        if (!$countableField) {
            $schema = Schema::get(static::GENERATED_TYPE);
            $countableField = $schema->getCountableField();
        }

        if (!$countableField) {
            return 0;
        }

        return $queryCaller->count($countableField);
    }

    /**
     * @param QueryCaller $queryCaller
     * @param string|null $countableField
     * @return int
     * @throws SchemaNotDefinedException
     */
    public static function getAmountOfPages(QueryCaller $queryCaller = null, string $countableField = null): int
    {
        $total = static::getCount($queryCaller, $countableField);
        if ($total === 0) {
            return 0;
        }
        $schema = Schema::get(static::GENERATED_TYPE);
        $itemsPerPage = $schema->getItemsPerPage();
        if ($itemsPerPage <= 0) {
            return 0;
        }

        return getTotalPages($total, $itemsPerPage);
    }

    /**
     * @param int $page
     * @param QueryCaller|null $queryCaller
     * @return array
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    public static function getPage(int $page, QueryCaller $queryCaller = null): array
    {
        if (!$queryCaller) {
            $queryCaller = static::getQueryCaller();
        }
        $schema = Schema::get(static::GENERATED_TYPE);
        $limit = $schema->getItemsPerPage();

        if ($limit >= 0) {
            $queryCaller->pagination($page, $limit);
        }

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
        return isset($this->PAGES[$fieldName])
            && isset($this->PAGES[$fieldName][$page])
            && is_array($this->PAGES[$fieldName][$page]);
    }

    protected function hasPageTotal(string $fieldName): bool
    {
        return isset($this->PAGES_TOTAL[$fieldName]);
    }
}