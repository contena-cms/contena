<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Seo\SeoException;
use Contena\Core\Content\Seo\SeoUrlGenerator;
use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Content\Seo\SeoUrlUpdater;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;

/**
 * @internal
 */
#[CoversClass(SeoUrlUpdater::class)]
class SeoUrlUpdaterTest extends TestCase
{
    /**
     * @var StaticEntityRepository<LanguageCollection>
     */
    private StaticEntityRepository $languageRepository;

    private SeoUrlRouteRegistry $seoUrlRouteRegistry;

    private SeoUrlGenerator&MockObject $seoUrlGenerator;

    private SeoUrlPersister&MockObject $seoUrlPersister;

    private Connection&Stub $connection;

    /**
     * @var StaticEntityRepository<ChannelCollection>
     */
    private StaticEntityRepository $channelRepository;

    protected function setUp(): void
    {
        $this->seoUrlGenerator = $this->createMock(SeoUrlGenerator::class);
        $this->seoUrlPersister = $this->createMock(SeoUrlPersister::class);
        $this->connection = static::createStub(Connection::class);
    }

    public function testUpdateWithoutDomain(): void
    {
        $seoUrlUpdater = $this->createSeoUrlUpdater([], [], [new BlogPageSeoUrlRoute(new BlogDefinition())]);

        $this->connection->method('fetchAllAssociative')->willReturn([]);
        $this->seoUrlGenerator->expects($this->never())->method('generate');
        $this->seoUrlPersister->expects($this->never())->method('updateSeoUrls');

        $seoUrlUpdater->update(BlogPageSeoUrlRoute::ROUTE_NAME, []);
    }

    public function testUpdateWithoutDefaultTemplates(): void
    {
        $seoUrlUpdater = $this->createSeoUrlUpdater([], [], [new BlogPageSeoUrlRoute(new BlogDefinition())]);

        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'channelId' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
            ],
        ]);
        $this->connection->method('fetchAllKeyValue')->willReturn([]);

        $this->seoUrlGenerator->expects($this->never())->method('generate');
        $this->seoUrlPersister->expects($this->never())->method('updateSeoUrls');

        $this->expectExceptionObject(new \RuntimeException('Default templates not configured'));
        $seoUrlUpdater->update(BlogPageSeoUrlRoute::ROUTE_NAME, []);
    }

    public function testUpdateWithoutRoute(): void
    {
        $seoUrlUpdater = $this->createSeoUrlUpdater();

        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'channelId' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
            ],
        ]);

        $this->connection->method('fetchAllKeyValue')->willReturn(
            [
                '' => '{{ blog.translated.name }}/{{ blog.blogNumber }}',
            ]
        );

        $this->seoUrlGenerator->expects($this->never())->method('generate');
        $this->seoUrlPersister->expects($this->never())->method('updateSeoUrls');
        $this->expectExceptionObject(SeoException::seoUrlRouteNotFound('test'));

        $seoUrlUpdater->update('test', []);
    }

    public function testUpdateWithOutChannel(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'channelId' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
            ],
        ]);

        $this->connection->method('fetchAllKeyValue')->willReturn(
            [
                '' => '{{ blog.translated.name }}/{{ blog.blogNumber }}',
            ]
        );

        $seoUrlUpdater = $this->createSeoUrlUpdater(
            [
                new LanguageCollection([]),
            ],
            [
                new ChannelCollection([]),
            ],
            [
                new BlogPageSeoUrlRoute(new BlogDefinition()),
            ]
        );

        $this->seoUrlGenerator->expects($this->never())->method('generate');
        $this->seoUrlPersister->expects($this->never())->method('updateSeoUrls');

        $seoUrlUpdater->update(BlogPageSeoUrlRoute::ROUTE_NAME, []);
    }

    public function testUpdateGetPersisted(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'channelId' => 'testSalsesChannelId',
                'languageId' => 'testLanguageId',
            ],
        ]);

        $this->connection->method('fetchAllKeyValue')->willReturn(
            [
                '' => '{{ blog.translated.name }}/{{ blog.blogNumber }}',
            ]
        );

        $channel = new ChannelEntity();
        $channel->setId('testSalsesChannelId');

        $language = new LanguageEntity();
        $language->setId('testLanguageId');

        $seoUrlUpdater = $this->createSeoUrlUpdater(
            [
                new LanguageCollection([
                    $language,
                ]),
            ],
            [
                new ChannelCollection([
                    $channel,
                ]),
            ],
            [
                new BlogPageSeoUrlRoute(new BlogDefinition()),
            ]
        );

        $this->seoUrlGenerator->expects($this->once())->method('generate');
        $this->seoUrlPersister->expects($this->once())->method('updateSeoUrls');

        $seoUrlUpdater->update(BlogPageSeoUrlRoute::ROUTE_NAME, []);
    }

    /**
     * @param LanguageCollection[] $languageSearches
     * @param ChannelCollection[] $channelSearches
     * @param SeoUrlRouteInterface[] $seoUrlRoutes
     */
    private function createSeoUrlUpdater(
        array $languageSearches = [],
        array $channelSearches = [],
        array $seoUrlRoutes = []
    ): SeoUrlUpdater {
        $this->languageRepository = new StaticEntityRepository($languageSearches);
        $this->seoUrlRouteRegistry = new SeoUrlRouteRegistry($seoUrlRoutes);
        $this->channelRepository = new StaticEntityRepository($channelSearches);

        return new SeoUrlUpdater(
            $this->languageRepository,
            $this->seoUrlRouteRegistry,
            $this->seoUrlGenerator,
            $this->seoUrlPersister,
            $this->connection,
            $this->channelRepository
        );
    }
}
