<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogTypeRegistry;

/**
 * @internal
 */
#[CoversClass(BlogTypeRegistry::class)]
class BlogTypeRegistryTest extends TestCase
{
    public function testConstructorDeduplicatesAndNormalizesTypes(): void
    {
        $registry = new BlogTypeRegistry([
            'post',
            'post',
            'media',
        ]);

        static::assertSame([
            'post',
            'media',
        ], $registry->getTypes());
    }

    public function testAddTypeAppendsOnlyWhenMissing(): void
    {
        $registry = new BlogTypeRegistry(['post']);

        $registry->addType('media');
        $registry->addType('media');

        static::assertSame([
            'post',
            'media',
        ], $registry->getTypes());

        static::assertSame([
            'post',
            'media',
        ], $registry->getChoices());
    }

    public function testHasTypeChecksRegisteredTypes(): void
    {
        $registry = new BlogTypeRegistry(['post']);

        static::assertTrue($registry->hasType('post'));
        static::assertFalse($registry->hasType('media'));
    }
}
