<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\ResolvedSeoUrl;

/**
 * @internal
 */
#[CoversClass(ResolvedSeoUrl::class)]
class ResolvedSeoUrlTest extends TestCase
{
    public function testAllFieldsAreExposed(): void
    {
        $resolved = new ResolvedSeoUrl(
            pathInfo: '/detail/1234',
            isCanonical: true,
            id: 'binaryId',
            canonicalPathInfo: '/awesome-blog',
            seoPathInfo: 'awesome-blog',
        );

        static::assertSame('/detail/1234', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertSame('binaryId', $resolved->id);
        static::assertSame('/awesome-blog', $resolved->canonicalPathInfo);
        static::assertSame('awesome-blog', $resolved->seoPathInfo);
    }

    public function testOptionalFieldsDefaultToNull(): void
    {
        $resolved = new ResolvedSeoUrl(pathInfo: '/', isCanonical: false);

        static::assertSame('/', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
        static::assertNull($resolved->id);
        static::assertNull($resolved->canonicalPathInfo);
        static::assertNull($resolved->seoPathInfo);
    }
}
