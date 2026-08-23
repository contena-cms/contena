<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\HreflangLoader;
use Contena\Core\Content\Seo\HreflangLoaderParameter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(HreflangLoader::class)]
class HreflangLoaderTest extends TestCase
{
    private RouterInterface&Stub $router;

    private Connection&Stub $connection;

    private HreflangLoader $loader;

    protected function setUp(): void
    {
        $this->router = static::createStub(RouterInterface::class);
        $this->connection = static::createStub(Connection::class);
        $this->loader = new HreflangLoader($this->router, $this->connection);
    }

    public function testSubfolderDeploymentStripsBasePath(): void
    {
        $channelContext = Generator::generateChannelContext();
        $channelContext->getChannel()->setHreflangActive(true);

        $languageId1 = Uuid::randomHex();
        $languageId2 = Uuid::randomHex();
        $domainId1 = Uuid::randomHex();
        $domainId2 = Uuid::randomHex();
        $blogId = Uuid::randomHex();

        $this->router
            ->method('generate')
            ->willReturn('/contena/public/detail/' . $blogId);

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [
                    ['languageId' => Uuid::fromHexToBytes($languageId1), 'id' => Uuid::fromHexToBytes($domainId1), 'url' => 'https://test.de', 'locale' => 'de-DE', 'onlyLocale' => false],
                    ['languageId' => Uuid::fromHexToBytes($languageId2), 'id' => Uuid::fromHexToBytes($domainId2), 'url' => 'https://test.de/en', 'locale' => 'en-GB', 'onlyLocale' => false],
                ],
                [
                    ['seoPathInfo' => 'nice-blog', 'languageId' => Uuid::fromHexToBytes($languageId1)],
                    ['seoPathInfo' => 'nice-blog-en', 'languageId' => Uuid::fromHexToBytes($languageId2)],
                ]
            );

        $parameter = new HreflangLoaderParameter(
            'frontend.detail.page',
            ['blogId' => $blogId],
            $channelContext,
            false,
            '/contena/public'
        );

        $result = $this->loader->load($parameter);

        static::assertCount(2, $result);
        $urls = array_map(static fn ($item) => $item->getUrl(), $result->getElements());
        static::assertContains('https://test.de/nice-blog', $urls);
        static::assertContains('https://test.de/en/nice-blog-en', $urls);
    }

    public function testNoBasePathDoesNotModifyPath(): void
    {
        $channelContext = Generator::generateChannelContext();
        $channelContext->getChannel()->setHreflangActive(true);

        $languageId1 = Uuid::randomHex();
        $languageId2 = Uuid::randomHex();
        $domainId1 = Uuid::randomHex();
        $domainId2 = Uuid::randomHex();
        $blogId = Uuid::randomHex();

        $this->router
            ->method('generate')
            ->willReturn('/detail/' . $blogId);

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [
                    ['languageId' => Uuid::fromHexToBytes($languageId1), 'id' => Uuid::fromHexToBytes($domainId1), 'url' => 'https://test.de', 'locale' => 'de-DE', 'onlyLocale' => false],
                    ['languageId' => Uuid::fromHexToBytes($languageId2), 'id' => Uuid::fromHexToBytes($domainId2), 'url' => 'https://test.de/en', 'locale' => 'en-GB', 'onlyLocale' => false],
                ],
                [
                    ['seoPathInfo' => 'nice-blog', 'languageId' => Uuid::fromHexToBytes($languageId1)],
                    ['seoPathInfo' => 'nice-blog-en', 'languageId' => Uuid::fromHexToBytes($languageId2)],
                ]
            );

        $parameter = new HreflangLoaderParameter(
            'frontend.detail.page',
            ['blogId' => $blogId],
            $channelContext,
        );

        $result = $this->loader->load($parameter);

        static::assertCount(2, $result);
        $urls = array_map(static fn ($item) => $item->getUrl(), $result->getElements());
        static::assertContains('https://test.de/nice-blog', $urls);
        static::assertContains('https://test.de/en/nice-blog-en', $urls);
    }
}
