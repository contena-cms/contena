<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\FloatField;

/**
 * @internal
 */
#[CoversClass(FloatField::class)]
class FloatFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/float-field.xml');
        static::assertCount(1, $customFields->getCustomFieldSets());

        $customFieldSet = $customFields->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $floatField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(FloatField::class, $floatField);
        static::assertSame('test_float_field', $floatField->getName());
        static::assertSame([
            'en-GB' => 'Test float field',
            'de-DE' => 'Test Kommazahlenfeld',
        ], $floatField->getLabel());
        static::assertSame(['en-GB' => 'This is a float field.'], $floatField->getHelpText());
        static::assertSame(2, $floatField->getPosition());
        static::assertSame(2.2, $floatField->getSteps());
        static::assertSame(0.5, $floatField->getMin());
        static::assertSame(1.6, $floatField->getMax());
        static::assertSame(['en-GB' => 'Enter a float...'], $floatField->getPlaceholder());
        static::assertFalse($floatField->getRequired());
    }

    public function testToEntityPayload(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/float-field.xml');

        $floatField = $customFields->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(FloatField::class, $floatField);

        static::assertEquals([
            'name' => 'test_float_field',
            'type' => 'float',
            'config' => [
                'label' => [
                    'en-GB' => 'Test float field',
                    'de-DE' => 'Test Kommazahlenfeld',
                ],
                'helpText' => [
                    'en-GB' => 'This is a float field.',
                ],
                'customFieldPosition' => 2,
                'type' => 'number',
                'placeholder' => [
                    'en-GB' => 'Enter a float...',
                ],
                'componentName' => 'ct-field',
                'customFieldType' => 'number',
                'numberType' => 'float',
                'max' => 1.6,
                'min' => 0.5,
                'step' => 2.2,
            ],
        ], $floatField->toEntityPayload());
    }
}
