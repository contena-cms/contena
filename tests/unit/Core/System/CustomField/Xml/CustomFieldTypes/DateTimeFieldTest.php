<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField\Xml\CustomFieldTypes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFieldTypes\DateTimeField;

/**
 * @internal
 */
#[CoversClass(DateTimeField::class)]
class DateTimeFieldTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/date-time-field.xml');
        static::assertCount(1, $customFields->getCustomFieldSets());

        $customFieldSet = $customFields->getCustomFieldSets()[0];

        static::assertCount(1, $customFieldSet->getFields());

        $dateTimeField = $customFieldSet->getFields()[0];
        static::assertInstanceOf(DateTimeField::class, $dateTimeField);
        static::assertSame('test_datetime_field', $dateTimeField->getName());
        static::assertSame([
            'en-GB' => 'Test datetime field',
        ], $dateTimeField->getLabel());
        static::assertSame([], $dateTimeField->getHelpText());
        static::assertSame(1, $dateTimeField->getPosition());
        static::assertFalse($dateTimeField->getRequired());
    }

    public function testToEntityPayload(): void
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/date-time-field.xml');

        $dateTimeField = $customFields->getCustomFieldSets()[0]->getFields()[0];
        static::assertInstanceOf(DateTimeField::class, $dateTimeField);

        static::assertEquals([
            'name' => 'test_datetime_field',
            'type' => 'datetime',
            'config' => [
                'label' => [
                    'en-GB' => 'Test datetime field',
                ],
                'helpText' => [],
                'customFieldPosition' => 1,
                'type' => 'date',
                'componentName' => 'ct-field',
                'customFieldType' => 'date',
                'config' => [
                    'time_24hr' => true,
                ],
                'dateType' => 'datetime',
            ],
        ], $dateTimeField->toEntityPayload());
    }
}
