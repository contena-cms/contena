<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\Api\Serializer\Record;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Tag\TagCollection;
use Contena\Core\System\Tag\TagDefinition;
use Contena\Core\System\Tag\TagEntity;
use Contena\Tests\Unit\Core\Framework\Api\Serializer\_fixtures\TestAttributeEntity;

/**
 * @internal
 */
#[CoversClass(Record::class)]
final class RecordTest extends TestCase
{
    public function testSerializeJson(): void
    {
        $record = $this->generateRecord();

        static::assertEquals([
            'id' => 'blog-id',
            'type' => 'blog',
            'attributes' => [
                'active' => true,
                'type' => BlogDefinition::TYPE_POST,
            ],
            'links' => new \stdClass(),
            'relationships' => [
                'categories' => [
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
        $tag = new TagEntity()->assign(['id' => 'tag-id', '_uniqueIdentifier' => 'tag-id']);

        $blog = new BlogEntity()->assign(['id' => 'blog-id', '_uniqueIdentifier' => 'blog-id']);
        $blog->setActive(false);
        $blog->setType(BlogDefinition::TYPE_MEDIA);
        $blog->setTags(new TagCollection([$tag]));
        $blog->setCustomFields([]);

        $record = $this->generateRecord();
        $record->setAttribute('customFields', []);
        $record->merge($blog);

        static::assertEquals([
            'id' => 'blog-id',
            'type' => 'blog',
            'attributes' => [
                'active' => false,
                'type' => BlogDefinition::TYPE_MEDIA,
                'customFields' => new \stdClass(),
            ],
            'links' => new \stdClass(),
            'relationships' => [
                'categories' => [
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
        $entity = new TestAttributeEntity()->assign([
            'id' => 'entity-id',
            '_uniqueIdentifier' => 'entity-id',
            'memberId' => 'member-id',
        ]);
        $entity->blogs = [
            'blog-id' => new BlogEntity()->assign(['id' => 'blog-id', '_uniqueIdentifier' => 'blog-id']),
        ];
        $entity->member = new MemberEntity()->assign(['id' => 'member-id', '_uniqueIdentifier' => 'member-id']);

        $blogDefinition = $this->createMock(BlogDefinition::class);
        $blogDefinition->expects($this->once())
            ->method('getEntityName')
            ->willReturn('blog');

        $memberDefinition = $this->createMock(MemberDefinition::class);
        $memberDefinition->expects($this->once())
            ->method('getEntityName')
            ->willReturn('member');

        $record = new Record('entity-id', 'test_attribute_entity');
        $record->setAttribute('memberId', 'member-id');
        $record->addRelationship('blogs', [
            'tmp' => [
                'definition' => $blogDefinition,
            ],
            'data' => [],
        ]);
        $record->addRelationship('member', [
            'tmp' => [
                'definition' => $memberDefinition,
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
            'member' => [
                'tmp' => [
                    'definition' => $memberDefinition,
                ],
                'data' => [
                    'type' => 'member',
                    'id' => 'member-id',
                ],
            ],
        ], $record->getRelationships());
    }

    private function generateRecord(): Record
    {
        $record = new Record('blog-id', 'blog');
        $record->setAttribute('active', true);
        $record->setAttribute('type', BlogDefinition::TYPE_POST);

        $categoryDefinition = static::createStub(CategoryDefinition::class);
        $categoryDefinition->method('getEntityName')
            ->willReturn('category');

        $record->addRelationship('categories', [
            'tmp' => [
                'definition' => $categoryDefinition,
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
