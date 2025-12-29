<?php

namespace Lkt\Factory\Instantiator\Helpers;

use Lkt\Factory\Instantiator\Instances\AbstractInstance;
use Lkt\Factory\Schemas\Schema;

class UpdatedRelatedDataProcessor
{
    protected Schema $schema;
    protected string $fieldName = '';
    public array $data = [];
    public AbstractInstance|null $referrer = null;
    public array $updatedData = [];
    public array $pendingUpdateData = [];


    protected $ownField;
    protected string $relatedComponent = '';
    protected string $relatedIdColumn = '';

    public function __construct(Schema $schema, string $fieldName, array $data, AbstractInstance $referrer)
    {
        $this->schema = $schema;
        $this->fieldName = $fieldName;
        $this->data = $data;
        $this->referrer = $referrer;
    }

    public function processRelatedField()
    {
        $ownField = $this->schema->getField($this->fieldName);
        if (!is_object($ownField)) {
            return;
        }

        $this->relatedComponent = $ownField->getComponent();
        if (method_exists($ownField, 'getDynamicComponentField')) { // Check due to RelatedField not implementing this feature yet
            $dynamicComponentFieldName = $ownField->getDynamicComponentField();
            if ($dynamicComponentFieldName !== '') {
                $dynamicComponentField = $this->schema->getField($dynamicComponentFieldName);
                $getter = $dynamicComponentField->getGetterForPrimitiveValue();
                $dynamicType = $this->referrer->{$getter}();
                if ($dynamicType !== '') $this->relatedComponent = $dynamicType;
            }
        }

        if ($this->relatedComponent === '') {
            $this->pendingUpdateData = [];
            $this->updatedData = [];
            return;
        }

        $relatedSchema = Schema::get($this->relatedComponent);
        $relatedIdColumn = $relatedSchema->getIdColumn();
        if (count($relatedIdColumn) === 1) $relatedIdColumn = reset($relatedIdColumn);

        $this->relatedIdColumn = $relatedIdColumn;

        $relatedClass = $relatedSchema->getInstanceSettings()->getAppClass();

        $r = [];

        foreach ($this->data as &$datum) {
            if (is_array($datum)) {
                if (!$datum[$relatedIdColumn]) {
                    if (method_exists($ownField, 'getRelatedComponentFeeds')){
                        foreach ($ownField->getRelatedComponentFeeds() as $relatedColumnKey => $relatedColumnValue) {
                            if (is_callable($relatedColumnValue)) {
                                $relatedColumnValue = call_user_func_array($relatedColumnValue, [
                                    'referrer' => $this->referrer
                                ]);
                            }
                            if (!$datum[$relatedColumnKey]) $datum[$relatedColumnKey] = $relatedColumnValue;
                        }
                    }
                }

                $instance = call_user_func_array([$relatedClass, 'getInstance'], [$datum[$relatedIdColumn]]);
                $instance::feedInstance($instance, $datum);

            } else if (is_numeric($datum)) {
                $instance = call_user_func_array([$relatedClass, 'getInstance'], [$datum]);
            }
            $r[] = $instance;
        }

        $this->pendingUpdateData = $this->data;
        $this->updatedData = $r;
    }

    public function processForeignKeysField()
    {
        $ownField = $this->schema->getField($this->fieldName);

        $relatedComponent = $ownField->getComponent();
        $dynamicComponentFieldName = $ownField->getDynamicComponentField();
        if ($dynamicComponentFieldName !== '') {
            $dynamicComponentField = $this->schema->getField($dynamicComponentFieldName);
            $getter = $dynamicComponentField->getGetterForPrimitiveValue();
            $dynamicType = $this->referrer->{$getter}();
            if ($dynamicType !== '') $relatedComponent = $dynamicType;
        }

        if ($relatedComponent === '') {
            $this->pendingUpdateData = [];
            $this->updatedData = [];
            return;
        }

        $relatedSchema = Schema::get($relatedComponent);
        $relatedIdColumn = $relatedSchema->getIdColumn();
        if (count($relatedIdColumn) === 1) $relatedIdColumn = reset($relatedIdColumn);
        $relatedForeignKeyColumn = $relatedSchema->getField($ownField->getColumn());
        $relatedForeignKeyKey = $relatedForeignKeyColumn->getName();

        $relatedClass = $relatedSchema->getInstanceSettings()->getAppClass();

        $r = [];

        foreach ($this->data as &$datum) {
            if (!$datum[$relatedIdColumn]) {
                $datum[$relatedForeignKeyKey] = $this->getIdColumnValue();

                foreach ($ownField->getRelatedComponentFeeds() as $relatedColumnKey => $relatedColumnValue) {
                    if (is_callable($relatedColumnValue)) {
                        $relatedColumnValue = call_user_func_array($relatedColumnValue, [
                            'referrer' => $this->referrer
                        ]);
                    }
                    if (!$datum[$relatedColumnKey]) $datum[$relatedColumnKey] = $relatedColumnValue;
                }
            }

            $instance = call_user_func_array([$relatedClass, 'getInstance'], [$datum[$relatedIdColumn]]);
            $instance::feedInstance($instance, $datum);
            $r[] = $instance;
        }

        $this->pendingUpdateData = $this->data;
        $this->updatedData = $r;
    }
}