<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Contena\Core\Framework\Api\Serializer\Record;
use Contena\Core\System\Tag\TagCollection;
use Contena\Core\System\Tag\TagDefinition;
use Contena\Core\System\Tag\TagEntity;
use Contena\Core\System\Tax\TaxEntity;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;

/**
 * @internal
 */
#[CoversClass(Record::class)]
class RecordTest extends TestCase
{
    public function testSerializeJson(): void
    {
        $record = $this->generateRecord();

        static::assertEquals([
            'id' => 'blog-id',
            'type' => 'blog',
            'attributes' => [
                'active' => true,
                'stock' => 10,
            ],
            'links' => new \stdClass(),
            'relationships' => [
                'options' => [
                    'data' => [],
                ],
                'tags' => [
                    'data' => [],
                ],
            ],
            'meta' => [],
        ], $record->jsonSerialize());
    }

    public function testMerge(): void
    {
        $tag = (new TagEntity())->assign(['id' => 'tag-id', '_uniqueIdentifier' => 'tag-id']);
        $tax = (new TaxEntity())->assign(['id' => 'tax-id', '_uniqueIdentifier' => 'tax-id']);

        $blog = (new BlogEntity())->assign(['id' => 'blog-id', '_uniqueIdentifier' => 'blog-id']);
        $blog->setActive(false);
        $blog->setStock(0);
        $blog->setTags(new TagCollection([$tag]));
        $blog->setTax($tax);
        $blog->setCustomFields([]);

        $record = $this->generateRecord();
        $record->setAttribute('customFields', []);
        $record->merge($blog);

        static::assertEquals([
            'id' => 'blog-id',
            'type' => 'blog',
            'attributes' => [
                'active' => false,
                'stock' => 0,
                'customFields' => new \stdClass(),
            ],
            'links' => new \stdClass(),
            'relationships' => [
                'options' => [
                    'data' => [],
                ],
                'tags' => [
                    'data' => [[
                        'type' => 'tag',
                        'id' => 'tag-id',
                    ]],
                ],
            ],
            'meta' => [],
        ], $record->jsonSerialize());
    }

    public function testMergeWithAttributeEntity(): void
    {
        $entity = (new TestAttributeEntity())->assign([
            'id' => 'entity-id',
            '_uniqueIdentifier' => 'entity-id',
            'userId' => 'user-id',
        ]);
        $entity->blogs = [
            'blog-id' => (new BlogEntity())->assign(['id' => 'blog-id', '_uniqueIdentifier' => 'blog-id']),
        ];
        $entity->user = (new UserEntity())->assign(['id' => 'user-id', '_uniqueIdentifier' => 'user-id']);

        $blogDefinition = $this->createMock(BlogDefinition::class);
        $blogDefinition->expects($this->once())
            ->method('getEntityName')
            ->willReturn('blog');

        $userDefinition = $this->createMock(UserDefinition::class);
        $userDefinition->expects($this->once())
            ->method('getEntityName')
            ->willReturn('user');

        $record = new Record('entity-id', 'test_attribute_entity');
        $record->setAttribute('userId', 'user-id');
        $record->addRelationship('blogs', [
            'tmp' => [
                'definition' => $blogDefinition,
            ],
            'data' => [],
        ]);
        $record->addRelationship('user', [
            'tmp' => [
                'definition' => $userDefinition,
            ],
            'data' => [],
        ]);
        $record->merge($entity);

        static::assertSame([
            'blogs' => [
                'tmp' => [
                    'definition' => $blogDefinition,
                ],
                'data' => [
                    [
                        'type' => 'blog',
                        'id' => 'blog-id',
                    ],
                ],
            ],
            'user' => [
                'tmp' => [
                    'definition' => $userDefinition,
                ],
                'data' => [
                    'type' => 'user',
                    'id' => 'user-id',
                ],
            ],
        ], $record->getRelationships());
    }

    private function generateRecord(): Record
    {
        $record = new Record('blog-id', 'blog');
        $record->setAttribute('active', true);
        $record->setAttribute('stock', 10);

        $propertyGroupOptionDefinition = static::createStub(PropertyGroupOptionDefinition::class);
        $propertyGroupOptionDefinition->method('getEntityName')
            ->willReturn('property_group_option');

        $record->addRelationship('options', [
            'tmp' => [
                'definition' => $propertyGroupOptionDefinition,
            ],
            'data' => [],
        ]);

        $tagDefinition = static::createStub(TagDefinition::class);
        $tagDefinition->method('getEntityName')
            ->willReturn('tag');

        $record->addRelationship('tags', [
            'tmp' => [
                'definition' => $tagDefinition,
            ],
            'data' => [],
        ]);

        return $record;
    }
}
