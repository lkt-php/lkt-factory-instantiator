<?php

namespace Lkt\Factory\Instantiator\Instances;

use Exception;
use Lkt\DatabaseConnectors\DatabaseConnector;
use Lkt\Factory\Instantiator\Cache\InstanceCache;
use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnBooleanTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnColorTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnDateTimeTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnEmailTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnFileTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnFloatTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnForeignListTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnForeignTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnIntegerTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnJsonTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnPivotTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnRelatedKeysTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnRelatedTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnStringTrait;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\RelatedField;
use Lkt\Factory\Schemas\Schema;
use Lkt\QueryCaller\QueryCaller;
use function Lkt\Tools\Arrays\compareArrays;

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
        ColumnDateTimeTrait;

    protected $TYPE;
    protected $DATA = [];
    protected $UPDATED = [];
    protected $PIVOT = [];
    protected $PIVOT_DATA = [];
    protected $UPDATED_PIVOT_DATA = [];
    protected $RELATED_DATA = [];
    protected $UPDATED_RELATED_DATA = [];
    protected $PENDING_UPDATE_RELATED_DATA = [];
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
    public static function getInstance($id = null, string $component = self::GENERATED_TYPE, array $initialData = []): self
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

        list($caller, $connection, $schema) = Instantiator::getQueryCaller($component);
        $identifiers = $schema->getIdentifiers();

        foreach ($identifiers as $identifier) {
            $caller->andIntegerEqual($identifier->getColumn(), $id);
        }

        $data = $caller->select();
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
    public function isAnonymous() :bool
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

    /**
     * @param array $data
     */
    public function hydrate(array $data)
    {
        if (count($data) === 0) {
            $this->UPDATED = [];
            return;
        }
        foreach ($data as $column => $datum){
            $this->UPDATED[$column] = $datum;
        }
    }

    public function save(): self
    {
//        $isValid = true;
        $isUpdate = true;
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

        } elseif($this->DATA[$origIdColumn] > 0) {
            $id = $this->DATA[$origIdColumn];
        }

        if ($queryResponse !== false){
            foreach ($this->UPDATED as $k => $v){
                $this->DATA[$k] = $v;
                unset($this->UPDATED[$k]);
            }
        }

        if (count($this->PENDING_UPDATE_RELATED_DATA) > 0){
            foreach ($this->PENDING_UPDATE_RELATED_DATA as $column => $data){

                /** @var RelatedField $field */
                $field = $schema->getField($column);
                $relatedComponent = $field->getComponent();

                $relatedSchema = Schema::get($field->getComponent());

                $relatedIdColumn = $relatedSchema->getIdColumn()[0];
                $relatedIdColumnGetter = 'get'.ucfirst($relatedIdColumn);
                $relatedClass = $relatedSchema->getInstanceSettings()->getAppClass();

                $create = $relatedSchema->getCreateHandler();
                $update = $relatedSchema->getUpdateHandler();
                $delete = $relatedSchema->getDeleteHandler();

                // Check which items must be deleted
                $currentItems = $this->_getRelatedVal($relatedComponent, $column, true);
                $currentIds = [];
                foreach ($currentItems as $currentItem){
                    $idAux = (int)$currentItem->{$relatedIdColumnGetter}();
                    if ($idAux > 0 && !in_array($idAux, $currentIds, true)){
                        $currentIds[] = $idAux;
                    }
                }

                $updatedIds = [];
                foreach ($data as $datum){
                    if ($datum[$relatedIdColumn] > 0){
                        $updatedIds[] = (int)$datum[$relatedIdColumn];
                    }
                }

                $diff = compareArrays($currentIds, $updatedIds);

                // Delete
                foreach ($diff['deleted'] as $deletedId) {
                    $delete::getInstance($relatedClass::getInstance($deletedId));
                }


                // Update or create
                foreach ($data as $datum){
                    if ($datum[$relatedIdColumn] > 0){
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
            $cacheCode = Instantiator::getInstanceCode($this->TYPE, $id);
            InstanceCache::clearCode($cacheCode);
            return Instantiator::make($this->TYPE, $id);

//            $cacheCode = "{$this->TYPE}_{$id}";
//            InstanceFactory::getInstance($this->TYPE, $id)->query();
//
//
//            $class = FactorySettings::getComponentClassName($this->TYPE);
//            if($class === get_class($this)){
//                InstanceGenerator::store($cacheCode, $this);
//            }
//
//            if (InstanceCache::inCache($cacheCode)) {
//                $data = InstanceCache::load($cacheCode);
//                foreach ($data as $key => $datum) {
//                    $this->DATA[$key] = $datum;
//                }
//            }
//
//            $this->RELATED_DATA = [];
        }

        return $this;
    }

    public function delete()
    {
        if ($this->isAnonymous()){
            return 1;
        }

        /**
         * @var Schema $schema
         * @var DatabaseConnector $connection
         * @var QueryCaller $queryBuilder
         */
        list($queryBuilder, $connection, $schema) = Instantiator::getQueryCaller(static::GENERATED_TYPE);

        $origIdColumn = $schema->getIdColumn();
        $origIdColumn = $origIdColumn[0];
        $idColumn = $schema->getField($origIdColumn);
        $idColumn = $idColumn->getColumn();
        $id = (int)$this->DATA[$origIdColumn];
        $queryBuilder->andIntegerEqual($idColumn, $id);
        $query = $connection->getDeleteQuery($queryBuilder);

        $queryResponse = $connection->query($query);
        if ($queryResponse === true) {
            $cacheCode = Instantiator::getInstanceCode($this->TYPE, $id);
            InstanceCache::clearCode($cacheCode);
        }
        return null;
    }

    /**
     * @return QueryCaller
     * @throws SchemaNotDefinedException
     */
    public static function getQueryCaller(): QueryCaller
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
    public static function getMany(QueryCaller $queryCaller): array
    {
        return Instantiator::makeResults(static::GENERATED_TYPE, $queryCaller->select());
    }

    /**
     * @param QueryCaller $queryCaller
     * @return AbstractInstance|null
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    public static function getOne(QueryCaller $queryCaller): ?AbstractInstance
    {
        $queryCaller->pagination(1, 1);
        $r = Instantiator::makeResults(static::GENERATED_TYPE, $queryCaller->select());
        if (count($r) > 0) {
            return $r[0];
        }
        return null;
    }
}