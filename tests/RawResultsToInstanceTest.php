<?php

namespace Lkt\FactoryConfig\Tests;


use Lkt\Factory\Instantiator\Conversions\RawResultsToInstanceConverter;
use Lkt\Factory\Schemas\Fields\BooleanField;
use Lkt\Factory\Schemas\Fields\ColorField;
use Lkt\Factory\Schemas\Fields\EmailField;
use Lkt\Factory\Schemas\Fields\ForeignKeyField;
use Lkt\Factory\Schemas\Fields\HTMLField;
use Lkt\Factory\Schemas\Fields\IntegerField;
use Lkt\Factory\Schemas\Fields\JSONField;
use Lkt\Factory\Schemas\Fields\StringField;
use Lkt\Factory\Schemas\Schema;
use PHPUnit\Framework\TestCase;

class RawResultsToInstanceTest extends TestCase
{
    /**
     * @return void
     * @throws \Lkt\Factory\Schemas\Exceptions\InvalidComponentException
     * @throws \Lkt\Factory\Schemas\Exceptions\InvalidFieldNameException
     * @throws \Lkt\Factory\Schemas\Exceptions\InvalidTableException
     * @throws \Lkt\Factory\Schemas\Exceptions\SchemaNotDefinedException
     */
    public function testConvert()
    {
        Schema::add(
        $schema = Schema::table('test', 'test')
            ->addField(StringField::define('name'))
            ->addField(EmailField::define('email'))
            ->addField(HTMLField::define('html'))
            ->addField(BooleanField::define('isActive', 'is_active'))
            ->addField(IntegerField::define('id'))
            ->addField(ForeignKeyField::define('parent', 'parent_id'))
            ->addField(ColorField::define('favouriteColor', 'favourite_color'))
            ->addField(JSONField::define('data')->setIsAssoc()->setIsCompressed())
        );

        $keys = array_keys($schema->getAllFields());
        sort($keys);

        $expected = ['id', 'name', 'data', 'email', 'html', 'isActive', 'favouriteColor', 'parent'];
        sort($expected);

        $this->assertEquals($expected, $keys);

        $converter = new RawResultsToInstanceConverter('test', ['id' => '1', 'name' => 'John', 'parent' => 1]);
        $parsed = $converter->parse();

        $this->assertEquals(true, $parsed['hasId']);
        $this->assertEquals(true, $parsed['hasName']);
        $this->assertEquals(false, $parsed['hasEmail']);
        $this->assertEquals(1, $parsed['id']);
        $this->assertEquals(1, $parsed['parentId']);
    }
}