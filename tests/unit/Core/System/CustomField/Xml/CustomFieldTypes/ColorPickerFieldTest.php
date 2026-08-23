<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\ColorPickerField;

/**
 * @internal
 */
#[CoversClass(ColorPickerField::class)]
class ColorPickerFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/color-picker-field.xml');
        static::assertCount(1, $customFields->getCustomFieldSets());

        $customFieldSet = $customFields->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $colorPickerField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(ColorPickerField::class, $colorPickerField);
        static::assertSame('test_color_picker_field', $colorPickerField->getName());
        static::assertSame([
            'en-GB' => 'Test color-picker field',
        ], $colorPickerField->getLabel());
        static::assertSame([], $colorPickerField->getHelpText());
        static::assertSame(1, $colorPickerField->getPosition());
        static::assertFalse($colorPickerField->getRequired());
    }

    public function testToEntityPayload(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/color-picker-field.xml');

        $colorPickerField = $customFields->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(ColorPickerField::class, $colorPickerField);

        static::assertEquals([
            'name' => 'test_color_picker_field',
            'type' => 'text',
            'config' => [
                'label' => [
                    'en-GB' => 'Test color-picker field',
                ],
                'helpText' => [],
                'customFieldPosition' => 1,
                'type' => 'colorpicker',
                'componentName' => 'ct-field',
                'customFieldType' => 'colorpicker',
            ],
        ], $colorPickerField->toEntityPayload());
    }
}
