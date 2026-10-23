<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Serializer\JsonApiEncodingResult;
use Contena\Core\Framework\Api\Serializer\Record;

/**
 * @internal
 */
#[CoversClass(JsonApiEncodingResult::class)]
class JsonApiEncodingResultTest extends TestCase
{
    public function testEmptyRelationshipDataDoesNotOverwriteExistingData(): void
    {
        $result = new JsonApiEncodingResult('/api');

        $firstRecord = new Record('entity-1', 'test_entity');
        $firstRecord->addRelationship('items', ['data' => [['type' => 'item', 'id' => 'item-1']]]);
        $firstRecord->addRelationship('categories', ['data' => [['type' => 'category', 'id' => 'cat-1']]]);

        $result->addIncluded($firstRecord);

        $secondRecord = new Record('entity-1', 'test_entity');
        // Empty array should NOT overwrite existing data
        $secondRecord->addRelationship('items', ['data' => []]);
        // Null should NOT overwrite existing data
        $secondRecord->addRelationship('categories', ['data' => null]);
        // New relationship with data should be added
        $secondRecord->addRelationship('tags', ['data' => [['type' => 'tag', 'id' => 'tag-1']]]);

        $result->addIncluded($secondRecord);

        $included = $result->getIncluded();
        $merged = array_values($included)[0];

        // Original data should be preserved
        static::assertSame([['type' => 'item', 'id' => 'item-1']], $merged->getRelationships()['items']['data']);
        static::assertSame([['type' => 'category', 'id' => 'cat-1']], $merged->getRelationships()['categories']['data']);
        // New relationship should be added
        static::assertSame([['type' => 'tag', 'id' => 'tag-1']], $merged->getRelationships()['tags']['data']);
    }

    public function testCircularReferenceHandling(): void
    {
        $result = new JsonApiEncodingResult('/api');

        // Simulate: AdminUser -> Media -> AdminUser (back-reference)
        // AdminUser is added to included first (via media.admin_user path)
        $includedUser = new Record('admin_user-1', 'admin_user');
        $includedUser->setAttribute('name', 'John');
        $includedUser->addRelationship('media', ['data' => []]); // No media loaded on this traversal
        $result->addIncluded($includedUser);

        // Media is processed
        $media = new Record('media-1', 'media');
        $media->addRelationship('admin_user', ['data' => ['type' => 'admin_user', 'id' => 'admin_user-1']]);
        $result->addIncluded($media);

        // Now original AdminUser (with media) is added to data
        $dataUser = new Record('admin_user-1', 'admin_user');
        $dataUser->setAttribute('name', 'John Doe');
        $dataUser->addRelationship('media', ['data' => [['type' => 'media', 'id' => 'media-1']]]);
        $result->addEntity($dataUser);

        // AdminUser should be in data (not included), with merged data
        static::assertTrue($result->containsInData('admin_user-1', 'admin_user'));
        static::assertFalse($result->containsInIncluded('admin_user-1', 'admin_user'));

        $data = $result->getData();
        $adminUser = array_values($data)[0];

        // The media relationship should be preserved (not overwritten by empty array)
        static::assertSame([['type' => 'media', 'id' => 'media-1']], $adminUser->getRelationships()['media']['data']);
    }
}
