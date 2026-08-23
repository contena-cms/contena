<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Framework\Routing\CachedDomainLoader;
use Contena\Frontend\Theme\Event\ThemeAssignedEvent;
use Contena\Frontend\Theme\Event\ThemeConfigChangedEvent;
use Contena\Frontend\Theme\ThemeConfigCacheInvalidator;

/**
 * @internal
 */
#[CoversClass(ThemeConfigCacheInvalidator::class)]
class ThemeConfigCacheInvalidatorTest extends TestCase
{
    private ThemeConfigCacheInvalidator $themeConfigCacheInvalidator;

    private MockedCacheInvalidator $cacheInvalidator;

    protected function setUp(): void
    {
        $this->cacheInvalidator = new MockedCacheInvalidator();
        $this->themeConfigCacheInvalidator = new ThemeConfigCacheInvalidator($this->cacheInvalidator);
    }

    public function testAssigned(): void
    {
        $themeId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $event = new ThemeAssignedEvent($themeId, $channelId, Context::createDefaultContext());
        $name = 'theme-config-' . $themeId;

        $this->themeConfigCacheInvalidator->assigned($event);

        $expectedInvalidatedTags = [
            $name,
            CachedDomainLoader::DOMAIN_COLLECTION_CACHE_KEY,
            Translator::tag($channelId),
        ];

        static::assertSame(
            $expectedInvalidatedTags,
            $this->cacheInvalidator->getInvalidatedTags()
        );
    }

    public function testInvalidate(): void
    {
        $themeId = Uuid::randomHex();
        $event = new ThemeConfigChangedEvent($themeId, ['test' => 'test'], Context::createDefaultContext());

        $this->themeConfigCacheInvalidator->invalidate($event);

        $expectedInvalidatedTags = ['theme-config-' . $themeId];

        static::assertSame(
            $expectedInvalidatedTags,
            $this->cacheInvalidator->getInvalidatedTags()
        );
    }
}
