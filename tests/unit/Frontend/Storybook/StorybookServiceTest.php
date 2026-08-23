<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Storybook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Generator;
use Contena\Frontend\Storybook\StorybookService;
use Contena\Frontend\Theme\DatabaseChannelThemeLoader;
use Contena\Frontend\Theme\ThemeRuntimeConfigStorage;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(StorybookService::class)]
class StorybookServiceTest extends TestCase
{
    /**
     * @var ChannelRepository<ChannelBlogCollection>&Stub
     */
    private ChannelRepository&Stub $blogRepository;

    /**
     * @var EntityRepository<MediaCollection>&Stub
     */
    private EntityRepository&Stub $mediaRepository;

    /**
     * @var EntityRepository<ChannelCollection>&Stub
     */
    private EntityRepository&Stub $channelRepository;

    private AbstractChannelContextFactory&Stub $contextFactory;

    private DatabaseChannelThemeLoader&Stub $themeLoader;

    private ThemeRuntimeConfigStorage&Stub $themeRuntimeConfigStorage;

    protected function setUp(): void
    {
        $this->blogRepository = static::createStub(ChannelRepository::class);
        $this->mediaRepository = static::createStub(EntityRepository::class);
        $this->channelRepository = static::createStub(EntityRepository::class);
        $this->contextFactory = static::createStub(AbstractChannelContextFactory::class);
        $this->themeLoader = static::createStub(DatabaseChannelThemeLoader::class);
        $this->themeRuntimeConfigStorage = static::createStub(ThemeRuntimeConfigStorage::class);
    }

    public function testCreateChannelContextReturnsChannelContext(): void
    {
        $channelContext = Generator::generateChannelContext();
        $channelId = $channelContext->getChannelId();

        $this->channelRepository->method('searchIds')
            ->willReturn($this->createChannelIdSearchResult($channelId));

        $this->contextFactory->method('create')
            ->willReturn($channelContext);

        $result = $this->createService()->createChannelContext();

        static::assertSame($channelContext, $result);
    }

    public function testCreateChannelContextThrowsWhenNoChannelAvailable(): void
    {
        $this->channelRepository->method('searchIds')
            ->willReturn(new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()));

        $this->expectException(ChannelException::class);

        $this->createService()->createChannelContext();
    }

    public function testGetThemeIdReturnsThemeIdFromTechnicalName(): void
    {
        $this->themeLoader->method('load')
            ->willReturn(['Frontend']);

        $this->themeRuntimeConfigStorage->method('getThemeIdByTechnicalName')
            ->willReturn('theme-id-123');

        $result = $this->createService()->getThemeId('channel-id');

        static::assertSame('theme-id-123', $result);
    }

    public function testGetThemeIdReturnsNullWhenThemeLoaderReturnsEmpty(): void
    {
        $this->themeLoader->method('load')->willReturn([]);

        $themeRuntimeConfigStorage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $themeRuntimeConfigStorage->expects($this->never())
            ->method('getThemeIdByTechnicalName');

        $result = $this->createService($themeRuntimeConfigStorage)->getThemeId('channel-id');

        static::assertNull($result);
    }

    public function testResolveComponentPropsFiltersDenyListedQueryParams(): void
    {
        $channelContext = Generator::generateChannelContext();

        $request = new Request([
            'label' => 'Click me',
            'measureEnabled' => 'true',
            'backgrounds' => 'dark',
            'outline' => '1',
            'viewport' => 'mobile',
        ]);

        $result = $this->createService()->resolveComponentProps($request, $channelContext);

        static::assertArrayHasKey('label', $result);
        static::assertSame('Click me', $result['label']);
        static::assertArrayNotHasKey('measureEnabled', $result);
        static::assertArrayNotHasKey('backgrounds', $result);
        static::assertArrayNotHasKey('outline', $result);
        static::assertArrayNotHasKey('viewport', $result);
    }

    public function testResolveComponentPropsFiltersInvalidQueryParamIdentifiers(): void
    {
        $channelContext = Generator::generateChannelContext();

        $request = new Request([
            'validProp' => 'hello',
            '123invalid' => 'bad',
            'also-invalid' => 'bad',
            'valid_prop2' => 'world',
        ]);

        $result = $this->createService()->resolveComponentProps($request, $channelContext);

        static::assertArrayHasKey('validProp', $result);
        static::assertArrayHasKey('valid_prop2', $result);
        static::assertArrayNotHasKey('123invalid', $result);
        static::assertArrayNotHasKey('also-invalid', $result);
    }

    public function testResolveComponentPropsResolvesBlogEntityProperty(): void
    {
        $channelContext = Generator::generateChannelContext();

        $blog = new ChannelBlogEntity();
        $blog->setId('blog-id-123');
        $blog->setUniqueIdentifier('blog-id-123');

        $this->blogRepository->method('search')
            ->willReturn(new EntitySearchResult(
                1,
                new ChannelBlogCollection([$blog]),
                null,
                new Criteria(),
                $channelContext->getContext()
            ));

        $result = $this->createService()->resolveComponentProps(
            new Request(['blog' => 'blog']),
            $channelContext
        );

        static::assertArrayHasKey('blog', $result);
        static::assertSame($blog, $result['blog']);
    }

    public function testResolveComponentPropsResolvesMediaEntityProperty(): void
    {
        $channelContext = Generator::generateChannelContext();

        $media = new MediaEntity();
        $media->setId('media-id-123');
        $media->setUniqueIdentifier('media-id-123');

        $this->mediaRepository->method('search')
            ->willReturn(new EntitySearchResult(
                1,
                new MediaCollection([$media]),
                null,
                new Criteria(),
                $channelContext->getContext()
            ));

        $result = $this->createService()->resolveComponentProps(
            new Request(['media' => 'media']),
            $channelContext
        );

        static::assertArrayHasKey('media', $result);
        static::assertSame($media, $result['media']);
    }

    public function testResolveComponentPropsReturnsNullForBlogWhenRepositoryIsEmpty(): void
    {
        $channelContext = Generator::generateChannelContext();

        $this->blogRepository->method('search')
            ->willReturn(new EntitySearchResult(
                0,
                new ChannelBlogCollection(),
                null,
                new Criteria(),
                $channelContext->getContext()
            ));

        $result = $this->createService()->resolveComponentProps(
            new Request(['blog' => 'blog']),
            $channelContext
        );

        static::assertArrayHasKey('blog', $result);
        static::assertNull($result['blog']);
    }

    private function createService(?ThemeRuntimeConfigStorage $themeRuntimeConfigStorage = null): StorybookService
    {
        return new StorybookService(
            $this->blogRepository,
            $this->mediaRepository,
            $this->channelRepository,
            $this->contextFactory,
            $this->themeLoader,
            $themeRuntimeConfigStorage ?? $this->themeRuntimeConfigStorage,
        );
    }

    private function createChannelIdSearchResult(string $channelId): IdSearchResult
    {
        return new IdSearchResult(
            1,
            [$channelId => ['primaryKey' => $channelId, 'data' => []]],
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
