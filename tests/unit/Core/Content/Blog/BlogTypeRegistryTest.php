<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogTypeRegistry;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(BlogTypeRegistry::class)]
class BlogTypeRegistryTest extends TestCase
{
    public function testConstructorDeduplicatesAndNormalizesTypes(): void
    {
        $registry = new BlogTypeRegistry([
            'standard',
            'standard',
            'download',
        ]);

        static::assertSame([
            'standard',
            'download',
        ], $registry->getTypes());
    }

    public function testAddTypeAppendsOnlyWhenMissing(): void
    {
        $registry = new BlogTypeRegistry(['standard']);

        $registry->addType('download');
        $registry->addType('download');

        static::assertSame([
            'standard',
            'download',
        ], $registry->getTypes());

        static::assertSame([
            'standard',
            'download',
        ], $registry->getChoices());
    }

    public function testHasTypeChecksRegisteredTypes(): void
    {
        $registry = new BlogTypeRegistry(['standard']);

        static::assertTrue($registry->hasType('standard'));
        static::assertFalse($registry->hasType('download'));
    }
}
