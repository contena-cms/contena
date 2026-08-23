<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Sitemap\Provider\CustomUrlProvider;
use Contena\Core\Content\Sitemap\Service\ConfigHandler;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(CustomUrlProvider::class)]
class CustomUrlProviderTest extends TestCase
{
    public function testGetUrlsReturnsNoUrls(): void
    {
        $configHandler = $this->createMock(ConfigHandler::class);
        $configHandler->expects($this->once())->method('get')->willReturnCallback(
            static function (string $key): array {
                static::assertSame(ConfigHandler::CUSTOM_URLS_KEY, $key);

                return [];
            }
        );

        $customUrlProvider = $this->getCustomUrlProvider($configHandler);

        $channelContext = static::createStub(ChannelContext::class);

        static::assertSame([], $customUrlProvider->getUrls($channelContext, 100)->getUrls());
    }

    public function testGetUrlsReturnsAllUrlsForChannel(): void
    {
        $channelContext = static::createStub(ChannelContext::class);

        $configHandler = $this->createMock(ConfigHandler::class);
        $configHandler->expects($this->once())->method('get')->willReturnCallback(
            static function (string $key) use ($channelContext): array {
                static::assertSame(ConfigHandler::CUSTOM_URLS_KEY, $key);

                return [
                    [
                        'url' => 'foo',
                        'lastMod' => new \DateTimeImmutable(),
                        'changeFreq' => 'weekly',
                        'priority' => 0.5,
                        'channelId' => 2,
                    ], [
                        'url' => 'bar',
                        'lastMod' => new \DateTimeImmutable(),
                        'changeFreq' => 'weekly',
                        'priority' => 0.5,
                        'channelId' => $channelContext->getChannelId(),
                    ],
                ];
            }
        );

        $customUrlProvider = $this->getCustomUrlProvider($configHandler);

        static::assertCount(1, $customUrlProvider->getUrls($channelContext, 100)->getUrls());
    }

    public function testGetUrlsReturnsAllUrlsForChannelIdNull(): void
    {
        $channelContext = static::createStub(ChannelContext::class);

        $configHandler = $this->createMock(ConfigHandler::class);
        $configHandler->expects($this->once())->method('get')->willReturnCallback(
            static function (string $key): array {
                static::assertSame(ConfigHandler::CUSTOM_URLS_KEY, $key);

                return [
                    [
                        'url' => 'foo',
                        'lastMod' => new \DateTimeImmutable(),
                        'changeFreq' => 'weekly',
                        'priority' => 0.5,
                        'channelId' => 2,
                    ], [
                        'url' => 'bar',
                        'lastMod' => new \DateTimeImmutable(),
                        'changeFreq' => 'weekly',
                        'priority' => 0.5,
                        'channelId' => null,
                    ], [
                        'url' => 'fooBar',
                        'lastMod' => new \DateTimeImmutable(),
                        'changeFreq' => 'weekly',
                        'priority' => 0.5,
                        'channelId' => null,
                    ],
                ];
            }
        );

        $customUrlProvider = $this->getCustomUrlProvider($configHandler);

        $urls = $customUrlProvider->getUrls($channelContext, 100)->getUrls();

        [$firstUrl, $secondUrl] = $urls;
        static::assertCount(2, $urls);
        static::assertSame('bar', $firstUrl->getLoc());
        static::assertSame('fooBar', $secondUrl->getLoc());
    }

    public function testGetUrlsReturnsNoUrlsWrongChannelId(): void
    {
        $channelContext = static::createStub(ChannelContext::class);

        $configHandler = $this->createMock(ConfigHandler::class);
        $configHandler->expects($this->once())->method('get')->willReturnCallback(
            static function (string $key): array {
                static::assertSame(ConfigHandler::CUSTOM_URLS_KEY, $key);

                return [
                    [
                        'url' => 'foo',
                        'lastMod' => new \DateTimeImmutable(),
                        'changeFreq' => 'weekly',
                        'priority' => 0.5,
                        'channelId' => 2,
                    ],
                ];
            }
        );

        $customUrlProvider = $this->getCustomUrlProvider($configHandler);

        static::assertEmpty($customUrlProvider->getUrls($channelContext, 100)->getUrls());
    }

    private function getCustomUrlProvider(ConfigHandler $configHandler): CustomUrlProvider
    {
        return new CustomUrlProvider($configHandler);
    }
}
