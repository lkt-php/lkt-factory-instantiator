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

        $this->relatedComponent = $ownField->getComponent();

        $relatedSchema = Schema::get($this->relatedComponent);
        $relatedIdColumn = $relatedSchema->getIdColumn();
        if (count($relatedIdColumn) === 1) $relatedIdColumn = reset($relatedIdColumn);

        $this->relatedIdColumn = $relatedIdColumn;

        $relatedClass = $relatedSchema->getInstanceSettings()->getAppClass();

        $r = [];

        foreach ($this->data as &$datum) {
            if (!$datum[$relatedIdColumn]) {
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
            $instance->hydrate($datum);
            $r[] = $instance;
        }

        $this->pendingUpdateData = $this->data;
        $this->updatedData = $r;


    }

    public function processForeignKeysField()
    {
        $ownField = $this->schema->getField($this->fieldName);

        $relatedComponent = $ownField->getComponent();

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
            $instance->hydrate($datum);
            $r[] = $instance;
        }

        $this->pendingUpdateData = $this->data;
        $this->updatedData = $r;
    }
}