<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\TextAreaField;

/**
 * @internal
 */
#[CoversClass(TextAreaField::class)]
class TextAreaFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/text-area-field.xml');
        static::assertCount(1, $customFields->getCustomFieldSets());

        $customFieldSet = $customFields->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $textAreaField = $customFieldSet->getFields()[0];

        static::assertInstanceOf(TextAreaField::class, $textAreaField);
        static::assertSame('test_text_area_field', $textAreaField->getName());
        static::assertSame([
            'en-GB' => 'Test text-area field',
        ], $textAreaField->getLabel());
        static::assertSame([], $textAreaField->getHelpText());
        static::assertSame(['en-GB' => 'Enter a text...'], $textAreaField->getPlaceholder());
        static::assertSame(1, $textAreaField->getPosition());
        static::assertFalse($textAreaField->getRequired());
    }

    public function testToEntityPayload(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/text-area-field.xml');

        $textAreaField = $customFields->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(TextAreaField::class, $textAreaField);

        static::assertEquals([
            'name' => 'test_text_area_field',
            'type' => 'html',
            'config' => [
                'label' => [
                    'en-GB' => 'Test text-area field',
                ],
                'helpText' => [],
                'customFieldPosition' => 1,
                'placeholder' => [
                    'en-GB' => 'Enter a text...',
                ],
                'componentName' => 'mt-text-editor',
                'customFieldType' => 'textEditor',
            ],
        ], $textAreaField->toEntityPayload());
    }
}
