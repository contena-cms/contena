<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Api\Serializer\JsonApiEncoder;
use Contena\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberEntity;
use Contena\Tests\Unit\Core\Framework\Api\Serializer\_fixtures\TestAttributeEntity;
use Contena\Tests\Unit\Core\Framework\Api\Serializer\_fixtures\TestChildEntity;
use Contena\Tests\Unit\Core\Framework\Api\Serializer\_fixtures\TestItemEntity;
use Contena\Tests\Unit\Core\Framework\Api\Serializer\_fixtures\TestParentEntity;
use Symfony\Component\DependencyInjection\Container;

/**
 * @internal
 */
#[CoversClass(JsonApiEncoder::class)]
class JsonApiEncoderTest extends TestCase
{
    public function testEncodeWithAttributeEntity(): void
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

        $definition = $this->getAttributeEntityDefinition();

        $encoder = new JsonApiEncoder();
        $result = $encoder->encode(new Criteria(), $definition, $entity, '/api');
        $result = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $result);
        static::assertArrayHasKey('relationships', $result['data']);
        static::assertCount(2, $result['data']['relationships']);

        foreach ($result['data']['relationships'] as $key => $relationship) {
            static::assertArrayHasKey('data', $relationship);
            static::assertIsArray($relationship['data']);

            if ($key === 'member') {
                static::assertSame([
                    'type' => 'member',
                    'id' => 'member-id',
                ], $relationship['data']);
            }

            if ($key === 'blogs') {
                static::assertCount(1, $relationship['data']);
                static::assertSame([
                    'type' => 'blog',
                    'id' => 'blog-id',
                ], $relationship['data'][0]);
            }
        }
    }

    /**
     * Tests that when the same entity (by ID) is accessed through different relationship paths,
     * and one path has associations loaded while another doesn't, the loaded associations are preserved.
     *
     * Scenario:
     * - Parent entity has childA and childB relationships
     * - Both point to the same Child entity (same ID)
     * - childA has items: [item1] loaded
     * - childB has items: [] (NOT loaded - simulates partial loading)
     * - Expected: Child in included should have items: [item1] preserved (not overwritten by empty)
     *
     * Without the PR fix (using `empty()` instead of `=== null`), the childB's empty items
     * would overwrite childA's loaded items, losing the data.
     */
    public function testSameEntityThroughDifferentPathsPreservesLoadedAssociations(): void
    {
        $sharedChildId = 'shared-child-id';

        // Create an item that will be loaded on childA path
        $item1 = new TestItemEntity()->assign([
            'id' => 'item-1',
            '_uniqueIdentifier' => 'item-1',
            'name' => 'Item One',
        ]);

        // childA instance has items loaded
        $childA = new TestChildEntity()->assign([
            'id' => $sharedChildId,
            '_uniqueIdentifier' => $sharedChildId,
            'name' => 'Shared Child',
        ]);
        $childA->items = ['item-1' => $item1];

        // childB instance (same ID!) has items NOT loaded (null/empty)
        // This simulates accessing the same entity through a different path
        // where the association wasn't eagerly loaded
        $childB = new TestChildEntity()->assign([
            'id' => $sharedChildId,
            '_uniqueIdentifier' => $sharedChildId,
            'name' => 'Shared Child',
        ]);
        $childB->items = null; // Not loaded

        // Parent references the same child through two different paths
        $parent = new TestParentEntity()->assign([
            'id' => 'parent-id',
            '_uniqueIdentifier' => 'parent-id',
            'childAId' => $sharedChildId,
            'childBId' => $sharedChildId,
        ]);
        $parent->childA = $childA;
        $parent->childB = $childB;

        $definition = $this->getParentEntityDefinition();

        $encoder = new JsonApiEncoder();
        $result = $encoder->encode(new Criteria(), $definition, $parent, '/api');
        $result = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);

        // Parent should be in data
        static::assertSame('parent-id', $result['data']['id']);

        // Both childA and childB relationships should reference the same child
        static::assertSame($sharedChildId, $result['data']['relationships']['childA']['data']['id']);
        static::assertSame($sharedChildId, $result['data']['relationships']['childB']['data']['id']);

        // The shared child should appear only ONCE in included
        $childIncludes = array_filter($result['included'], fn ($item) => $item['type'] === 'test_child');
        static::assertCount(1, $childIncludes, 'Child should appear exactly once in included');

        // Get the child's items relationship data
        $child = array_values($childIncludes)[0];
        $childItemsData = $child['relationships']['items']['data'] ?? [];

        // CRITICAL: item-1 should be preserved even though childB had empty items
        // Without the fix, childB's empty items would overwrite childA's loaded items
        $itemIds = array_column($childItemsData, 'id');
        static::assertContains('item-1', $itemIds, 'item-1 from childA path should be preserved');
        static::assertCount(1, $itemIds, 'Only item-1 should be present');

        // item-1 should also be in included
        $itemIncludes = array_filter($result['included'], fn ($item) => $item['type'] === 'test_item');
        static::assertCount(1, $itemIncludes, 'item-1 should be in included');
        static::assertSame('item-1', array_values($itemIncludes)[0]['id']);
    }

    /**
     * Tests collection encoding where Entity B references Entity A which is already in data.
     *
     * Scenario:
     * - Collection contains [EntityA, EntityB]
     * - EntityA has no items loaded
     * - EntityB has childA pointing to EntityA (same ID), and THIS instance has items loaded
     * - When EntityB.childA is processed, EntityA is already in `included` (from first serialization)
     * - Expected: EntityA should get items merged in from EntityB's reference
     *
     * Without the PR fix, when EntityA is encountered via EntityB.childA, it would be
     * skipped (already in included), and the items association would be lost.
     */
    public function testCollectionEncodingMergesAssociationsFromLaterReferences(): void
    {
        $sharedChildId = 'shared-child-id';
        $blogId = 'blog-id';

        // Create the blog
        $blog = new TestItemEntity()->assign([
            'id' => $blogId,
            '_uniqueIdentifier' => $blogId,
            'name' => 'Test Blog',
        ]);

        // First child: NO items loaded
        $childWithoutItems = new TestChildEntity()->assign([
            'id' => $sharedChildId,
            '_uniqueIdentifier' => $sharedChildId,
            'name' => 'Child Without Items',
        ]);
        $childWithoutItems->items = null;

        // Second reference to the same child: HAS items loaded
        $childWithItems = new TestChildEntity()->assign([
            'id' => $sharedChildId, // SAME ID!
            '_uniqueIdentifier' => $sharedChildId,
            'name' => 'Child With Items',
        ]);
        $childWithItems->items = [$blogId => $blog];

        // Parent A: references child without items
        $parentA = new TestParentEntity()->assign([
            'id' => 'parent-a',
            '_uniqueIdentifier' => 'parent-a',
            'childAId' => $sharedChildId,
        ]);
        $parentA->childA = $childWithoutItems;

        // Parent B: references same child WITH items (via childB to use different path)
        $parentB = new TestParentEntity()->assign([
            'id' => 'parent-b',
            '_uniqueIdentifier' => 'parent-b',
            'childBId' => $sharedChildId,
        ]);
        $parentB->childB = $childWithItems;

        $definition = $this->getParentEntityDefinition();

        // Encode as collection: parentA processed first, then parentB
        $collection = new EntityCollection([$parentA, $parentB]);

        $encoder = new JsonApiEncoder();
        $result = $encoder->encode(new Criteria(), $definition, $collection, '/api');
        $result = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);

        // Both parents should be in data
        static::assertCount(2, $result['data']);

        // Child should appear only ONCE in included
        $childIncludes = array_filter($result['included'], fn ($item) => $item['type'] === 'test_child');
        static::assertCount(1, $childIncludes, 'Child should appear exactly once');

        // CRITICAL: The child should have items from parentB's reference
        $child = array_values($childIncludes)[0];
        $itemsData = $child['relationships']['items']['data'] ?? [];
        $itemIds = array_column($itemsData, 'id');

        static::assertContains($blogId, $itemIds, 'Blog should be merged from parentB reference');

        // Blog should be in included
        $blogIncludes = array_filter($result['included'], fn ($item) => $item['type'] === 'test_item');
        static::assertCount(1, $blogIncludes, 'Blog should be in included');
    }

    private function getAttributeEntityDefinition(): AttributeEntityDefinition
    {
        $meta = [
            'entity_name' => 'test_attribute_entity',
            'fields' => [
                [
                    'type' => 'uuid',
                    'name' => 'id',
                    'class' => IdField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['id', 'id'],
                ],
                [
                    'type' => 'fk',
                    'name' => 'memberId',
                    'class' => FkField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['member_id', 'memberId', 'member'],
                ],
                [
                    'type' => 'many-to-one',
                    'name' => 'member',
                    'class' => ManyToOneAssociationField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['member', 'member_id', 'member', 'id'],
                ],
                [
                    'type' => 'many-to-many',
                    'name' => 'blogs',
                    'class' => ManyToManyAssociationField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['blogs', 'blog', 'test_attribute_entity_blog', 'test_attribute_entity_id', 'blog_id'],
                ],
            ],
        ];

        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

        $blogDefinition = new BlogDefinition();
        $blogDefinition->compile($definitionRegistry);
        $memberDefinition = new MemberDefinition();
        $memberDefinition->compile($definitionRegistry);

        $container = new Container();
        $container->set(BlogDefinition::class, $blogDefinition);
        $container->set(MemberDefinition::class, $memberDefinition);
        $container->set(AttributeEntityDefinition::class, static::createStub(AttributeEntityDefinition::class));

        $attributeEntityDefinition = new AttributeEntityDefinition($meta);
        $attributeEntityDefinition->compile(new DefinitionInstanceRegistry($container, [
            'member' => MemberDefinition::class,
            'blog' => BlogDefinition::class,
            'test_attribute_entity_blog' => AttributeEntityDefinition::class,
        ], []));

        return $attributeEntityDefinition;
    }

    private function getParentEntityDefinition(): AttributeEntityDefinition
    {
        $parentMeta = [
            'entity_name' => 'test_parent',
            'fields' => [
                [
                    'type' => 'uuid',
                    'name' => 'id',
                    'class' => IdField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['id', 'id'],
                ],
                [
                    'type' => 'fk',
                    'name' => 'childAId',
                    'class' => FkField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['child_a_id', 'childAId', 'test_child'],
                ],
                [
                    'type' => 'fk',
                    'name' => 'childBId',
                    'class' => FkField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['child_b_id', 'childBId', 'test_child'],
                ],
                [
                    'type' => 'many-to-one',
                    'name' => 'childA',
                    'class' => ManyToOneAssociationField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['childA', 'child_a_id', 'test_child', 'id'],
                ],
                [
                    'type' => 'many-to-one',
                    'name' => 'childB',
                    'class' => ManyToOneAssociationField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['childB', 'child_b_id', 'test_child', 'id'],
                ],
            ],
        ];

        $childMeta = [
            'entity_name' => 'test_child',
            'fields' => [
                [
                    'type' => 'uuid',
                    'name' => 'id',
                    'class' => IdField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['id', 'id'],
                ],
                [
                    'type' => 'one-to-many',
                    'name' => 'items',
                    'class' => OneToManyAssociationField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['items', 'test_item', 'test_child_id'],
                ],
            ],
        ];

        $itemMeta = [
            'entity_name' => 'test_item',
            'fields' => [
                [
                    'type' => 'uuid',
                    'name' => 'id',
                    'class' => IdField::class,
                    'flags' => [],
                    'translated' => false,
                    'args' => ['id', 'id'],
                ],
            ],
        ];

        $childDefinition = new AttributeEntityDefinition($childMeta);
        $itemDefinition = new AttributeEntityDefinition($itemMeta);
        $parentDefinition = new AttributeEntityDefinition($parentMeta);

        $container = new Container();
        $container->set('test_child_definition', $childDefinition);
        $container->set('test_item_definition', $itemDefinition);
        $container->set('test_parent_definition', $parentDefinition);

        $registry = new DefinitionInstanceRegistry($container, [
            'test_child' => 'test_child_definition',
            'test_item' => 'test_item_definition',
            'test_parent' => 'test_parent_definition',
        ], []);

        $itemDefinition->compile($registry);
        $childDefinition->compile($registry);
        $parentDefinition->compile($registry);

        return $parentDefinition;
    }
}
