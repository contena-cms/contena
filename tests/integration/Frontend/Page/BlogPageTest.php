<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Exception\BlogNotFoundException;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Frontend\Page\Blog\BlogPageCriteriaEvent;
use Contena\Frontend\Page\Blog\BlogPageLoadedEvent;
use Contena\Frontend\Page\Blog\BlogPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class BlogPageTest extends TestCase
{
    use FrontendPageTestBehaviour;
    use IntegrationTestBehaviour;

    private const string DEFAULT_WEB_CHANNEL_ID = 'c6d2905ae914eb8d6320c54d2d1cab04';

    private const string DEFAULT_BLOG_ID = '3bdbb2474ffec6bfc96342ec3f4a75a0';

    public function testItRequiresBlogParam(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('blogId', '/blogId'));

        $this->getPageLoader()->load(new Request(), $this->createDefaultFrontendContext());
    }

    public function testItFailsWithANonExistingBlog(): void
    {
        $blogId = Uuid::randomHex();
        $request = new Request([], [], ['blogId' => $blogId]);

        $this->expectExceptionObject(new BlogNotFoundException($blogId));

        $this->getPageLoader()->load($request, $this->createDefaultFrontendContext());
    }

    public function testItLoadsTheDefaultBlog(): void
    {
        $request = new Request([], [], ['blogId' => self::DEFAULT_BLOG_ID]);
        $context = $this->createDefaultFrontendContext();

        $loadedEvent = null;
        $criteriaEvent = null;
        $this->catchEvent(BlogPageLoadedEvent::class, $loadedEvent);
        $this->catchEvent(BlogPageCriteriaEvent::class, $criteriaEvent);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame(self::DEFAULT_BLOG_ID, $page->getBlog()->getId());
        static::assertSame('Welcome to Contena', $page->getBlog()->getTranslation('name'));
        static::assertInstanceOf(BlogPageCriteriaEvent::class, $criteriaEvent);
        static::assertSame(self::DEFAULT_BLOG_ID, $criteriaEvent->getBlogId());
        static::assertPageEvent(BlogPageLoadedEvent::class, $loadedEvent, $context, $request, $page);
    }

    protected function getPageLoader(): BlogPageLoader
    {
        return static::getContainer()->get(BlogPageLoader::class);
    }

    private function createDefaultFrontendContext(): ChannelContext
    {
        return static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            self::DEFAULT_WEB_CHANNEL_ID,
        );
    }
}
