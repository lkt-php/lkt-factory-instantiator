<?php

namespace Lkt\Factory\Instantiator\Helpers;

use FileUpload\FileNameGenerator\FileNameGenerator;
use FileUpload\FileNameGenerator\Slug;
use FileUpload\FileUpload;
use Lkt\Factory\Instantiator\Instances\AbstractInstance;
use Lkt\Factory\Schemas\Fields\FileField;
use Lkt\Factory\Schemas\Schema;
use Lkt\MIME;

class LktFieldFileNameGenerator implements FileNameGenerator
{
    protected FileField|null $field = null;
    protected Schema|null $schema = null;
    protected AbstractInstance|null $instance = null;

    public function __construct(FileField $field, Schema $schema, AbstractInstance $instance)
    {
        $this->field = $field;
        $this->schema = $schema;
        $this->instance = $instance;
    }

    public function getFileName(string $source_name, string $type, string $tmp_name, int $index, array $content_range, FileUpload $upload): string
    {
        $configuredFileName = $this->field->getFileName();
        if ($configuredFileName === null) {
            $slugGenerator = new Slug();
            return $slugGenerator->getFileName($source_name, $type, $tmp_name, $index, $content_range, $upload);
        }

        $extension = MIME::getExtensionByMime($type);

        return "{$this->instance->parseFileName($configuredFileName, $this->field)}.$extension";
    }
}