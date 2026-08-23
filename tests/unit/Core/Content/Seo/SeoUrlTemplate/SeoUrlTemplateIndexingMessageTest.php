<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SeoUrlTemplate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingMessage;
use Contena\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(SeoUrlTemplateIndexingMessage::class)]
class SeoUrlTemplateIndexingMessageTest extends TestCase
{
    public function testItExposesRouteAndEntityName(): void
    {
        $message = new SeoUrlTemplateIndexingMessage('frontend.navigation.page', 'category');

        static::assertSame('frontend.navigation.page', $message->routeName);
        static::assertSame('category', $message->entityName);
        static::assertNull($message->offset);
    }

    public function testItExposesIteratorOffsetForChainedMessages(): void
    {
        $message = new SeoUrlTemplateIndexingMessage('frontend.detail.page', 'blog', ['offset' => 4711]);

        static::assertSame(['offset' => 4711], $message->offset);
    }

    public function testItCarriesTenantContextAcrossChainedMessages(): void
    {
        $context = Context::createTenantContext('tenant-a');
        $message = new SeoUrlTemplateIndexingMessage('frontend.detail.page', 'blog', context: $context);

        static::assertSame($context, $message->getContext());
        static::assertSame('tenant-a', $message->getContext()->getTenantId());
    }
}
