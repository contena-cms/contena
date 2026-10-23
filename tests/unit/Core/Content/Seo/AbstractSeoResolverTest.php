<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\AbstractSeoResolver;
use Contena\Core\Content\Seo\SeoUrlRequestContext;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AbstractSeoResolver::class)]
class AbstractSeoResolverTest extends TestCase
{
    public function testDefaultResolveUrlImplConstructsDtoFromLegacyResolveArray(): void
    {
        $resolver = new class extends AbstractSeoResolver {
            public function getDecorated(): AbstractSeoResolver
            {
                throw new DecorationPatternException(self::class);
            }

            public function resolve(string $languageId, string $channelId, string $pathInfo): array
            {
                return [
                    'id' => 'binaryId',
                    'pathInfo' => '/detail/1234',
                    'isCanonical' => '1',
                    'canonicalPathInfo' => '/awesome-blog',
                    'seoPathInfo' => 'awesome-blog',
                ];
            }
        };

        $resolved = $resolver->resolveUrl(new SeoUrlRequestContext(Uuid::randomHex(), Uuid::randomHex(), '/awesome-blog'));

        static::assertSame('/detail/1234', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical, 'isCanonical coerced from string "1"');
        static::assertSame('binaryId', $resolved->id);
        static::assertSame('/awesome-blog', $resolved->canonicalPathInfo);
        static::assertSame('awesome-blog', $resolved->seoPathInfo);
    }

    public function testDefaultResolveUrlImplDefaultsOptionalFieldsToNull(): void
    {
        $resolver = new class extends AbstractSeoResolver {
            public function getDecorated(): AbstractSeoResolver
            {
                throw new DecorationPatternException(self::class);
            }

            public function resolve(string $languageId, string $channelId, string $pathInfo): array
            {
                return ['pathInfo' => '/foo/bar', 'isCanonical' => false];
            }
        };

        $resolved = $resolver->resolveUrl(new SeoUrlRequestContext(Uuid::randomHex(), Uuid::randomHex(), 'foo/bar'));

        static::assertSame('/foo/bar', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
        static::assertNull($resolved->id);
        static::assertNull($resolved->canonicalPathInfo);
        static::assertNull($resolved->seoPathInfo);
    }
}
