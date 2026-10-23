<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrlRequestContext;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(SeoUrlRequestContext::class)]
class SeoUrlRequestContextTest extends TestCase
{
    public function testReadonlyConstruction(): void
    {
        $languageId = Uuid::randomHex();
        $channelId = Uuid::randomHex();

        $context = new SeoUrlRequestContext(
            languageId: $languageId,
            channelId: $channelId,
            pathInfo: 'awesome-blog',
            queryString: 'test=123',
        );

        static::assertSame($languageId, $context->languageId);
        static::assertSame($channelId, $context->channelId);
        static::assertSame('awesome-blog', $context->pathInfo);
        static::assertSame('test=123', $context->queryString);
    }

    public function testQueryStringIsOptional(): void
    {
        $context = new SeoUrlRequestContext(
            languageId: Uuid::randomHex(),
            channelId: Uuid::randomHex(),
            pathInfo: 'awesome-blog',
        );

        static::assertNull($context->queryString);
    }
}
