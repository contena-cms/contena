<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFieldSet;

/**
 * @internal
 */
#[CoversClass(CustomFieldSet::class)]
class CustomFieldSetTest extends TestCase
{
    public function testToEntityArrayForNewSetIncludesNameAndFieldNames(): void
    {
        $customFieldSet = $this->getCustomFieldSet();

        $existingRelations = [];
        $existingFields = [];

        $payload = $customFieldSet->toEntityArray($existingRelations, $existingFields);

        static::assertArrayHasKey('name', $payload);
        static::assertSame('test_set', $payload['name']);
        static::assertSame(['label' => $customFieldSet->getLabel(), 'translated' => true], $payload['config']);
        static::assertCount(2, $payload['relations']);
        static::assertSame('media', $payload['relations'][0]['entityName']);
        static::assertCount(2, $payload['customFields']);
        static::assertSame('test_set_int_field', $payload['customFields'][0]['name']);
        static::assertSame([], $existingRelations);
        static::assertSame([], $existingFields);
    }

    public function testToEntityArrayUsesExistingIdentifiersWhenUpdating(): void
    {
        $customFieldSet = $this->getCustomFieldSet();

        $existingRelationId = Uuid::randomHex();
        $existingFieldId = Uuid::randomHex();
        $existingSetId = Uuid::randomHex();

        $existingRelations = ['media' => $existingRelationId];
        $existingFields = ['test_set_int_field' => $existingFieldId];

        $payload = $customFieldSet->toEntityArray($existingRelations, $existingFields, $existingSetId);

        static::assertArrayHasKey('id', $payload);
        static::assertSame($existingSetId, $payload['id']);
        static::assertArrayNotHasKey('name', $payload);
        static::assertCount(2, $payload['relations']);
        static::assertSame($existingRelationId, $payload['relations'][0]['id']);
        static::assertArrayHasKey('customFields', $payload);
        static::assertSame($existingFieldId, $payload['customFields'][0]['id']);
        static::assertArrayNotHasKey('name', $payload['customFields'][0]);
        static::assertSame([], $existingRelations);
        static::assertSame([], $existingFields);
    }

    private function getCustomFieldSet(): CustomFieldSet
    {
        $customFields = CustomFieldXmlLoader::load(__DIR__ . '/../_fixtures/custom-fields.xml');
        $sets = $customFields->getCustomFieldSets();
        static::assertNotEmpty($sets);

        return $sets[0];
    }
}
