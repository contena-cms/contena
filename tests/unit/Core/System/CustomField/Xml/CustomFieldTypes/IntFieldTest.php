<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\IntField;

/**
 * @internal
 */
#[CoversClass(IntField::class)]
class IntFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/int-field.xml');
        static::assertCount(1, $customFields->getCustomFieldSets());

        $customFieldSet = $customFields->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $intField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(IntField::class, $intField);
        static::assertSame('test_int_field', $intField->getName());
        static::assertSame([
            'en-GB' => 'Test int field',
            'de-DE' => 'Test Ganzzahlenfeld',
        ], $intField->getLabel());
        static::assertSame(['en-GB' => 'This is an int field.'], $intField->getHelpText());
        static::assertSame(1, $intField->getPosition());
        static::assertSame(2, $intField->getSteps());
        static::assertSame(0, $intField->getMin());
        static::assertSame(1, $intField->getMax());
        static::assertSame(['en-GB' => 'Enter an int...'], $intField->getPlaceholder());
        static::assertTrue($intField->getRequired());
    }

    public function testToEntityPayload(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/int-field.xml');

        $intField = $customFields->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(IntField::class, $intField);

        static::assertEquals([
            'name' => 'test_int_field',
            'type' => 'int',
            'config' => [
                'label' => [
                    'en-GB' => 'Test int field',
                    'de-DE' => 'Test Ganzzahlenfeld',
                ],
                'helpText' => [
                    'en-GB' => 'This is an int field.',
                ],
                'customFieldPosition' => 1,
                'validation' => 'required',
                'type' => 'number',
                'placeholder' => [
                    'en-GB' => 'Enter an int...',
                ],
                'componentName' => 'ct-field',
                'customFieldType' => 'number',
                'numberType' => 'int',
                'max' => 1,
                'min' => 0,
                'step' => 2,
            ],
        ], $intField->toEntityPayload());
    }
}
