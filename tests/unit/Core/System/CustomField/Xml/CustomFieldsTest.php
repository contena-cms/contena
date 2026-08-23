<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFields;

/**
 * @internal
 */
#[CoversClass(CustomFields::class)]
class CustomFieldsTest extends TestCase
{
    public function testFromXml(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/../_fixtures/custom-fields.xml');

        static::assertCount(2, $customFields->getCustomFieldSets());

        $customFieldSet = $customFields->getCustomFieldSets()[0];
        static::assertSame('test_set', $customFieldSet->getName());
        static::assertSame([
            'en-GB' => 'Test Set',
            'de-DE' => 'Test-Set',
        ], $customFieldSet->getLabel());
        static::assertSame(['media', 'user'], $customFieldSet->getRelatedEntities());
        static::assertFalse($customFieldSet->getGlobal());

        static::assertCount(2, $customFieldSet->getFields());

        $fields = $customFieldSet->getFields();

        static::assertSame('test_set_int_field', $fields[0]->getName());
        static::assertTrue($fields[0]->isIncludeInSearch());

        static::assertSame('test_set_text_field', $fields[1]->getName());
        static::assertFalse($fields[1]->isIncludeInSearch());
    }
}
