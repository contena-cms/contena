<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\TextField;

/**
 * @internal
 */
#[CoversClass(TextField::class)]
class TextFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/text-field.xml');
        static::assertCount(1, $customFields->getCustomFieldSets());

        $customFieldSet = $customFields->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $textField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(TextField::class, $textField);
        static::assertSame('test_text_field', $textField->getName());
        static::assertSame([
            'en-GB' => 'Test text field',
        ], $textField->getLabel());
        static::assertSame([], $textField->getHelpText());
        static::assertSame(1, $textField->getPosition());
        static::assertSame(['en-GB' => 'Enter a text...'], $textField->getPlaceholder());
        static::assertFalse($textField->getRequired());
    }

    public function testToEntityPayload(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/text-field.xml');

        $textField = $customFields->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(TextField::class, $textField);

        static::assertEquals([
            'name' => 'test_text_field',
            'type' => 'text',
            'config' => [
                'label' => [
                    'en-GB' => 'Test text field',
                ],
                'helpText' => [],
                'customFieldPosition' => 1,
                'type' => 'text',
                'placeholder' => [
                    'en-GB' => 'Enter a text...',
                ],
                'componentName' => 'ct-field',
                'customFieldType' => 'text',
            ],
        ], $textField->toEntityPayload());
    }
}
