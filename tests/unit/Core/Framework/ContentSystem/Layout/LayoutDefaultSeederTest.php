<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Contena\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Contena\Core\Framework\ContentSystem\Layout\Type\PrimitiveDefaultProvider;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Test\Stub\ContentSystem\ContentSystemElementTypeSpecificationBuilder;

/**
 * @internal
 */
#[CoversClass(LayoutDefaultSeeder::class)]
class LayoutDefaultSeederTest extends TestCase
{
    #[TestDox('seeds a missing primitive default and ignores reference properties on a content element')]
    public function testSeedsPrimitiveDefaultIgnoringReferences(): void
    {
        $element = new ContentElement('el', 'CT:Block');

        $this->seeder()->seed([$element]);

        static::assertSame(['headline' => 'Default headline'], $element->getProperties());
    }

    #[TestDox('does not overwrite an authored primitive value on a content element')]
    public function testKeepsAuthoredValue(): void
    {
        $element = new ContentElement('el', 'CT:Block', [], ['headline' => 'Authored']);

        $this->seeder()->seed([$element]);

        static::assertSame('Authored', $element->getProperty('headline'));
    }

    #[TestDox('seeds primitive defaults on slot descendants')]
    public function testSeedsSlotDescendants(): void
    {
        $child = new ContentElement('child', 'CT:Block');
        $root = new ContentElement('root', 'CT:Block', [], [], ['content' => new SlotContent([$child])]);

        $this->seeder()->seed([$root]);

        static::assertSame('Default headline', $child->getProperty('headline'));
    }

    #[TestDox('leaves a node whose component type is not registered untouched')]
    public function testNoOpsOnUnregisteredComponent(): void
    {
        $element = new ContentElement('el', 'CT:Unregistered');

        $this->seeder()->seed([$element]);

        static::assertSame([], $element->getProperties());
    }

    #[TestDox('seeds a missing primitive default into a raw element array and recurses raw slots')]
    public function testSeedsRawArrayNodesAndRecursesSlots(): void
    {
        $forest = [[
            'id' => 'root',
            'component' => 'CT:Block',
            'properties' => [],
            'slots' => [
                'content' => [
                    ['id' => 'child', 'component' => 'CT:Block', 'properties' => []],
                ],
            ],
        ]];

        $expected = [[
            'id' => 'root',
            'component' => 'CT:Block',
            'properties' => ['headline' => 'Default headline'],
            'slots' => [
                'content' => [
                    ['id' => 'child', 'component' => 'CT:Block', 'properties' => ['headline' => 'Default headline']],
                ],
            ],
        ]];

        static::assertSame($expected, $this->seeder()->seed($forest));
    }

    #[TestDox('leaves a malformed scalar properties value untouched (no silent transform)')]
    public function testSeedRawArrayLeavesScalarPropertiesUntouched(): void
    {
        $forest = [['id' => 'el', 'component' => 'CT:Block', 'properties' => 'oops']];

        static::assertSame($forest, $this->seeder()->seed($forest));
    }

    #[TestDox('leaves a malformed list-shaped properties value untouched rather than mixing key types')]
    public function testSeedRawArrayLeavesListShapedPropertiesUntouched(): void
    {
        $forest = [['id' => 'el', 'component' => 'CT:Block', 'properties' => ['first', 'second']]];

        static::assertSame($forest, $this->seeder()->seed($forest));
    }

    #[TestDox('leaves a raw node without a string component untouched')]
    public function testSeedRawArrayLeavesNonStringComponentUntouched(): void
    {
        $forest = [['id' => 'el', 'slots' => []]];

        static::assertSame($forest, $this->seeder()->seed($forest));
    }

    #[TestDox('does not add a properties key to a registered component that has no primitive defaults')]
    public function testSeedRawArrayAddsNoPropertiesKeyWhenTypeHasNoDefaults(): void
    {
        $forest = [['id' => 'el', 'component' => 'CT:NoDefaults']];

        static::assertSame($forest, $this->seeder()->seed($forest));
    }

    #[TestDox('leaves a raw slot whose value is not a list untouched')]
    public function testSeedRawArrayLeavesNonListSlotValueUntouched(): void
    {
        $forest = [['id' => 'el', 'component' => 'CT:NoDefaults', 'slots' => ['content' => 'not-a-list']]];

        static::assertSame($forest, $this->seeder()->seed($forest));
    }

    private function seeder(): LayoutDefaultSeeder
    {
        $specs = [
            'CT:Block' => ContentSystemElementTypeSpecificationBuilder::create('CT:Block')
                ->primitive('headline', 'string', default: 'Default headline')
                ->reference('blog', ChannelBlogEntity::class)
                ->build(),
            'CT:NoDefaults' => ContentSystemElementTypeSpecificationBuilder::create('CT:NoDefaults')
                ->primitive('label', 'string')
                ->build(),
        ];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return new LayoutDefaultSeeder($registry, new PrimitiveDefaultProvider());
    }
}
