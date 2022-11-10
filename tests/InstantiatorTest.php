<?php

namespace Lkt\Factory\Instantiator\Tests;


use Lkt\DatabaseConnectors\DatabaseConnections;
use Lkt\DatabaseConnectors\MySQLConnector;
use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Instantiator\Instantiator;
use Lkt\Factory\Instantiator\Tests\Assets\SampleInstance;
use Lkt\Factory\Schemas\Fields\BooleanField;
use Lkt\Factory\Schemas\Fields\ColorField;
use Lkt\Factory\Schemas\Fields\EmailField;
use Lkt\Factory\Schemas\Fields\ForeignKeyField;
use Lkt\Factory\Schemas\Fields\HTMLField;
use Lkt\Factory\Schemas\Fields\IdField;
use Lkt\Factory\Schemas\Fields\JSONField;
use Lkt\Factory\Schemas\Fields\StringField;
use Lkt\Factory\Schemas\InstanceSettings;
use Lkt\Factory\Schemas\Schema;
use PHPUnit\Framework\TestCase;

class InstantiatorTest extends TestCase
{
    /**
     * @return void
     */
    public function test_001_FromFactory()
    {
        DatabaseConnections::set(MySQLConnector::define(''));

        $component = 'sample';
        /** Define working schema */
        Schema::add(
            $schema = Schema::table('test', $component)
                ->setInstanceSettings(InstanceSettings::define(SampleInstance::class))
                ->addField(IdField::define('id'))
                ->addField(StringField::define('name'))
                ->addField(EmailField::define('email'))
                ->addField(HTMLField::define('html'))
                ->addField(BooleanField::define('isActive', 'is_active'))
                ->addField(ForeignKeyField::define('parent', 'parent_id'))
                ->addField(ColorField::define('favouriteColor', 'favourite_color'))
                ->addField(JSONField::define('data')->setIsAssoc()->setIsCompressed())
        );

        // Prepare some initial data
        $converter = new RawResultsToInstanceConverter($component, ['id' => '1', 'name' => 'John', 'parent' => 1]);
        $parsed = $converter->parse();

        // Instantiate
        $instance = Instantiator::make($component, 1, $parsed);
        $this->assertInstanceOf(SampleInstance::class, $instance);
    }

    /**
     * @return void
     */
    public function test_002_FromGetInstanceWithCache()
    {
        $component = 'sample';

        // Instantiate
        $instance = SampleInstance::getInstance(1, $component);
        $this->assertInstanceOf(SampleInstance::class, $instance);
    }

    /**
     * @return void
     */
    public function test_003_FromGetInstanceWithoutCache()
    {
        $component = 'sample2';
        /** Define working schema */
        Schema::add(
            $schema = Schema::table('test', $component)
                ->setDatabaseConnector('')
                ->setInstanceSettings(InstanceSettings::define(SampleInstance::class))
                ->addField(IdField::define('id'))
                ->addField(StringField::define('name'))
                ->addField(EmailField::define('email'))
                ->addField(HTMLField::define('html'))
                ->addField(BooleanField::define('isActive', 'is_active'))
                ->addField(ForeignKeyField::define('parent', 'parent_id'))
                ->addField(ColorField::define('favouriteColor', 'favourite_color'))
                ->addField(JSONField::define('data')->setIsAssoc()->setIsCompressed())
        );

        // Instantiate
        $instance = SampleInstance::getInstance(1, $component);
        $this->assertInstanceOf(SampleInstance::class, $instance);
    }
}