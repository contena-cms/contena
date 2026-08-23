<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\MultiSelectField;

/**
 * @internal
 */
#[CoversClass(MultiSelectField::class)]
class MultiSelectFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/multi-select-field.xml');
        static::assertCount(1, $customFields->getCustomFieldSets());

        $customFieldSet = $customFields->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $multiSelectField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(MultiSelectField::class, $multiSelectField);
        static::assertSame('test_multi_select_field', $multiSelectField->getName());
        static::assertSame([
            'en-GB' => 'Test multi-select field',
        ], $multiSelectField->getLabel());
        static::assertSame([], $multiSelectField->getHelpText());
        static::assertSame(1, $multiSelectField->getPosition());
        static::assertSame(['en-GB' => 'Choose your options...'], $multiSelectField->getPlaceholder());
        static::assertFalse($multiSelectField->getRequired());
        static::assertSame([
            'first' => [
                'en-GB' => 'First',
                'de-DE' => 'Erster',
            ],
            'second' => [
                'en-GB' => 'Second',
            ],
        ], $multiSelectField->getOptions());
    }

    public function testToEntityPayload(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/multi-select-field.xml');

        $multiSelectField = $customFields->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(MultiSelectField::class, $multiSelectField);

        static::assertEquals([
            'name' => 'test_multi_select_field',
            'type' => 'select',
            'config' => [
                'label' => [
                    'en-GB' => 'Test multi-select field',
                ],
                'helpText' => [],
                'customFieldPosition' => 1,
                'placeholder' => [
                    'en-GB' => 'Choose your options...',
                ],
                'componentName' => 'ct-multi-select',
                'customFieldType' => 'select',
                'options' => [
                    [
                        'label' => [
                            'en-GB' => 'First',
                            'de-DE' => 'Erster',
                        ],
                        'value' => 'first',
                    ],
                    [
                        'label' => [
                            'en-GB' => 'Second',
                        ],
                        'value' => 'second',
                    ],
                ],
            ],
        ], $multiSelectField->toEntityPayload());
    }
}
