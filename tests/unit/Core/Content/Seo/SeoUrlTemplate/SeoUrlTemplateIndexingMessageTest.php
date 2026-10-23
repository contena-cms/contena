<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SeoUrlTemplate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateIndexingMessage;
use Contena\Core\Framework\Log\Package;

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
}
