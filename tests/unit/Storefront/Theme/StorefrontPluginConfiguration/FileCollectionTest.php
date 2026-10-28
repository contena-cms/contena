<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme\StorefrontPluginConfiguration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FileCollection::class)]
class FileCollectionTest extends TestCase
{
    public function testCreateFromArray(): void
    {
        $collection = FileCollection::createFromArray([
            'foo',
            'bar',
        ]);

        static::assertCount(2, $collection);
        static::assertSame(['foo', 'bar'], $collection->getFilepaths());
        static::assertSame([], $collection->getResolveMappings());
    }

    public function testResolveMappingGetsMerged(): void
    {
        $collection = new FileCollection([
        ]);

        static::assertSame(['a' => 'b', 'b' => 'c'], $collection->getResolveMappings());
    }

    public function testGetPublicPaths(): void
    {
        $collection = new FileCollection([
        ]);

        static::assertSame(['baz/foo/bar.js'], $collection->getPublicPaths('baz'));
    }
}
