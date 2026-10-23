<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Blog\Exception\BlogNotFoundException;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Page\Blog\BlogPageLoadedEvent;
use Contena\Frontend\Page\Blog\BlogPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class BlogPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendPageTestBehaviour;

    public function testItRequiresBlogParam(): void
    {
        $request = new Request();
        $context = $this->createChannelContextWithNavigation();

        $this->expectParamMissingException('blogId');
        $this->getPageLoader()->load($request, $context);
    }

    public function testItRequiresAValidBlogParam(): void
    {
        $request = new Request([], [], ['blogId' => '99999911ffff4fffafffffff19830531']);
        $context = $this->createChannelContextWithNavigation();

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(BlogNotFoundException::class);
        } else {
            $this->expectException(BlogException::class);
        }

        $this->getPageLoader()->load($request, $context);
    }

    public function testItFailsWithANonExistingBlog(): void
    {
        $context = $this->createChannelContextWithNavigation();
        $request = new Request([], [], ['blogId' => Uuid::randomHex()]);

        $event = null;
        $this->catchEvent(BlogPageLoadedEvent::class, $event);

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(BlogNotFoundException::class);
        } else {
            $this->expectException(BlogException::class);
        }

        $this->getPageLoader()->load($request, $context);
    }

    public function testItDoesLoadATestBlog(): void
    {
        $context = $this->createChannelContextWithNavigation();
        $blog = $this->getRandomBlog($context);

        $request = new Request([], [], ['blogId' => $blog->getId()]);

        $event = null;
        $this->catchEvent(BlogPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame(FrontendPageTestConstants::BLOG_NAME, $page->getBlog()->getName());
        self::assertPageEvent(BlogPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItDispatchPageCriteriaEvent(): void
    {
        $context = $this->createChannelContextWithNavigation();
        $blog = $this->getRandomBlog($context);

        $request = new Request([], [], ['blogId' => $blog->getId()]);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame($page->getBlog()->getId(), $blog->getId());
    }

    public function testItDoesLoadACloseBlogWithHideCloseEnabled(): void
    {
        $context = $this->createChannelContextWithNavigation();

        // enable hideCloseoutBlogsWhenOutOfStock filter
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', true);

        $blog = $this->getRandomBlog($context, 1, true);

        $request = new Request([], [], ['blogId' => $blog->getId()]);

        $event = null;
        $this->catchEvent(BlogPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame(FrontendPageTestConstants::BLOG_NAME, $page->getBlog()->getName());
        self::assertPageEvent(BlogPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItDoesFailWithACloseBlogWithHideCloseEnabledWhenOutOfStock(): void
    {
        $context = $this->createChannelContextWithNavigation();

        // enable hideCloseoutBlogsWhenOutOfStock filter
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', true);

        $blog = $this->getRandomBlog($context, 0, true);

        $request = new Request([], [], ['blogId' => $blog->getId()]);

        $event = null;
        $this->catchEvent(BlogPageLoadedEvent::class, $event);

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(BlogNotFoundException::class);
        } else {
            $this->expectException(BlogException::class);
        }

        $this->getPageLoader()->load($request, $context);
    }

    public function testItDoesLoadACmsBlogDetailPage(): void
    {
        $context = $this->createChannelContextWithNavigation();
        $cmsPageId = Uuid::randomHex();
        $blogCmsPageData = [
            'cmsPage' => [
                'id' => $cmsPageId,
                'type' => 'blog_detail',
                'sections' => [],
            ],
            'cover' => [
                'id' => Uuid::randomHex(),
                'position' => 0,
                'media' => [
                    'fileName' => 'cover-1',
                ],
            ],
            'media' => [
                [
                    'id' => Uuid::randomHex(),
                    'position' => 0,
                    'media' => [
                        'fileName' => 'media-1',
                    ],
                ],
                [
                    'id' => Uuid::randomHex(),
                    'position' => 2,
                    'media' => [
                        'fileName' => 'media-2',
                    ],
                ],
            ],
        ];

        $blog = $context->getContext()->scope(Context::SYSTEM_SCOPE, fn (): BlogEntity => $this->getRandomBlog($context, 10, false, $blogCmsPageData));

        static::assertSame($cmsPageId, $blog->getCmsPageId());
        $request = new Request([], [], ['blogId' => $blog->getId()]);

        $event = null;
        $this->catchEvent(BlogPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CmsPageEntity::class, $page->getCmsPage());
        static::assertSame($cmsPageId, $page->getCmsPage()->getId());

        static::assertSame(FrontendPageTestConstants::BLOG_NAME, $page->getBlog()->getName());
        self::assertPageEvent(BlogPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testSlotOverwrite(): void
    {
        $context = $this->createChannelContextWithNavigation();
        $cmsPageId = Uuid::randomHex();
        $firstSlotId = Uuid::randomHex();
        $secondSlotId = Uuid::randomHex();
        $blogCmsPageData = [
            'cmsPage' => [
                'id' => $cmsPageId,
                'type' => 'blog_detail',
                'sections' => [
                    [
                        'id' => Uuid::randomHex(),
                        'type' => 'default',
                        'position' => 0,
                        'blocks' => [
                            [
                                'type' => 'text',
                                'position' => 0,
                                'slots' => [
                                    [
                                        'id' => $firstSlotId,
                                        'type' => 'text',
                                        'slot' => 'content',
                                        'config' => [
                                            'content' => [
                                                'source' => 'static',
                                                'value' => 'initial',
                                            ],
                                        ],
                                    ],
                                    [
                                        'id' => $secondSlotId,
                                        'type' => 'text',
                                        'slot' => 'content',
                                        'config' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'slotConfig' => [
                $firstSlotId => [
                    'content' => [
                        'source' => 'static',
                        'value' => 'overwrittenByBlog',
                    ],
                ],
                $secondSlotId => [
                    'content' => [
                        'source' => 'static',
                        'value' => 'overwrittenByBlog',
                    ],
                ],
            ],
        ];

        $blog = $this->getRandomBlog($context, 10, false, $blogCmsPageData);
        $request = new Request([], [], ['blogId' => $blog->getId()]);

        $event = null;
        $this->catchEvent(BlogPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);
        $cmsPage = $page->getCmsPage();
        $fieldConfigCollection = new FieldConfigCollection([new FieldConfig('content', 'static', 'overwrittenByBlog')]);

        static::assertNotNull($cmsPage);
        static::assertIsIterable($cmsPage->getSections());
        static::assertNotNull($cmsPage->getSections()->first());
        static::assertIsIterable($cmsPage->getSections()->first()->getBlocks());

        $blocks = $cmsPage->getSections()->first()->getBlocks();
        static::assertInstanceOf(CmsBlockCollection::class, $blocks);
        static::assertNotNull($blocks->getSlots()->get($firstSlotId));
        static::assertNotNull($blocks->getSlots()->get($secondSlotId));

        static::assertEquals(
            $blogCmsPageData['slotConfig'][$firstSlotId],
            $blocks->getSlots()->get($firstSlotId)->getConfig()
        );

        static::assertEquals(
            $fieldConfigCollection,
            $blocks->getSlots()->get($firstSlotId)->getFieldConfig()
        );

        static::assertEquals(
            $blogCmsPageData['slotConfig'][$secondSlotId],
            $blocks->getSlots()->get($secondSlotId)->getConfig()
        );
    }

    protected function getPageLoader(): BlogPageLoader
    {
        return static::getContainer()->get(BlogPageLoader::class);
    }
}
