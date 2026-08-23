<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\SingleEntitySelectField;

/**
 * @internal
 */
#[CoversClass(SingleEntitySelectField::class)]
class SingleEntitySelectFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/single-entity-select-field.xml');
        static::assertCount(1, $customFields->getCustomFieldSets());

        $customFieldSet = $customFields->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $singleEntitySelectField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(SingleEntitySelectField::class, $singleEntitySelectField);
        static::assertSame('test_single_entity_select_field', $singleEntitySelectField->getName());
        static::assertSame([
            'en-GB' => 'Test single-entity-select field',
        ], $singleEntitySelectField->getLabel());
        static::assertSame([], $singleEntitySelectField->getHelpText());
        static::assertSame(1, $singleEntitySelectField->getPosition());
        static::assertSame(['en-GB' => 'Choose an entity...'], $singleEntitySelectField->getPlaceholder());
        static::assertFalse($singleEntitySelectField->getRequired());
        static::assertSame('media', $singleEntitySelectField->getEntity());
    }

    public function testToEntityPayload(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/single-entity-select-field.xml');

        $singleEntitySelectField = $customFields->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(SingleEntitySelectField::class, $singleEntitySelectField);

        static::assertEquals([
            'name' => 'test_single_entity_select_field',
            'type' => 'entity',
            'config' => [
                'label' => [
                    'en-GB' => 'Test single-entity-select field',
                ],
                'helpText' => [],
                'customFieldPosition' => 1,
                'entity' => 'media',
                'placeholder' => [
                    'en-GB' => 'Choose an entity...',
                ],
                'componentName' => 'ct-entity-single-select',
                'customFieldType' => 'select',
            ],
        ], $singleEntitySelectField->toEntityPayload());
    }
}
