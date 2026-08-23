<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\BoolField;

/**
 * @internal
 */
#[CoversClass(BoolField::class)]
class BoolFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/bool-field.xml');
        static::assertCount(1, $customFields->getCustomFieldSets());

        $customFieldSet = $customFields->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $boolField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(BoolField::class, $boolField);
        static::assertSame('test_bool_field', $boolField->getName());
        static::assertSame([
            'en-GB' => 'Test bool field',
        ], $boolField->getLabel());
        static::assertSame([], $boolField->getHelpText());
        static::assertSame(1, $boolField->getPosition());
        static::assertFalse($boolField->getRequired());
        static::assertTrue($boolField->isIncludeInSearch());
    }

    public function testToEntityPayload(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/bool-field.xml');

        $boolField = $customFields->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(BoolField::class, $boolField);

        static::assertEquals([
            'name' => 'test_bool_field',
            'type' => 'bool',
            'includeInSearch' => true,
            'config' => [
                'label' => [
                    'en-GB' => 'Test bool field',
                ],
                'helpText' => [],
                'customFieldPosition' => 1,
                'type' => 'checkbox',
                'componentName' => 'ct-field',
                'customFieldType' => 'checkbox',
            ],
        ], $boolField->toEntityPayload());
    }
}
