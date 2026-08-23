<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\MultiEntitySelectField;

/**
 * @internal
 */
#[CoversClass(MultiEntitySelectField::class)]
class MultiEntitySelectFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/multi-entity-select-field.xml');
        static::assertCount(1, $customFields->getCustomFieldSets());

        $customFieldSet = $customFields->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $multiEntitySelectField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(MultiEntitySelectField::class, $multiEntitySelectField);
        static::assertSame('test_multi_entity_select_field', $multiEntitySelectField->getName());
        static::assertSame([
            'en-GB' => 'Test multi-entity-select field',
        ], $multiEntitySelectField->getLabel());
        static::assertSame([], $multiEntitySelectField->getHelpText());
        static::assertSame(1, $multiEntitySelectField->getPosition());
        static::assertSame(['en-GB' => 'Choose an entity...'], $multiEntitySelectField->getPlaceholder());
        static::assertFalse($multiEntitySelectField->getRequired());
        static::assertSame('media', $multiEntitySelectField->getEntity());
    }

    public function testToEntityPayload(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/multi-entity-select-field.xml');

        $multiEntitySelectField = $customFields->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(MultiEntitySelectField::class, $multiEntitySelectField);

        static::assertEquals([
            'name' => 'test_multi_entity_select_field',
            'type' => 'entity',
            'config' => [
                'label' => [
                    'en-GB' => 'Test multi-entity-select field',
                ],
                'helpText' => [],
                'customFieldPosition' => 1,
                'entity' => 'media',
                'placeholder' => [
                    'en-GB' => 'Choose an entity...',
                ],
                'componentName' => 'ct-entity-multi-id-select',
                'customFieldType' => 'select',
            ],
        ], $multiEntitySelectField->toEntityPayload());
    }
}
