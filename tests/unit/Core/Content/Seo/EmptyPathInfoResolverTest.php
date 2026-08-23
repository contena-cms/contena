<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\AbstractSeoResolver;
use Contena\Core\Content\Seo\EmptyPathInfoResolver;
use Contena\Core\Content\Seo\ResolvedSeoUrl;
use Contena\Core\Content\Seo\SeoUrlRequestContext;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(EmptyPathInfoResolver::class)]
class EmptyPathInfoResolverTest extends TestCase
{
    public function testResolveUrlReturnsRootForEmptyPath(): void
    {
        $decorated = static::createMock(AbstractSeoResolver::class);
        $decorated->expects($this->never())->method('resolveUrl');

        $resolver = new EmptyPathInfoResolver($decorated);

        $resolved = $resolver->resolveUrl(new SeoUrlRequestContext(Uuid::randomHex(), Uuid::randomHex(), ''));

        static::assertSame('/', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
    }

    public function testResolveUrlReturnsRootForSlashOnlyPath(): void
    {
        $decorated = static::createMock(AbstractSeoResolver::class);
        $decorated->expects($this->never())->method('resolveUrl');

        $resolver = new EmptyPathInfoResolver($decorated);

        $resolved = $resolver->resolveUrl(new SeoUrlRequestContext(Uuid::randomHex(), Uuid::randomHex(), '/'));

        static::assertSame('/', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
    }

    public function testResolveUrlDelegatesNonEmptyPathToDecorated(): void
    {
        $expected = new ResolvedSeoUrl(pathInfo: '/detail/1234', isCanonical: true);

        $decorated = static::createMock(AbstractSeoResolver::class);
        $decorated
            ->expects($this->once())
            ->method('resolveUrl')
            ->with(static::callback(static fn (SeoUrlRequestContext $context): bool => $context->pathInfo === '/awesome-blog'))
            ->willReturn($expected);

        $resolver = new EmptyPathInfoResolver($decorated);

        $resolved = $resolver->resolveUrl(new SeoUrlRequestContext(Uuid::randomHex(), Uuid::randomHex(), '/awesome-blog'));

        static::assertSame($expected, $resolved);
    }

    public function testGetDecoratedReturnsInjectedResolver(): void
    {
        $decorated = static::createStub(AbstractSeoResolver::class);
        $resolver = new EmptyPathInfoResolver($decorated);

        static::assertSame($decorated, $resolver->getDecorated());
    }
}
