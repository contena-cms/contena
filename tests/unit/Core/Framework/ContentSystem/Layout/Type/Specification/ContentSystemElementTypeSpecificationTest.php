<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;

/**
 * @internal
 */
#[CoversClass(ContentSystemElementTypeSpecification::class)]
class ContentSystemElementTypeSpecificationTest extends TestCase
{
    #[TestDox('includes all top-level scalar fields in schema')]
    public function testToSchemaIncludesTopLevelScalarFields(): void
    {
        $spec = $this->createSpecification('card', 'commerce');
        $schema = $spec->toSchema();

        static::assertSame('CT:Blog:Card', $schema['name']);
        static::assertSame('Blog Card', $schema['label']);
        static::assertSame('A blog card.', $schema['description']);
        static::assertSame('test', $schema['source']);
        static::assertSame('card', $schema['icon']);
        static::assertSame('commerce', $schema['category']);
    }

    #[TestDox('includes property keys and slots in schema')]
    public function testToSchemaIncludesPropertyKeysAndSlots(): void
    {
        $spec = $this->createFullSpecification();
        $schema = $spec->toSchema();

        static::assertCount(2, $schema['properties']);
        static::assertArrayHasKey('blog', $schema['properties']);
        static::assertArrayHasKey('layout', $schema['properties']);
        static::assertCount(1, $schema['slots']);
        static::assertIsArray($schema['slots'][0]);
    }

    #[TestDox('includes null for absent optional fields and empty collections')]
    public function testToSchemaHandlesAbsentOptionalFields(): void
    {
        $spec = $this->createSpecification(null, null);
        $schema = $spec->toSchema();

        static::assertNull($schema['icon']);
        static::assertNull($schema['category']);
        static::assertIsArray($schema['copilot']);
        static::assertSame([], $schema['properties']);
        static::assertSame([], $schema['slots']);
    }

    #[TestDox('includes source field in schema output')]
    public function testToSchemaIncludesSource(): void
    {
        $specification = new ContentSystemElementTypeSpecification(
            'CT:Content:Text',
            'Text',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            [],
            [],
            'core'
        );

        static::assertSame('core', $specification->toSchema()['source']);
    }

    private function createSpecification(?string $icon, ?string $category): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            'CT:Blog:Card',
            'Blog Card',
            'A blog card.',
            $icon,
            $category,
            new CopilotSpecification('Blog card', ['Use for single blogs']),
            [],
            [],
            'test',
        );
    }

    private function createFullSpecification(): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            'CT:Blog:Card',
            'Blog Card',
            'A blog card.',
            'card',
            'commerce',
            new CopilotSpecification('Blog card', ['Use for single blogs']),
            [
                'blog' => new PropertySpecification(
                    'blog',
                    new PropertyType('Contena\Core\Content\Blog\BlogEntity', false, null, null, null),
                    true,
                    'Blog',
                    'The blog.',
                    null,
                ),
                'layout' => new PropertySpecification(
                    'layout',
                    new PropertyType('string', false, ['box', 'list'], 'box', null),
                    false,
                    'Layout',
                    'Layout variant.',
                    null,
                ),
            ],
            [
                new SlotSpecification('media', 1, ['CT:Media:Image'], 'Media slot.'),
            ],
            'test',
        );
    }
}
