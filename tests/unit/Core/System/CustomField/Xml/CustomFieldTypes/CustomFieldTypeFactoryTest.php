<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFieldTypeNotFoundException;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\CustomFieldTypeFactory;

/**
 * @internal
 */
#[CoversClass(CustomFieldTypeFactory::class)]
class CustomFieldTypeFactoryTest extends TestCase
{
    public function testCreateFromXmlThrowsExceptionOnInvalidTag(): void
    {
        $this->expectExceptionObject(new CustomFieldTypeNotFoundException('invalid'));
        CustomFieldTypeFactory::createFromXml(new \DOMElement('invalid'));
    }

    public function testTranslatedForTag(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/custom-field-type-factory.xml');

        static::assertCount(1, $customFields->getCustomFieldSets());

        $customFieldSet = $customFields->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $field = $customFieldSet->getFields()[0];
        static::assertSame('bool_field', $field->getName());
        static::assertSame([
            'en-GB' => 'Test bool field',
            'de-DE' => 'Test bool field',
        ], $field->getLabel());
        static::assertSame([
            'en-GB' => 'Help text',
            'de-DE' => 'Help text',
        ], $field->getHelpText());
    }
}
