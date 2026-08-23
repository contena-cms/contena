<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\MediaSelectionField;

/**
 * @internal
 */
#[CoversClass(MediaSelectionField::class)]
class MediaSelectionFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/media-selection-field.xml');
        static::assertCount(1, $customFields->getCustomFieldSets());

        $customFieldSet = $customFields->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $mediaSelectionField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(MediaSelectionField::class, $mediaSelectionField);
        static::assertSame('test_media_selection_field', $mediaSelectionField->getName());
        static::assertSame([
            'en-GB' => 'Test media-selection field',
        ], $mediaSelectionField->getLabel());
        static::assertSame([], $mediaSelectionField->getHelpText());
        static::assertSame(1, $mediaSelectionField->getPosition());
        static::assertFalse($mediaSelectionField->getRequired());
    }

    public function testToEntityPayload(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/media-selection-field.xml');

        $mediaSelectionField = $customFields->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(MediaSelectionField::class, $mediaSelectionField);

        static::assertEquals([
            'name' => 'test_media_selection_field',
            'type' => 'text',
            'config' => [
                'label' => [
                    'en-GB' => 'Test media-selection field',
                ],
                'helpText' => [],
                'customFieldPosition' => 1,
                'componentName' => 'ct-media-field',
                'customFieldType' => 'media',
            ],
        ], $mediaSelectionField->toEntityPayload());
    }
}
