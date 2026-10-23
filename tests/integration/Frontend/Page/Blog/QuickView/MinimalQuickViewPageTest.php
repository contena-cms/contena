<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\Blog\QuickView;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Exception\BlogNotFoundException;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Page\Blog\QuickView\MinimalQuickViewPageCriteriaEvent;
use Contena\Frontend\Page\Blog\QuickView\MinimalQuickViewPageLoadedEvent;
use Contena\Frontend\Page\Blog\QuickView\MinimalQuickViewPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Contena\Tests\Integration\Frontend\Page\FrontendPageTestConstants;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class MinimalQuickViewPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendPageTestBehaviour;

    public function testItRequiresBlogParam(): void
    {
        $request = new Request();
        $context = $this->createChannelContext();

        $this->expectParamMissingException('blogId');
        $this->getPageLoader()->load($request, $context);
    }

    public function testItRequiresAValidBlogParam(): void
    {
        $request = new Request([], [], ['blogId' => '99999911ffff4fffafffffff19830531']);
        $context = $this->createChannelContext();

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(BlogNotFoundException::class);
        } else {
            $this->expectException(BlogException::class);
        }

        $this->getPageLoader()->load($request, $context);
    }

    public function testItFailsWithANonExistingBlog(): void
    {
        $context = $this->createChannelContext();
        $request = new Request([], [], ['blogId' => Uuid::randomHex()]);

        $event = null;
        $this->catchEvent(MinimalQuickViewPageLoadedEvent::class, $event);

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(BlogNotFoundException::class);
        } else {
            $this->expectException(BlogException::class);
        }

        $this->getPageLoader()->load($request, $context);
    }

    public function testItDoesLoadATestBlog(): void
    {
        $context = $this->createChannelContext();
        $blog = $this->getRandomBlog($context);

        $request = new Request([], [], ['blogId' => $blog->getId()]);

        $event = null;
        $this->catchEvent(MinimalQuickViewPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame(FrontendPageTestConstants::BLOG_NAME, $page->getBlog()->getName());
        self::assertPageEvent(MinimalQuickViewPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItDispatchPageCriteriaEvent(): void
    {
        $context = $this->createChannelContextWithNavigation();
        $blog = $this->getRandomBlog($context);

        $request = new Request([], [], ['blogId' => $blog->getId()]);

        $event = null;
        $this->catchEvent(MinimalQuickViewPageCriteriaEvent::class, $event);

        $this->getPageLoader()->load($request, $context);
        static::assertInstanceOf(MinimalQuickViewPageCriteriaEvent::class, $event);
    }

    protected function getPageLoader(): MinimalQuickViewPageLoader
    {
        return static::getContainer()->get(MinimalQuickViewPageLoader::class);
    }
}
