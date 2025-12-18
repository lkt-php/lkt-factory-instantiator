<?php

namespace Lkt\Factory\Instantiator\Instances;

use Exception;
use Lkt\Connectors\Cache\QueryCache;
use Lkt\Factory\Instantiator\Cache\InstanceCache;
use Lkt\Factory\Instantiator\Conversions\InstanceToArray;
use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Instantiator\Enums\CrudOperation;
use Lkt\Factory\Instantiator\Exceptions\InvalidCountableFieldException;
use Lkt\Factory\Instantiator\Exceptions\UnsetFieldStorePathException;
use Lkt\Factory\Instantiator\Helpers\FileUploadHelper;
use Lkt\Factory\Instantiator\Helpers\QueryBuilderHelper;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnBooleanTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnColorTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnCompositionTrait;
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnConcatTrait;
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
use Lkt\Factory\Instantiator\Instances\AccessDataTraits\ColumnValueListTrait;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Instantiator\ValueObjects\ComponentDatabaseIntegration;
use Lkt\Factory\Instantiator\ValueObjects\MonthlyAccuratePages;
use Lkt\Factory\Schemas\Enums\AccessPolicyEndOfLife;
use Lkt\Factory\Schemas\Exceptions\InvalidComponentException;
use Lkt\Factory\Schemas\Exceptions\InvalidSchemaAppClassException;
use Lkt\Factory\Schemas\Exceptions\MissedMandatoryValueException;
use Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException;
use Lkt\Factory\Schemas\Fields\AbstractField;
use Lkt\Factory\Schemas\Fields\BooleanField;
use Lkt\Factory\Schemas\Fields\ColorField;
use Lkt\Factory\Schemas\Fields\DateTimeField;
use Lkt\Factory\Schemas\Fields\EmailField;
use Lkt\Factory\Schemas\Fields\EncryptField;
use Lkt\Factory\Schemas\Fields\FileField;
use Lkt\Factory\Schemas\Fields\FloatField;
use Lkt\Factory\Schemas\Fields\ForeignKeyField;
use Lkt\Factory\Schemas\Fields\ForeignKeysField;
use Lkt\Factory\Schemas\Fields\HTMLField;
use Lkt\Factory\Schemas\Fields\IdField;
use Lkt\Factory\Schemas\Fields\IntegerChoiceField;
use Lkt\Factory\Schemas\Fields\IntegerField;
use Lkt\Factory\Schemas\Fields\JSONField;
use Lkt\Factory\Schemas\Fields\MethodGetterField;
use Lkt\Factory\Schemas\Fields\PivotField;
use Lkt\Factory\Schemas\Fields\PivotLeftIdField;
use Lkt\Factory\Schemas\Fields\PivotPositionField;
use Lkt\Factory\Schemas\Fields\RelatedField;
use Lkt\Factory\Schemas\Fields\RelatedKeysField;
use Lkt\Factory\Schemas\Fields\StringChoiceField;
use Lkt\Factory\Schemas\Fields\StringField;
use Lkt\Factory\Schemas\Fields\UnixTimeStampField;
use Lkt\Factory\Schemas\Fields\ValueListField;
use Lkt\Factory\Schemas\Schema;
use Lkt\Factory\Schemas\ValueObjects\AccessPolicyUsage;
use Lkt\Locale\Locale;
use Lkt\QueryBuilding\Query;
use Lkt\QueryBuilding\SelectBuilder;
use Lkt\Translations\Translations;
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
        ColumnRelatedKeysMergeTrait,
        ColumnValueListTrait,
        ColumnConcatTrait,
        ColumnCompositionTrait;

    protected $TYPE;
    protected array $DATA = [];
    protected array $UPDATED = [];
    protected array $UPLOADING_FILES = [];
    protected array $PIVOT = [];
    protected array $PIVOT_DATA = [];
    protected array $UPDATED_PIVOT_DATA = [];
    protected array $RELATED_DATA = [];
    protected array $UPDATED_RELATED_DATA = [];
    protected array $PENDING_UPDATE_RELATED_DATA = [];
    protected array $PAGES = [];
    protected array $PAGES_TOTAL = [];

    /** @deprecated */
    const GENERATED_TYPE = '';
    const COMPONENT = '';

    protected array $DECRYPT = [];
    protected array $DECRYPT_UPDATED = [];

    protected AccessPolicyUsage|null $accessPolicy;

    /**
     * @param string|null $component
     * @param array $initialData
     */
    public function __construct(string $component = null, array $initialData = [])
    {
        if (!$component && static::COMPONENT) {
            $component = static::COMPONENT;
        }
        if (!$component && static::GENERATED_TYPE) {
            $component = static::GENERATED_TYPE;
        }
        $this->TYPE = $component;
        $this->DATA = $initialData;
    }

    public function setAccessPolicy(string $accessPolicy, AccessPolicyEndOfLife $accessPolicyEndOfLife = AccessPolicyEndOfLife::UntilUpdated): static
    {
        $this->accessPolicy = new AccessPolicyUsage(static::COMPONENT, $accessPolicy, $accessPolicyEndOfLife);
        return $this;
    }

    public function setData(array $initialData): static
    {
        $schema = Schema::get(static::COMPONENT);

        foreach ($initialData as $column => $datum) {
            $field = $schema->getField($column);
            if ($field && $field->hasDefaultValue()) {
                $initialData[$column] = $field->ensureDefaultValue($initialData[$column]);
            }
        }


        $this->DATA = $initialData;
        return $this;
    }

    /**
     * @throws InvalidComponentException
     * @throws SchemaNotDefinedException
     */
    public static function getInstance($id = null, string $component = self::COMPONENT, array $initialData = []): static
    {
        if (!$component) $component = static::COMPONENT;
        if (!$id || !$component) {
            $r = new static();

            $schema = Schema::get($component);
            $fields = $schema->getChoiceFieldsWithDefaultValue();

            if (count($fields)) {
                foreach ($fields as $field) {
                    $setter = $field->getSetterForPrimitiveValue();
                    $r->{$setter}($field->getEmptyDefault());
                }
            }

            $fields = $schema->getFieldsWithDefaultValue();

            if (count($fields)) {
                foreach ($fields as $field) {
                    $setter = $field->getSetterForPrimitiveValue();
                    $r->{$setter}($field->getDefaultValue());
                }
            }

            return $r;
        }

        $codeId = is_array($id) ? implode('-', $id) : $id;

        $code = Instantiator::getInstanceCode($component, $codeId);

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

        $dbIntegration = ComponentDatabaseIntegration::from($component);
        $builder = $dbIntegration->query;
        $schema = $dbIntegration->schema;

        $identifiers = $schema->getIdentifiers();

        if (is_array($id) && $schema->hasComplexPrimaryKey()){
            foreach ($identifiers as $identifier) $builder->andIntegerEqual($identifier->getColumn(), $id[$identifier->getName()]);

        } else {
            foreach ($identifiers as $identifier) $builder->andIntegerEqual($identifier->getColumn(), $id);
        }


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
        $schema = Schema::get(static::COMPONENT);
        $idColumn = $schema->getIdString();
        return $this->DATA[$idColumn];
    }

    /**
     * @throws InvalidComponentException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     * @deprecated
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



        $dbIntegration = ComponentDatabaseIntegration::from(static::COMPONENT);
        $queryBuilder = $dbIntegration->query;
        $connection = $dbIntegration->databaseConnector;
        $schema = $dbIntegration->schema;

//        /**
//         * @var Schema $schema
//         * @var DatabaseConnector $connection
//         * @var Query $queryBuilder
//         */
//        list($queryBuilder, $connection, $schema) = Instantiator::getQueryCaller(static::COMPONENT);

        // Create only: set default values
        if (!$isUpdate) {
            /** @var AbstractField $fieldsWithDefaultValue */
            $fieldsWithDefaultValue = $schema->getFieldsWithDefaultValue();
            foreach ($fieldsWithDefaultValue as $fieldWithDefaultValue) {
                $defaultValueKey = $fieldWithDefaultValue->getName();
                if (isset($this->UPDATED[$defaultValueKey])) continue;

                $defaultValueKey = $fieldWithDefaultValue->getName().'Id';
                if (isset($this->UPDATED[$defaultValueKey])) continue;

                $defaultValue = $fieldWithDefaultValue->getDefaultValue();
                $setter = $fieldWithDefaultValue->getSetterForPrimitiveValue();
                $this->{$setter}($defaultValue);
            }
        }


        $fileFields = $schema->getFileFields();

        $pendingUploadBase64Files = [];
        $pendingUploadBase64MultipleFiles = [];
        if (count($this->UPDATED) > 0) {
            // Check if it's needed to store a base64 file:
            foreach ($fileFields as $fileField) {
                if ($this->_fileValUpdatedWithBase64Data($fileField->getName())) {
                    $storePath = $fileField->getStorePath($this);
                    if ($storePath === ''){
                        throw UnsetFieldStorePathException::getInstance($fileField->getName(), $schema->getComponent());
                    }

                    if ($fileField->isMultiple()) {
                        $pendingUploadBase64MultipleFiles[$fileField->getName()] = $this->UPDATED[$fileField->getName()];
                        $this->UPDATED[$fileField->getName()] = [];

                    } else {
                        $pendingUploadBase64Files[$fileField->getName()] = $this->UPDATED[$fileField->getName()];
                        $this->UPDATED[$fileField->getName()] = '';
                    }
                }
            }
        }

        foreach ($schema->getMandatoryFields() as $mandatoryField) {
            $checkerMethod = $mandatoryField->getGetterForChecker();
            if (!$this->{$checkerMethod}()) {
                $additionalFieldsToColumn =  array_filter($schema->getFields(), function (AbstractField $field) use ($mandatoryField) {
                    return $field->getName() !== $mandatoryField->getName()
                        && $field->getColumn() === $mandatoryField->getColumn();
                });
                $ok = false;
                if (count($additionalFieldsToColumn) > 0) {
                    foreach ($additionalFieldsToColumn as $additionalFieldToColumn) {
                        $additionalCheckerMethod = $additionalFieldToColumn->getGetterForChecker();
                        $ok = $ok || $this->{$additionalCheckerMethod}();
                    }
                }

                if (!$ok) {
                    throw MissedMandatoryValueException::getInstance($schema->getComponent() . '.' .$mandatoryField->getName());
                }
            }
        }

        $parsed = $connection->prepareDataToStore($schema, $this->UPDATED);

        $origIdColumn = $schema->getIdColumn();
        $origIdColumn = $origIdColumn[0];

        $id = 0;

        if (count($this->UPDATED) > 0) {
            // Save current instance process
            $queryBuilder->updateData($parsed);

            if ($isUpdate) {
                if ($schema->hasComplexPrimaryKey()) {
                    $identifiers = $schema->getIdentifiers();
                    foreach ($identifiers as $identifier) {
                        $originalValueKey = $identifier->getGetterForPrimitiveValue();
                        $originalValueKey = lcfirst(substr($originalValueKey, 3));
                        $queryBuilder->andIntegerEqual($identifier->getColumn(), $this->DATA[$originalValueKey]);
                    }
                    $query = $connection->getUpdateQuery($queryBuilder);

                } elseif ($schema->isPivot()) {
                    $pivotColumns = $schema->getIdColumn();
                    foreach ($pivotColumns as $pivotColumn) {
                        $idColumn = $schema->getField($pivotColumn);
                        $originalValueKey = $idColumn->getGetterForPrimitiveValue();
                        $originalValueKey = lcfirst(substr($originalValueKey, 3));
                        $idColumn = $idColumn->getColumn();
                        $queryBuilder->andIntegerEqual($idColumn, $this->DATA[$originalValueKey]);
                    }
                    $query = $connection->getUpdateQuery($queryBuilder);
                } else {
                    $idColumn = $schema->getField($origIdColumn);
                    $idColumn = $idColumn->getColumn();
                    $queryBuilder->andIntegerEqual($idColumn, $this->DATA[$origIdColumn]);
                    $query = $connection->getUpdateQuery($queryBuilder);
                }
            } else {
                $query = $connection->getInsertQuery($queryBuilder);
            }

            $queryResponse = $connection->query($query);

            if ($queryResponse !== false) {
                foreach ($this->UPDATED as $k => $v) {
                    $this->DATA[$k] = $v;
                    unset($this->UPDATED[$k]);
                }
            }

            $id = (int)$connection->getLastInsertedId();
            $reload = true;
        }

        // Get current instance ID (if it's been created)
        if ($id > 0 && (!isset($this->DATA[$origIdColumn]) || !$this->DATA[$origIdColumn])) {
            $this->DATA[$origIdColumn] = $id;

        } elseif ($this->DATA[$origIdColumn] > 0) {
            $id = $this->DATA[$origIdColumn];
        }

        $hasToReUpdate = false;

        if (count($pendingUploadBase64Files) > 0) {
            foreach ($pendingUploadBase64Files as $fileFieldName => $fileFieldValue) {
                $this->_storeBase64DataAsFile($fileFieldName, $fileFieldValue, $id);
                $hasToReUpdate = true;
            }
        }

        if (count($pendingUploadBase64MultipleFiles) > 0) {
            foreach ($pendingUploadBase64MultipleFiles as $fileFieldName => $fileFieldValue) {
                $this->_storeBase64DataAsFiles($fileFieldName, $fileFieldValue, $id);
                $hasToReUpdate = true;
            }
        }

        if (count($this->UPLOADING_FILES) > 0) {
            // Check if it's needed to store a base64 file:
            foreach ($fileFields as $fileField) {
                $key = $fileField->getName();
                if (is_array($this->UPLOADING_FILES[$key])) {
                    $uploadData = FileUploadHelper::uploadFileField($fileField, $this->UPLOADING_FILES[$key], $this, $schema);

                    if (is_array($uploadData)) {
                        $this->_setFileVal($key, $uploadData['name']);
                        $hasToReUpdate = true;
                    }
                    unset($this->UPLOADING_FILES[$key]);
                }
            }
        }

        // Update relational data
        if (count($this->PENDING_UPDATE_RELATED_DATA) > 0) {
            foreach ($this->PENDING_UPDATE_RELATED_DATA as $column => $data) {

                if (!$isUpdate && count($data) === 0) continue;

                /** @var RelatedField $field */
                $field = $schema->getField($column);
                $relatedComponent = $field->getComponent();

                if ($field instanceof ForeignKeysField && count($data) === 0) {
                    $currentItems = $this->_getForeignListData($column);
                    if (count($currentItems) === 0) continue;
                }

                if (method_exists($field, 'getDynamicComponentField')) { // Check due to RelatedField not implementing this feature yet
                    $dynamicComponentFieldName = $field->getDynamicComponentField();
                    if ($dynamicComponentFieldName !== '') {
                        $dynamicComponentField = $schema->getField($dynamicComponentFieldName);
                        $getter = $dynamicComponentField->getGetterForPrimitiveValue();
                        $dynamicType = $this->{$getter}();
                        if ($dynamicType !== '') $relatedComponent = $dynamicType;
                    }
                }

                $relatedSchema = Schema::get($relatedComponent);

                $relatedIdColumn = $relatedSchema->getIdColumn()[0];
                $relatedIdColumnGetter = 'get' . ucfirst($relatedIdColumn);
                $relatedClass = $relatedSchema->getInstanceSettings()->getAppClass();

                $relatedMode = false;
                $foreignKeysMode = false;

                $foreignKeysIds = [];

                // Check which items must be deleted
                if ($field instanceof RelatedField) {
                    $relatedMode = true;
                    $currentItems = $this->_getRelatedVal($relatedComponent, $column, true);

                } elseif ($field instanceof ForeignKeysField) {
                    $foreignKeysMode = true;
                    $currentItems = $this->_getForeignListData($column);
                }

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
                if (method_exists($field, 'hasToAutoRemoveUnlinked') && $field->hasToAutoRemoveUnlinked()) {
                    foreach ($diff['deleted'] as $deletedId) {
                        $ins = $relatedClass::getInstance($deletedId);
                        $ins->delete();
                    }
                }

                if ($relatedMode) {
                    $relatedForeignKeyColumn = $relatedSchema->getField($field->getColumn());
                    $relatedForeignKeyKey = $relatedForeignKeyColumn->getName();
                    if ($relatedForeignKeyColumn instanceof ForeignKeyField) {
                        if (!$relatedForeignKeyColumn->keyIsId($relatedForeignKeyKey)) {
                            $relatedForeignKeyKey .= 'Id';
                        }
                    }
                }


                // Update or create
                foreach ($data as $datum) {
                    if ($relatedMode && !$datum[$relatedForeignKeyKey]) {
                        $datum[$relatedForeignKeyKey] = $this->getIdColumnValue();
                    }

                    if ($datum[$relatedIdColumn] > 0) {
                        $ins = $relatedClass::getInstance($datum[$relatedIdColumn]);
                        $ins::feedInstance($ins, $datum, 'update');
                        $ins->save();

                    } else {
                        $ins = $relatedClass::getInstance();
                        $ins::feedInstance($ins, $datum, 'create');
                        $ins->save();
                    }

                    if ($foreignKeysMode) $foreignKeysIds[] = $ins->getId();
                }

                if ($foreignKeysMode && count($foreignKeysIds) > 0) {
                    $setter = 'set' . ucfirst($field->getName());
                    $this->{$setter}($foreignKeysIds);
                    $hasToReUpdate = true;
                }
            }
            $this->PENDING_UPDATE_RELATED_DATA = [];
        }

        if ($hasToReUpdate) {
            $this->save();
        }

        if (count($this->PIVOT_SORT) > 0) {
            foreach ($this->PIVOT_SORT as $column => $ids) {

                $ownField = $schema->getPivotField($column);

                // Pivot table fields (intermediate table)
                $pivotSchema = $ownField->getPivotSchema();

                $pointingField = $pivotSchema->getOneFieldPointingToComponent(static::COMPONENT);

                if ($pointingField instanceof PivotLeftIdField) {
                    $referencedField = $pivotSchema->getPivotRightIdField();
                } else {
                    $referencedField = $pivotSchema->getPivotLeftIdField();
                }

                /** @var PivotPositionField $positionField */
                $positionField = $pivotSchema->getOnePositionField();

                $positionGetter = $positionField->getGetterForPrimitiveValue();
                $positionSetter = $positionField->getSetterForPrimitiveValue();
                $referencedGetter = $referencedField->getGetterForPrimitiveValue();
                $referencedSetter = $referencedField->getSetterForPrimitiveValue();
                $pointingSetter = $pointingField->getSetterForPrimitiveValue();

                $results = $this->_getPivots($ownField->getName());

                $checkedIds = [];

                // Update existing pivots
                foreach ($results as $result) {
                    $id = $result->{$referencedGetter}();
                    $updatedPosition = array_search($id, $ids);
                    $checkedIds[] = $id;

                    $position = $result->{$positionGetter}();

                    if ($updatedPosition !== $position) {
                        $result
                            ->{$positionSetter}($updatedPosition)
                            ->save();
                    }

                    // Unlink pivot relation
                    if (!in_array($id, $ids, true)) {
                        $result->delete();
                    }
                }

                // Link new pivot relations
                foreach ($ids as $i => $id) {
                    if (!in_array($id, $checkedIds, true)) {
                        $ins = $pivotSchema->getItemInstance();
                        $ins
                            ->{$pointingSetter}($this->getIdColumnValue())
                            ->{$referencedSetter}($id)
                            ->{$positionSetter}($i)
                            ->save();
                    }
                }
            }
        }
        $this->_saveCompositionValues();

        if ($reload) {
            $cacheCode = Instantiator::getInstanceCode(static::COMPONENT, $id);
            InstanceCache::clearCode($cacheCode);
            return Instantiator::make(static::COMPONENT, $id);
        }

        return $this;
    }

    public function delete(): static
    {
        if ($this->isAnonymous()) return $this;

        $dbIntegration = ComponentDatabaseIntegration::from(static::COMPONENT);
        $caller = $dbIntegration->query;
        $connection = $dbIntegration->databaseConnector;
        $connector = $dbIntegration->databaseConnectorName;
        $schema = $dbIntegration->schema;

//        /**
//         * @var Schema $schema
//         * @var DatabaseConnector $connection
//         * @var Query $caller
//         */
//        list($caller, $connection, $schema, $connector) = Instantiator::getQueryCaller(static::COMPONENT);


        if ($schema->isPivot()) {
            $pivotColumns = $schema->getIdColumn();
            foreach ($pivotColumns as $pivotColumn) {
                $idColumn = $schema->getField($pivotColumn);
                $originalValueKey = $idColumn->getGetterForPrimitiveValue();
                $originalValueKey = lcfirst(substr($originalValueKey, 3));
                $idColumn = $idColumn->getColumn();
                $caller->andIntegerEqual($idColumn, $this->DATA[$originalValueKey]);
            }

        } else {

            $origIdColumn = $schema->getIdColumn();
            $origIdColumn = $origIdColumn[0];
            $idColumn = $schema->getField($origIdColumn);
            $idColumn = $idColumn->getColumn();
            $id = (int)$this->DATA[$origIdColumn];
            $caller->andIntegerEqual($idColumn, $id);
        }

        $connection->query($connection->getDeleteQuery($caller));
        $cacheCode = Instantiator::getInstanceCode(static::COMPONENT, $id);
        InstanceCache::clearCode($cacheCode);
        $query = $connection->getSelectQuery($caller);
        QueryCache::set($connector, $query, []);
        $this->DATA = [];
        $this->UPDATED = [];
        $this->RELATED_DATA = [];
        $this->PIVOT = [];
        $this->PIVOT_DATA = [];
        $this->PIVOT_SORT = [];
        $this->UPDATED_RELATED_DATA = [];
        $this->PENDING_UPDATE_RELATED_DATA = [];
        return $this;
    }

    /**
     * @deprecated
     * @return Query
     * @throws SchemaNotDefinedException
     */
    public static function getQueryCaller()
    {
        return QueryBuilderHelper::getComponentQuery(static::COMPONENT);
//        $dbIntegration = ComponentDatabaseIntegration::from(static::COMPONENT);
//        return $dbIntegration->query;
    }

    /**
     * @return Query
     * @throws SchemaNotDefinedException
     */
    public static function getQueryBuilder()
    {
        return QueryBuilderHelper::getComponentQuery(static::COMPONENT);
//        $dbIntegration = ComponentDatabaseIntegration::from(static::COMPONENT);
//        return $dbIntegration->query;
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
        return Instantiator::makeResults(static::COMPONENT, $queryCaller->selectDistinct());
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
        $r = Instantiator::makeResults(static::COMPONENT, $queryCaller->selectDistinct());
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
            $schema = Schema::get(static::COMPONENT);
            $countableField = $schema->getCountableField();
        }

        if (!$countableField) return 0;

        return $queryCaller->count($countableField);
    }

    /**
     * @throws SchemaNotDefinedException
     */
    public static function getAmountOfPages(Query $queryCaller = null, string $countableField = null, int $itemsPerPage = 0): int
    {
        $total = static::getCount($queryCaller, $countableField);
        if ($total === 0) return 0;
        $schema = Schema::get(static::COMPONENT);
        if ($itemsPerPage <= 0) $itemsPerPage = $schema->getItemsPerPage();
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
    public static function getPage(int $page, Query $queryCaller = null, int $itemsPerPage = 0): array
    {
        if (!$queryCaller) $queryCaller = static::getQueryCaller();
        $schema = Schema::get(static::COMPONENT);
        $limit = $itemsPerPage;
        if ($limit <= 0) $limit = $queryCaller->getLimit();
        if ($limit <= 0) $limit = $schema->getItemsPerPage();
        if ($limit >= 0) $queryCaller->pagination($page, $limit);
        return Instantiator::makeResults(static::COMPONENT, $queryCaller->selectDistinct());
    }

    /**
     * @param int $page
     * @param Query|null $queryCaller
     * @param string|null $countableField
     * @return array
     * @throws InvalidComponentException
     * @throws InvalidCountableFieldException
     * @throws InvalidSchemaAppClassException
     * @throws SchemaNotDefinedException
     */
    public static function getMonthlyAccuratePage(int $page, Query $queryCaller = null, string $countableField = null): array
    {
        if (!$queryCaller) $queryCaller = static::getQueryBuilder();
        $originalSelect = $queryCaller->getColumns();
        $pagesValueObject = static::getMonthlyAccuratePages($queryCaller, $countableField);
        $queryCaller->setColumns($originalSelect);
        $month = $pagesValueObject->getPageYearMonth($page);

        if (is_null($month)) {
            return [];
        }

        $queryCaller->andExtractYearMonthEqual($countableField, $month);
        return Instantiator::makeResults(static::COMPONENT, $queryCaller->selectDistinct());
    }

    /**
     * @param Query|null $query
     * @param string|null $countableField
     * @param int $itemsPerPage
     * @return MonthlyAccuratePages
     * @throws InvalidCountableFieldException
     * @throws SchemaNotDefinedException
     */
    public static function getMonthlyAccuratePages(Query $query = null, string $countableField = null): MonthlyAccuratePages
    {
        if (!$countableField) throw InvalidCountableFieldException::getInstance(__METHOD__, static::COMPONENT);

        if (!$query) $query = static::getQueryBuilder();

        $query->setColumns(SelectBuilder::extractYearMonthDatum($countableField, 'countable_datum'));

        $results = $query->selectDistinct();

        $data = array_unique(array_map(function ($item) {
            return (int)$item['countable_datum'];
        }, $results));

        return new MonthlyAccuratePages($data);
    }

    public function getComponent(): string
    {
        return static::COMPONENT;
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

    protected function prepareCrudData(array $data, CrudOperation|null $operation = null): array
    {
        return $data;
    }

    protected function patchReadData(array $data): array
    {
        return $data;
    }

    public function autoRead(string $view = ''): array
    {
        $schema = Schema::get(static::COMPONENT);
        if ($this->accessPolicy) {
            $fields = $schema->getAccessPolicyFields($this->accessPolicy);
            $composedFields = $schema->getAccessPolicyComposedFields($this->accessPolicy);

        } else {
            $fields = $view ? $schema->getViewFields($view) : $schema->getAllFields();
            $composedFields = $schema->getComposedFields($view);
        }

        $fieldsStack = [...$fields, ...$composedFields];

        $r = $this->patchReadData($this->readFields($fieldsStack, $view));

        if ($this->accessPolicy && $this->accessPolicy->matchedEndOfLife(AccessPolicyEndOfLife::UntilNextRead)) {
            unset($this->accessPolicy);
        }

        return $r;
    }

    public function autoCreate(array $data): static
    {
        static::feedInstance($this, $this->prepareCrudData($data, CrudOperation::Create), CrudOperation::Create->value);
        return $this->save();
    }

    public function autoUpdate(array $data): static
    {
        static::feedInstance($this, $this->prepareCrudData($data, CrudOperation::Update), CrudOperation::Update->value);
        return $this->save();
    }

    public static function create(array $params): static
    {
        return (new static())->autoCreate($params);
    }

    public static function update(AbstractInstance $instance, array $params): static
    {
        return $instance->autoUpdate($params);
    }

    public static function feedInstance(AbstractInstance $instance, array $params, string $view = ''): static
    {
        $schema = Schema::get(static::COMPONENT);

        foreach ($params as $param => $value) {

            if ($schema->hasToExcludeFieldFromViewFeed($view, $param)) continue;

            $field = $schema->getField($param);

            if ($field instanceof StringChoiceField) {
                $instance->_setStringChoiceVal($param, clearInput($value));

            } elseif ($field instanceof ValueListField) {
                $instance->_setValueListVal($param, $value);

            } elseif ($field instanceof StringField || $field instanceof EmailField || $field instanceof HTMLField) {
                $instance->_setStringVal($param, clearInput($value));

            } elseif ($field instanceof DateTimeField) {
                $instance->_setDateTimeVal($param, $value);

            } elseif ($field instanceof EncryptField) {
                $instance->_setEncryptVal($param, $value);

            } elseif ($field instanceof ForeignKeyField) {
                if ($field->keyIsId($param)) {
                    $instance->_setIntegerVal($field->getName() . 'Id', $value);

                } else {
//                    $instance->_setForeignListWithData($param, $value);
                }
            } elseif ($field instanceof IntegerChoiceField && !$field->isMultiple()) {
                $instance->_setIntegerChoiceVal($param, (int)$value);

            } elseif ($field instanceof IntegerChoiceField) {
                $instance->_setIntegerChoiceVal($param, $value);

            } elseif ($field instanceof IntegerField && !($field instanceof IdField) && !$field->isMultiple()) {
                $instance->_setIntegerVal($param, (int)$value);

            } elseif ($field instanceof IntegerField && $field->isMultiple()) {
                $instance->_setIntegerVal($param, $value);

            } elseif ($field instanceof FloatField) {
                $instance->_setFloatVal($param, (float)$value);

            } elseif ($field instanceof JSONField) {
                $instance->_setJsonVal($param, $value);

            } elseif ($field instanceof ColorField) {
                $instance->_setColorVal($param, $value);

            } elseif ($field instanceof RelatedKeysField) {
                $instance->_setRelatedKeysValWithData($param, $value);

            } elseif ($field instanceof RelatedField) {
                if ($field->isSingleMode()) {
                    $instance->_setRelatedValWithData('', $param, [$value]);
                } else {
                    $instance->_setRelatedValWithData('', $param, $value);
                }

            } elseif ($field instanceof BooleanField) {
                $instance->_setBooleanVal($param, $value);

            } elseif ($field instanceof ForeignKeysField) {
                if ($field->keyIsIds($param)) {
                    $instance->_setForeignListVal($field->getName(), $value);

                } else {
                    $instance->_setForeignListWithData($param, $value);
                }

            } elseif ($field instanceof FileField) {
                $instance->_setFileVal($param, $value);

            } elseif ($field instanceof PivotField) {
                $instance->_setPivotSort($param, $value);
            }
        }

        return $instance;
    }


    public function readViewFields(string $view): array
    {
        $schema = Schema::get(static::COMPONENT);

        $r = $this->readFields($schema->getViewFields($view), $view);

        $schema = Schema::get(static::COMPONENT);

        // Option value
        $field = $schema->getRelatedModeValueField();
        if ($field instanceof AbstractField) {
            $getter = $field->getGetterForPrimitiveValue();
            $r['value'] = $this->{$getter}();
        }

        // Option label
        $field = $schema->getRelatedModeLabelField();
        if ($field instanceof AbstractField) {
            $getter = $field->getGetterForPrimitiveValue();
            $r['label'] = $this->{$getter}();
        }

        return $r;
    }


    /**
     * @param AbstractField[] $fields
     * @return array
     */
    public function readFields(array $fields = [], string $view = ''): array
    {
        $r = [];
        foreach ($fields as $field) {
            if ($field instanceof RelatedField) {
                $getter = $field->getGetterForPrimitiveValue();
                $items = $this->{$getter}();

                if ($field->isSingleMode()) {
                    if (is_object($items)) {
                        $r[$field->getCustomViewName($view)] = $items->readAsRelated();
                    } elseif ($field->hasToReturnsEmptyOneInSingleMode()) {
                        $anonymous = Instantiator::make($field->getComponent(), 0);
                        $r[$field->getCustomViewName($view)] = $anonymous->readAsRelated();
                    }

                } else {
                    $t = [];
                    foreach ($items as $item) {
                        $t[] = $item->readAsRelated();
                    }
                    $r[$field->getCustomViewName($view)] = $t;
                }

            } elseif ($field instanceof ForeignKeysField) {
                $getter = $field->getGetterForData();
                $getterIds = $field->getGetterForPrimitiveValue();
                $items = $this->{$getter}();
                if (!is_array($items)) $items = [];
                $t = [];
                foreach ($items as $item) {
                    $t[] = $item->readAsRelated();
                }
                $r[$field->getCustomViewName($view)] = $t;
                $r[$field->getCustomViewName($view) . 'Ids'] = $this->{$getterIds}();

            } elseif ($field instanceof ForeignKeyField) {
                $getter = $field->getGetterForData();
                $getterIds = $field->getGetterForPrimitiveValue();
                $item = $this->{$getter}();
                if ($item instanceof AbstractInstance) $item = $item->readAsRelated();
                if (!is_array($item)) $item = [];
                $r[$field->getCustomViewName($view)] = $item;
                if (method_exists($this, $getterIds)) {
                    $r[$field->getCustomViewName($view) . 'Id'] = $this->{$getterIds}();
                }

                if ($field->hasOnReadIncludeOptions()) {
                    $r[$field->getCustomViewName($view) . 'Opts'] = [$item];
                }

            } elseif ($field instanceof MethodGetterField) {
                $getter = $field->getName();

                $key = $field->getCustomViewName($view);
                if (!$key) $key = $field->getColumn();

                $r[$key] = $this->{$getter}();

            } elseif ($field instanceof FileField) {
                $val = '';
                if (!$this->isAnonymous()) {
                    $getter = $field->getGetterForPrimitiveValue().'PublicPath';
                    $val = $this->{$getter}();
                }
                $r[$field->getCustomViewName($view)] = $val;

            } elseif ($field instanceof DateTimeField || $field instanceof UnixTimeStampField) {
                $getter = $field->getGetterForPrimitiveValue();

                $format = $field->getLangDefaultReadFormat(Locale::getLangCode());
                if (!$format) $format = $field->getDefaultReadFormat();

                if ($format !== '') {
                    $r[$field->getCustomViewName($view)] = $this->{$getter . 'Formatted'}($format);

                } else {
                    $r[$field->getCustomViewName($view)] = $this->{$getter}();
                }

            } elseif ($field instanceof PivotField) {

                $getter = $field->getGetterForPrimitiveValue();
                $items = $this->{$getter}();
                if (!is_array($items)) $items = [];
                $t = [];
                foreach ($items as $item) {
                    $t[] = $item->readViewFields('related');


                }
                $r[$field->getCustomViewName($view)] = $t;

            } elseif ($field instanceof ValueListField) {
                $getter = $field->getGetterForPrimitiveValue();

                if ($field->readModeIsBoth()) {
                    $r[$field->getCustomViewName($view)] = $this->{$getter}();
                    $r[$field->getCustomViewName($view).'List'] = $this->{$getter.'AsArray'}();

                } elseif ($field->readModeIsString()) {
                    $r[$field->getCustomViewName($view)] = $this->{$getter}();

                } elseif ($field->readModeIsArray()) {
                    $r[$field->getCustomViewName($view)] = $this->{$getter.'AsArray'}();
                }

            } elseif ($field instanceof StringChoiceField) {
                $getter = $field->getGetterForPrimitiveValue();
                $value = $this->{$getter}();
                $r[$field->getCustomViewName($view)] = $value;
                $i18nOptions = $field->getI18nViewOptions();
                if ($i18nOptions !== '') {;
                    $r[$field->getCustomViewName($view) . 'Text'] = Translations::get($i18nOptions . ".{$value}", Locale::getLangCode());
                }

            } elseif ($field instanceof AbstractField) {
                $getter = $field->getGetterForPrimitiveValue();
                $r[$field->getCustomViewName($view)] = $this->{$getter}();
            }
        }

        if (method_exists($this, 'postProcessRead')) return $this->postProcessRead($r);
        return $r;
    }


    /**
     * @param AbstractField[] $fields
     * @return array
     */
    public function readAsRelated(): array
    {
        $schema = Schema::get(static::COMPONENT);

        $r = [];

        // Option value
        $field = $schema->getRelatedModeValueField();
        if ($field instanceof AbstractField) {
            $getter = $field->getGetterForPrimitiveValue();
            $r['value'] = $this->{$getter}();
        }

        // Option label
        $field = $schema->getRelatedModeLabelField();
        if ($field instanceof AbstractField) {
            $getter = $field->getGetterForPrimitiveValue();
            $r['label'] = $this->{$getter}();
        }

        // Additional data
        $fields = $schema->getRelatedModeAdditionalFields();
        $r = [...$r, ...$this->readFields($fields, 'related')];

        return $r;
    }

    public function linkPivot(string $pivotComponent, $id): static
    {
        $pivotSchema = Schema::get($pivotComponent);

        $pointingField = $pivotSchema->getOneFieldPointingToComponent(static::COMPONENT);

        if ($pointingField instanceof PivotLeftIdField) {
            $referencedField = $pivotSchema->getPivotRightIdField();
        } else {
            $referencedField = $pivotSchema->getPivotLeftIdField();
        }

        /** @var PivotPositionField $positionField */
        $positionField = $pivotSchema->getOnePositionField();

        $pivotQueryBuilder = QueryBuilderHelper::getComponentQuery($pivotComponent);

//        /** @var Query $queryBuilder */
//        list($pivotQueryBuilder) = Instantiator::getQueryCaller($pivotComponent);

        $pivotQueryBuilder->setColumns(["MAX({$positionField->getColumn()}) AS {$positionField->getName()}"]);

        $results = $pivotQueryBuilder->select();
        $nextPosition = $results[0]['position'] === null ? 0 : (int)$results[0]['position'] + 1;


        $instance = $pivotSchema->getItemInstance();

        $pointingSetter = $pointingField->getSetterForPrimitiveValue();
        $instance->{$pointingSetter}($this->getIdColumnValue());

        $referencedSetter = $referencedField->getSetterForPrimitiveValue();
        $instance->{$referencedSetter}($id);

        $positionSetter = $positionField->getSetterForPrimitiveValue();
        $instance->{$positionSetter}($nextPosition);

        $instance->save();
        return $this;
    }

    public function unlinkPivot(string $pivotComponent, $id): static
    {
        $pivotSchema = Schema::get($pivotComponent);

        $pointingField = $pivotSchema->getOneFieldPointingToComponent(static::COMPONENT);

        if ($pointingField instanceof PivotLeftIdField) {
            $referencedField = $pivotSchema->getPivotRightIdField();
        } else {
            $referencedField = $pivotSchema->getPivotLeftIdField();
        }

//        /** @var PivotPositionField $positionField */
//        $positionField = $pivotSchema->getOnePositionField();

        $pivotQueryBuilder = QueryBuilderHelper::getComponentQuery($pivotComponent);

//        /** @var Query $queryBuilder */
//        list($pivotQueryBuilder) = Instantiator::getQueryCaller($pivotComponent);

        $pointingGetter = $pointingField->getGetterForPrimitiveValue();
        $pivotQueryBuilder->andIntegerEqual($pointingField->getColumn(), $this->{$pointingGetter}());

        $referencedGetter = $referencedField->getGetterForPrimitiveValue();
        $pivotQueryBuilder->andIntegerEqual($referencedField->getColumn(), $this->{$referencedGetter}());

        $anonymous = $pivotSchema->getItemInstance();
        $instance = $anonymous::getOne($pivotQueryBuilder);
        $instance->delete();
        return $this;
    }
}